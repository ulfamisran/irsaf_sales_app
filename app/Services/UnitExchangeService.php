<?php

namespace App\Services;

use App\Models\CashFlow;
use App\Models\ExpenseCategory;
use App\Models\IncomeCategory;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\Stock;
use App\Models\UnitExchange;
use App\Models\UnitExchangeItem;
use App\Support\CatatRange;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UnitExchangeService
{
    public function __construct(
        private StockMutationService $stock
    ) {}

    public function today(): string
    {
        return Carbon::now(CatatRange::TZ)->toDateString();
    }

    public function peekNumber(): string
    {
        return $this->nextNumber($this->today());
    }

    /**
     * @param  list<array{old_serial: string, new_serial: string, price_difference: float|int|string, payment_method_id?: int|null}>  $items
     */
    public function exchange(Sale $sale, array $items, int $userId, string $status = UnitExchange::STATUS_RELEASED, ?UnitExchange $existing = null): UnitExchange
    {
        if (! in_array($status, [UnitExchange::STATUS_OPEN, UnitExchange::STATUS_RELEASED], true)) {
            throw new InvalidArgumentException(__('Status tukar unit tidak valid.'));
        }
        if ($sale->status !== Sale::STATUS_RELEASED) {
            throw new InvalidArgumentException(__('Tukar unit hanya untuk penjualan yang sudah Release.'));
        }
        if ($existing && ((int) $existing->sale_id !== (int) $sale->id || $existing->status !== UnitExchange::STATUS_OPEN)) {
            throw new InvalidArgumentException(__('Tukar unit yang sudah Release tidak dapat diubah.'));
        }
        if ($items === []) {
            throw new InvalidArgumentException(__('Pilih minimal 1 unit untuk ditukar.'));
        }

        $exchangeDate = $this->today();
        $releasing = $status === UnitExchange::STATUS_RELEASED;

        return DB::transaction(function () use ($sale, $items, $userId, $exchangeDate, $existing, $releasing) {
            $sale->load(['saleDetails.product']);
            $locType = $sale->stockLocationType();
            $locId = $sale->stockLocationId();

            if ($existing) {
                $existing->items()->delete();
                $existing->update([
                    'exchange_date' => $exchangeDate,
                    'status' => UnitExchange::STATUS_OPEN,
                ]);
                $exchange = $existing;
            } else {
                $exchange = UnitExchange::create([
                    'exchange_number' => $this->nextNumber($exchangeDate, true),
                    'exchange_date' => $exchangeDate,
                    'status' => UnitExchange::STATUS_OPEN,
                    'sale_id' => $sale->id,
                    'user_id' => $userId,
                    'branch_id' => $sale->branch_id,
                    'warehouse_id' => $sale->warehouse_id,
                ]);
            }

            $appliedDifference = 0.0;

            $usedNew = [];
            $usedOld = [];
            foreach ($items as $item) {
                $oldSerial = trim((string) ($item['old_serial'] ?? ''));
                $newSerial = trim((string) ($item['new_serial'] ?? ''));
                $difference = round((float) ($item['price_difference'] ?? 0), 2);
                $paymentMethodId = isset($item['payment_method_id']) && $item['payment_method_id'] !== ''
                    ? (int) $item['payment_method_id']
                    : null;

                if ($oldSerial === '' || $newSerial === '') {
                    throw new InvalidArgumentException(__('Nomor serial lama dan nomor serial pengganti wajib diisi.'));
                }
                $oldKey = mb_strtolower($oldSerial);
                $newKey = mb_strtolower($newSerial);
                if ($oldKey === $newKey) {
                    throw new InvalidArgumentException(__('Nomor serial pengganti harus berbeda dari unit yang ditukar.'));
                }
                if (isset($usedOld[$oldKey]) || isset($usedNew[$newKey]) || isset($usedNew[$oldKey]) || isset($usedOld[$newKey])) {
                    throw new InvalidArgumentException(__('Nomor serial :serial dipakai lebih dari sekali.', ['serial' => $newSerial]));
                }
                $usedOld[$oldKey] = true;
                $usedNew[$newKey] = true;

                if (abs($difference) >= 0.01 && ! $paymentMethodId) {
                    throw new InvalidArgumentException(__('Metode pembayaran wajib diisi bila ada selisih harga.'));
                }
                if ($paymentMethodId) {
                    $this->assertPaymentMethod($sale, $paymentMethodId);
                }
                $this->assertSerialNotDrafted($sale, $oldSerial, $newSerial, $existing);

                [$detail, $serials, $index] = $this->findDetailSerial($sale, $oldSerial);
                $oldUnit = $this->findSoldUnit($oldSerial, (int) $detail->product_id);
                $newUnit = $this->findReplacement($newSerial, $locType, $locId);

                $unitPrice = round((float) $detail->price, 2);
                if ($releasing) {
                    $this->swapSerialOnSale($sale, $detail, $serials, $index, $newUnit, $unitPrice);
                    $this->swapUnitStatus($oldUnit, $newUnit, $locType, $locId, $exchangeDate);
                    $appliedDifference += $difference;
                }

                UnitExchangeItem::create([
                    'unit_exchange_id' => $exchange->id,
                    'sale_detail_id' => $detail->exists ? $detail->id : null,
                    'product_id' => $newUnit->product_id,
                    'old_unit_id' => $oldUnit->id,
                    'new_unit_id' => $newUnit->id,
                    'old_serial' => $oldUnit->serial_number,
                    'new_serial' => $newUnit->serial_number,
                    'unit_price' => $unitPrice,
                    'price_difference' => $difference,
                    'payment_method_id' => abs($difference) >= 0.01 ? $paymentMethodId : null,
                ]);

                if ($releasing && abs($difference) >= 0.01) {
                    $this->recordCash($exchange, $sale, $oldUnit->serial_number, $newUnit->serial_number, $difference, (int) $paymentMethodId, $userId, $exchangeDate);
                }

                if ($releasing) {
                    $sale->load(['saleDetails.product']);
                }
            }

            if ($releasing) {
                $exchange->update(['status' => UnitExchange::STATUS_RELEASED]);
                if (abs($appliedDifference) >= 0.01) {
                    $sale->update([
                        'total' => round((float) $sale->total + $appliedDifference, 2),
                        'total_paid' => round((float) $sale->total_paid + $appliedDifference, 2),
                    ]);
                }
            }

            return $exchange->load(['items.product', 'items.paymentMethod', 'sale', 'user']);
        });
    }

    /**
     * @return array{id: int, invoice_number: string, customer: string, sale_date: string, units: list<array{serial: string, sku: string, product: string, price: float}>, payment_methods: list<array{id: int, label: string}>}
     */
    public function invoicePayload(Sale $sale): array
    {
        $sale->loadMissing(['saleDetails.product', 'customer']);
        $units = [];
        foreach ($sale->saleDetails as $detail) {
            $product = $detail->product;
            $name = trim((string) ($product?->brand.' '.($product?->series ?? '')));
            foreach ($this->serialsOf($detail) as $serial) {
                $units[] = [
                    'serial' => $serial,
                    'sku' => (string) ($product?->sku ?? ''),
                    'product' => $name !== '' ? $name : (string) ($product?->sku ?? ''),
                    'price' => round((float) $detail->price, 2),
                ];
            }
        }

        $methods = PaymentMethod::query()
            ->where('is_active', true)
            ->forLocation(
                $sale->isWarehouseSale() ? null : (int) $sale->branch_id,
                $sale->isWarehouseSale() ? (int) $sale->warehouse_id : null
            )
            ->orderBy('jenis_pembayaran')
            ->get();

        return [
            'id' => $sale->id,
            'invoice_number' => $sale->invoice_number,
            'customer' => (string) ($sale->customer?->name ?? '-'),
            'sale_date' => $sale->sale_date?->format('d/m/Y') ?? '-',
            'units' => $units,
            'payment_methods' => $methods->map(fn (PaymentMethod $method) => [
                'id' => $method->id,
                'label' => $method->display_label,
            ])->values()->all(),
        ];
    }

    private function nextNumber(string $date, bool $lock = false): string
    {
        $prefix = 'TU-'.str_replace('-', '', $date).'-';
        $query = UnitExchange::query()
            ->where('exchange_number', 'like', $prefix.'%')
            ->orderByDesc('id');
        if ($lock) {
            $query->lockForUpdate();
        }
        $last = $query->value('exchange_number');
        $seq = $last ? ((int) substr((string) $last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @return array{0: SaleDetail, 1: list<string>, 2: int}
     */
    private function findDetailSerial(Sale $sale, string $serial): array
    {
        foreach ($sale->saleDetails as $detail) {
            $serials = $this->serialsOf($detail);
            foreach ($serials as $index => $current) {
                if (mb_strtolower($current) === mb_strtolower($serial)) {
                    return [$detail, $serials, $index];
                }
            }
        }

        throw new InvalidArgumentException(__('Nomor serial :serial tidak ada pada invoice ini.', ['serial' => $serial]));
    }

    private function findSoldUnit(string $serial, int $productId): ProductUnit
    {
        $units = ProductUnit::query()
            ->whereRaw('LOWER(TRIM(serial_number)) = ?', [mb_strtolower(trim($serial))])
            ->where('status', ProductUnit::STATUS_SOLD)
            ->lockForUpdate()
            ->get();

        $unit = $units->firstWhere('product_id', $productId) ?? $units->first();
        if (! $unit) {
            throw new InvalidArgumentException(__('Unit :serial tidak berstatus terjual.', ['serial' => $serial]));
        }

        return $unit;
    }

    private function findReplacement(string $serial, string $locationType, int $locationId): ProductUnit
    {
        $unit = ProductUnit::query()
            ->whereRaw('LOWER(TRIM(serial_number)) = ?', [mb_strtolower(trim($serial))])
            ->where('status', ProductUnit::STATUS_IN_STOCK)
            ->where('location_type', $locationType)
            ->where('location_id', $locationId)
            ->lockForUpdate()
            ->first();

        if (! $unit) {
            throw new InvalidArgumentException(__('Nomor serial :serial tidak tersedia sebagai stok di lokasi penjualan.', ['serial' => $serial]));
        }

        return $unit;
    }

    /**
     * @param  list<string>  $serials
     */
    private function swapSerialOnSale(Sale $sale, SaleDetail $detail, array $serials, int $index, ProductUnit $newUnit, float $unitPrice): void
    {
        array_splice($serials, $index, 1);
        $newSerial = $newUnit->serial_number;

        if ((int) $newUnit->product_id === (int) $detail->product_id) {
            $serials[] = $newSerial;
            $detail->serial_numbers = implode("\n", $serials);
            if (count($serials) === 1) {
                $detail->hpp = $newUnit->harga_hpp;
            }
            $detail->save();

            return;
        }

        $detail->quantity = max(0, (int) $detail->quantity - 1);
        $detail->serial_numbers = $serials === [] ? null : implode("\n", $serials);
        if ((int) $detail->quantity <= 0) {
            $detail->delete();
        } else {
            $detail->save();
        }

        $target = SaleDetail::query()
            ->where('sale_id', $sale->id)
            ->where('product_id', $newUnit->product_id)
            ->lockForUpdate()
            ->first();

        if ($target) {
            $existing = $this->serialsOf($target);
            $existing[] = $newSerial;
            $target->quantity = (int) $target->quantity + 1;
            $target->serial_numbers = implode("\n", $existing);
            if (count($existing) === 1) {
                $target->hpp = $newUnit->harga_hpp;
            }
            $target->save();

            return;
        }

        SaleDetail::create([
            'sale_id' => $sale->id,
            'product_id' => $newUnit->product_id,
            'quantity' => 1,
            'price' => $unitPrice,
            'hpp' => $newUnit->harga_hpp,
            'serial_numbers' => $newSerial,
        ]);
    }

    private function swapUnitStatus(ProductUnit $oldUnit, ProductUnit $newUnit, string $locationType, int $locationId, string $exchangeDate): void
    {
        $oldProductId = (int) $oldUnit->product_id;
        $previousType = (string) $oldUnit->location_type;
        $previousId = (int) $oldUnit->location_id;
        $newProductId = (int) $newUnit->product_id;

        $oldUnit->update([
            'status' => ProductUnit::STATUS_IN_STOCK,
            'sold_at' => null,
            'location_type' => $locationType,
            'location_id' => $locationId,
        ]);
        $newUnit->update([
            'status' => ProductUnit::STATUS_SOLD,
            'sold_at' => Carbon::parse($exchangeDate, CatatRange::TZ),
        ]);

        $returnKey = $oldProductId.'|'.$locationType.'|'.$locationId;
        $soldKey = $newProductId.'|'.$locationType.'|'.$locationId;
        $deltas = [$returnKey => 1, $soldKey => ($returnKey === $soldKey ? 0 : -1)];
        $spots = [
            [$oldProductId, $previousType, $previousId],
            [$oldProductId, $locationType, $locationId],
            [$newProductId, $locationType, $locationId],
        ];
        $seen = [];
        foreach ($spots as [$productId, $type, $id]) {
            $key = $productId.'|'.$type.'|'.$id;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $this->refreshStock($productId, $type, $id, $deltas[$key] ?? 0);
        }
    }

    private function refreshStock(int $productId, string $locationType, int $locationId, int $nonSerialDelta): void
    {
        $product = Product::query()->with('category')->find($productId);
        if (! $product) {
            return;
        }
        if ($product->category?->requires_serial ?? true) {
            $this->stock->recalculateStockQuantityIfExists($productId, $locationType, $locationId);

            return;
        }
        if ($nonSerialDelta === 0) {
            return;
        }
        $row = Stock::query()->firstOrCreate(
            [
                'product_id' => $productId,
                'location_type' => $locationType,
                'location_id' => $locationId,
            ],
            ['quantity' => 0]
        );
        if ($nonSerialDelta < 0 && (int) $row->quantity < abs($nonSerialDelta)) {
            throw new InvalidArgumentException(__('Stok tidak cukup untuk unit pengganti.'));
        }
        if ($nonSerialDelta > 0) {
            $row->increment('quantity', $nonSerialDelta);
        } else {
            $row->decrement('quantity', abs($nonSerialDelta));
        }
    }

    private function recordCash(UnitExchange $exchange, Sale $sale, string $oldSerial, string $newSerial, float $difference, int $paymentMethodId, int $userId, string $exchangeDate): void
    {
        $method = PaymentMethod::query()->find($paymentMethodId);
        $label = $method?->display_label ?? __('Pembayaran');
        $description = __('Tukar unit').' '.$exchange->exchange_number.' · '.$sale->invoice_number.' · '.$oldSerial.' → '.$newSerial.' - '.$label;
        $payload = [
            'branch_id' => $sale->branch_id,
            'warehouse_id' => $sale->warehouse_id,
            'amount' => abs($difference),
            'description' => $description,
            'reference_type' => CashFlow::REFERENCE_UNIT_EXCHANGE,
            'reference_id' => $exchange->id,
            'payment_method_id' => $paymentMethodId,
            'transaction_date' => $exchangeDate,
            'user_id' => $userId,
        ];

        if ($difference > 0) {
            $income = IncomeCategory::resolveByCode('UNIT_EXCHANGE', 'Tukar Unit', 'Pemasukan selisih tukar unit');
            CashFlow::create($payload + [
                'type' => CashFlow::TYPE_IN,
                'income_category_id' => $income->id,
            ]);

            return;
        }

        $expense = ExpenseCategory::query()->firstOrCreate(
            ['code' => 'TUKAR-UNIT'],
            [
                'name' => 'Pengembalian Tukar Unit',
                'description' => 'Pengembalian dana selisih tukar unit',
                'is_active' => true,
                'affects_profit_loss' => false,
            ]
        );
        if ($expense->affects_profit_loss) {
            $expense->update(['affects_profit_loss' => false]);
        }
        CashFlow::create($payload + [
            'type' => CashFlow::TYPE_OUT,
            'expense_category_id' => $expense->id,
        ]);
    }

    private function assertSerialNotDrafted(Sale $sale, string $oldSerial, string $newSerial, ?UnitExchange $existing): void
    {
        $query = UnitExchangeItem::query()
            ->whereHas('exchange', function ($exchange) use ($sale, $existing) {
                $exchange->where('sale_id', $sale->id)->where('status', UnitExchange::STATUS_OPEN);
                if ($existing) {
                    $exchange->where('id', '!=', $existing->id);
                }
            })
            ->where(function ($inner) use ($oldSerial, $newSerial) {
                $inner->whereRaw('LOWER(TRIM(old_serial)) = ?', [mb_strtolower($oldSerial)])
                    ->orWhereRaw('LOWER(TRIM(new_serial)) = ?', [mb_strtolower($newSerial)])
                    ->orWhereRaw('LOWER(TRIM(old_serial)) = ?', [mb_strtolower($newSerial)])
                    ->orWhereRaw('LOWER(TRIM(new_serial)) = ?', [mb_strtolower($oldSerial)]);
            });

        if ($query->exists()) {
            throw new InvalidArgumentException(__('Nomor serial ini masih ada pada tukar unit Open yang lain.'));
        }
    }

    private function assertPaymentMethod(Sale $sale, int $paymentMethodId): void
    {
        $allowed = PaymentMethod::query()
            ->whereKey($paymentMethodId)
            ->where('is_active', true)
            ->forLocation(
                $sale->isWarehouseSale() ? null : (int) $sale->branch_id,
                $sale->isWarehouseSale() ? (int) $sale->warehouse_id : null
            )
            ->exists();
        if (! $allowed) {
            throw new InvalidArgumentException(__('Metode pembayaran tidak sesuai lokasi penjualan.'));
        }
    }

    /**
     * @return list<string>
     */
    private function serialsOf(SaleDetail $detail): array
    {
        $parts = preg_split('/[\r\n,]+/', (string) $detail->serial_numbers) ?: [];

        return array_values(array_filter(array_map('trim', $parts), fn (string $serial) => $serial !== ''));
    }
}
