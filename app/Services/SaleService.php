<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Warehouse;
use App\Models\CashFlow;
use App\Models\ExpenseCategory;
use App\Models\IncomeCategory;
use App\Models\Distributor;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\SalePayment;
use App\Models\SaleTradeIn;
use App\Models\Stock;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SaleService
{
    public function __construct(
        protected StockMutationService $stockMutationService
    ) {}

    /**
     * Create a sale draft (OPEN). No stock/cashflow will be mutated.
     * Draft dapat menyimpan uang muka (pembayaran kurang dari total).
     *
     * @param  array<int, array{product_id: int, quantity: int, price: float, serial_numbers?: array<int,string>}>  $items
     * @param  array<int, array{payment_method_id: int, amount: float, notes?: string|null}>  $payments  Uang muka (boleh kurang dari total)
     * @param  array<int, array{sku: string, serial_number: string, brand: string, series?: string, processor?: string, ram?: string, storage?: string, color?: string, specs?: string, category_id: int, trade_in_value: float}>  $tradeIns  Tukar tambah (laptop bekas)
     */
    public function createDraftSale(
        ?int $branchId,
        ?int $warehouseId,
        array $items,
        string $saleDate,
        ?int $customerId = null,
        float $discountAmount = 0,
        float $taxAmount = 0,
        ?string $description = null,
        ?int $userId = null,
        array $payments = [],
        array $tradeIns = [],
        bool $allowSoldSerialReuse = false
    ): Sale {
        if (($branchId === null) === ($warehouseId === null)) {
            throw new InvalidArgumentException(__('Pilih cabang atau gudang untuk penjualan.'));
        }
        if (empty($items)) {
            throw new InvalidArgumentException(__('Sale must have at least one item.'));
        }

        $locType = $branchId !== null ? Stock::LOCATION_BRANCH : Stock::LOCATION_WAREHOUSE;
        $locId = $branchId !== null ? $branchId : (int) $warehouseId;

        if ($branchId !== null) {
            Branch::findOrFail($branchId);
        } else {
            Warehouse::findOrFail((int) $warehouseId);
        }

        return DB::transaction(function () use ($branchId, $warehouseId, $locType, $locId, $items, $saleDate, $customerId, $discountAmount, $taxAmount, $description, $userId, $payments, $tradeIns, $allowSoldSerialReuse) {
            [$details, $subTotal] = $this->buildDetails($items, $locType, $locId);

            $discountAmount = max(0, round($discountAmount, 2));
            $taxAmount = max(0, round($taxAmount, 2));
            $discountAmount = min($discountAmount, $subTotal);
            $grandTotal = max(0, round(($subTotal - $discountAmount + $taxAmount), 2));

            $paymentSum = $this->sumPayments($payments);
            $tradeInTotal = $this->sumTradeIns($tradeIns);

            // Total pembayaran = tunai + tukar tambah. Harus antara 0.01 dan grandTotal.
            $totalPaid = $paymentSum + $tradeInTotal;
            if ($totalPaid > 0) {
                if ($totalPaid < 0.01) {
                    throw new InvalidArgumentException(__('Total pembayaran minimal Rp 0,01.'));
                }
                if ($totalPaid > $grandTotal + 0.02) {
                    throw new InvalidArgumentException(
                        __('Total pembayaran (:sum) tidak boleh melebihi total penjualan (:total).', [
                            'sum' => number_format($totalPaid, 2, ',', '.'),
                            'total' => number_format($grandTotal, 2, ',', '.'),
                        ])
                    );
                }
            }

            $invoiceNumber = $this->generateInvoiceNumber();
            $sale = Sale::create([
                'invoice_number' => $invoiceNumber,
                'branch_id' => $branchId,
                'warehouse_id' => $warehouseId,
                'customer_id' => $customerId,
                'user_id' => $userId ?? auth()->id(),
                'total' => $grandTotal,
                'total_paid' => $totalPaid,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'description' => $description,
                'sale_date' => $saleDate,
                'status' => Sale::STATUS_OPEN,
                'released_at' => null,
            ]);

            foreach ($this->buildTradeIns($tradeIns, $allowSoldSerialReuse) as $ti) {
                SaleTradeIn::create([
                    'sale_id' => $sale->id,
                    'sku' => $ti['sku'],
                    'serial_number' => $ti['serial_number'],
                    'brand' => $ti['brand'],
                    'series' => $ti['series'] ?? null,
                    'processor' => $ti['processor'] ?? null,
                    'ram' => $ti['ram'] ?? null,
                    'storage' => $ti['storage'] ?? null,
                    'color' => $ti['color'] ?? null,
                    'specs' => $ti['specs'] ?? null,
                    'category_id' => $ti['category_id'],
                    'trade_in_value' => $ti['trade_in_value'],
                ]);
            }

            foreach ($details as $detail) {
                $serialNumbers = $detail['serial_numbers'] ?? [];
                $product = Product::find($detail['product_id']);
                $hpp = isset($detail['hpp']) ? (float) $detail['hpp'] : ($product ? (float) $product->purchase_price : 0);

                SaleDetail::create([
                    'sale_id' => $sale->id,
                    'product_id' => $detail['product_id'],
                    'quantity' => $detail['quantity'],
                    'price' => $detail['price'],
                    'hpp' => $hpp,
                    'serial_numbers' => ! empty($serialNumbers) ? implode("\n", $serialNumbers) : null,
                ]);

                // Reserve serial-numbered units for OPEN sale (in_stock -> keep)
                $productId = (int) $detail['product_id'];
                $isSerialTracked = ProductUnit::query()->where('product_id', $productId)->exists();
                if ($isSerialTracked) {
                    if (count($serialNumbers) !== (int) $detail['quantity']) {
                        $product = Product::find($productId);
                        throw new InvalidArgumentException(
                            __('Please select serial numbers equal to quantity for :sku', ['sku' => $product?->sku ?? $productId])
                        );
                    }
                    $product = Product::findOrFail($productId);
                    $this->stockMutationService->reserveUnits(
                        $product,
                        $locType,
                        $locId,
                        $serialNumbers
                    );
                }
            }

            // Simpan uang muka (SalePayment) untuk draft
            foreach ($payments as $p) {
                $amt = round((float) ($p['amount'] ?? 0), 2);
                if ($amt <= 0) {
                    continue;
                }
                SalePayment::create([
                    'sale_id' => $sale->id,
                    'payment_method_id' => (int) $p['payment_method_id'],
                    'amount' => $amt,
                    'notes' => $p['notes'] ?? null,
                ]);
            }

            return $sale->load(['saleDetails', 'payments.paymentMethod', 'tradeIns']);
        });
    }

    /**
     * Create a released sale (RELEASED). Will mutate stock and create cashflow & payments.
     *
     * @param  array<int, array{payment_method_id: int, amount: float, notes?: string|null}>  $payments
     */
    public function createReleasedSale(
        ?int $branchId,
        ?int $warehouseId,
        array $items,
        string $saleDate,
        array $payments,
        ?int $customerId = null,
        float $discountAmount = 0,
        float $taxAmount = 0,
        ?string $description = null,
        ?int $userId = null,
        array $tradeIns = [],
        bool $allowSoldSerialReuse = false
    ): Sale {
        $sale = $this->createDraftSale($branchId, $warehouseId, $items, $saleDate, $customerId, $discountAmount, $taxAmount, $description, $userId, $payments, $tradeIns, $allowSoldSerialReuse);

        return $this->releaseSale($sale, $payments, $saleDate, $userId, $allowSoldSerialReuse);
    }

    /**
     * Update an OPEN sale draft.
     *
     * @param  array<int, array{payment_method_id: int, amount: float, notes?: string|null}>  $payments
     * @param  array<int, array{sku: string, serial_number: string, brand: string, series?: string, processor?: string, ram?: string, storage?: string, color?: string, specs?: string, category_id: int, trade_in_value: float}>  $tradeIns
     */
    public function updateDraftSale(
        Sale $sale,
        array $items,
        string $saleDate,
        ?int $customerId = null,
        float $discountAmount = 0,
        float $taxAmount = 0,
        ?string $description = null,
        array $payments = [],
        array $tradeIns = [],
        bool $allowSoldSerialReuse = false
    ): Sale {
        if ($sale->status !== Sale::STATUS_OPEN) {
            throw new InvalidArgumentException(__('Sale is already released.'));
        }

        return DB::transaction(function () use ($sale, $items, $saleDate, $customerId, $discountAmount, $taxAmount, $description, $payments, $tradeIns, $allowSoldSerialReuse) {
            // Release old reservations first (keep -> in_stock)
            $this->unreserveSaleUnits($sale, strict: false);

            [$details, $subTotal] = $this->buildDetails($items, $sale->stockLocationType(), $sale->stockLocationId());

            $discountAmount = max(0, round($discountAmount, 2));
            $taxAmount = max(0, round($taxAmount, 2));
            $discountAmount = min($discountAmount, $subTotal);
            $grandTotal = max(0, round(($subTotal - $discountAmount + $taxAmount), 2));

            $paymentSum = $this->sumPayments($payments);
            $tradeInTotal = $this->sumTradeIns($tradeIns);
            $totalPaid = $paymentSum + $tradeInTotal;

            if ($totalPaid > 0) {
                if ($totalPaid < 0.01) {
                    throw new InvalidArgumentException(__('Total pembayaran minimal Rp 0,01.'));
                }
                if ($totalPaid > $grandTotal + 0.02) {
                    throw new InvalidArgumentException(__('Total pembayaran tidak boleh melebihi total penjualan.'));
                }
            }

            $sale->update([
                'customer_id' => $customerId,
                'sale_date' => $saleDate,
                'total' => $grandTotal,
                'total_paid' => $totalPaid,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'description' => $description,
            ]);

            // Replace trade-ins
            SaleTradeIn::where('sale_id', $sale->id)->delete();
            foreach ($this->buildTradeIns($tradeIns, $allowSoldSerialReuse) as $ti) {
                SaleTradeIn::create([
                    'sale_id' => $sale->id,
                    'sku' => $ti['sku'],
                    'serial_number' => $ti['serial_number'],
                    'brand' => $ti['brand'],
                    'series' => $ti['series'] ?? null,
                    'processor' => $ti['processor'] ?? null,
                    'ram' => $ti['ram'] ?? null,
                    'storage' => $ti['storage'] ?? null,
                    'color' => $ti['color'] ?? null,
                    'specs' => $ti['specs'] ?? null,
                    'category_id' => $ti['category_id'],
                    'trade_in_value' => $ti['trade_in_value'],
                ]);
            }

            // Replace details (simpan HPP dari product)
            SaleDetail::where('sale_id', $sale->id)->delete();
            foreach ($details as $detail) {
                $serialNumbers = $detail['serial_numbers'] ?? [];
                $product = Product::find($detail['product_id']);
                $hpp = isset($detail['hpp']) ? (float) $detail['hpp'] : ($product ? (float) $product->purchase_price : 0);

                SaleDetail::create([
                    'sale_id' => $sale->id,
                    'product_id' => $detail['product_id'],
                    'quantity' => $detail['quantity'],
                    'price' => $detail['price'],
                    'hpp' => $hpp,
                    'serial_numbers' => ! empty($serialNumbers) ? implode("\n", $serialNumbers) : null,
                ]);

                // Reserve new selections
                $productId = (int) $detail['product_id'];
                $isSerialTracked = ProductUnit::query()->where('product_id', $productId)->exists();
                if ($isSerialTracked) {
                    if (count($serialNumbers) !== (int) $detail['quantity']) {
                        $product = Product::find($productId);
                        throw new InvalidArgumentException(
                            __('Please select serial numbers equal to quantity for :sku', ['sku' => $product?->sku ?? $productId])
                        );
                    }
                    $product = Product::findOrFail($productId);
                    $this->stockMutationService->reserveUnits(
                        $product,
                        $sale->stockLocationType(),
                        $sale->stockLocationId(),
                        $serialNumbers
                    );
                }
            }

            // Replace draft payments
            SalePayment::where('sale_id', $sale->id)->delete();
            foreach ($payments as $p) {
                $amt = round((float) ($p['amount'] ?? 0), 2);
                if ($amt <= 0) {
                    continue;
                }
                SalePayment::create([
                    'sale_id' => $sale->id,
                    'payment_method_id' => (int) $p['payment_method_id'],
                    'amount' => $amt,
                    'notes' => $p['notes'] ?? null,
                ]);
            }

            return $sale->fresh()->load(['saleDetails', 'payments.paymentMethod', 'tradeIns']);
        });
    }

    /**
     * Release an OPEN sale: reduce stock, save payments, create cashflows, lock editing.
     *
     * @param  array<int, array{payment_method_id: int, amount: float, notes?: string|null}>  $payments
     */
    public function releaseSale(Sale $sale, array $payments, string $saleDate, ?int $userId = null, bool $allowSoldSerialReuse = true): Sale
    {
        if ($sale->status !== Sale::STATUS_OPEN) {
            throw new InvalidArgumentException(__('Sale is already released.'));
        }

        if (! $sale->isWarehouseSale()) {
            Branch::findOrFail((int) $sale->branch_id);
        } else {
            Warehouse::findOrFail((int) $sale->warehouse_id);
        }

        $locType = $sale->stockLocationType();
        $locId = $sale->stockLocationId();

        return DB::transaction(function () use ($sale, $locType, $locId, $payments, $saleDate, $userId, $allowSoldSerialReuse) {
            $details = SaleDetail::where('sale_id', $sale->id)->get();
            if ($details->isEmpty()) {
                throw new InvalidArgumentException(__('Invalid sale items.'));
            }

            $sum = $this->sumPayments($payments);
            $tradeIns = $sale->tradeIns()->get();
            $tradeInTotal = round((float) $tradeIns->sum('trade_in_value'), 2);
            $totalPaid = $sum + $tradeInTotal;
            $total = round((float) $sale->total, 2);

            if ($total > 0 && $totalPaid < 0.01) {
                throw new InvalidArgumentException(__('Total pembayaran minimal Rp 0,01. Isi metode pembayaran tunai dan/atau tukar tambah.'));
            }
            if ($totalPaid > $total + 0.02) {
                throw new InvalidArgumentException(
                    __('Total pembayaran (:sum) tidak boleh melebihi total penjualan (:total).', [
                        'sum' => number_format($totalPaid, 2, ',', '.'),
                        'total' => number_format($total, 2, ',', '.'),
                    ])
                );
            }

            // Reduce stock now (validate serial availability at release time)
            foreach ($details as $detail) {
                $product = Product::findOrFail((int) $detail->product_id);
                $serialNumbers = [];
                if ($detail->serial_numbers) {
                    $serialNumbers = preg_split('/[\r\n,]+/', (string) $detail->serial_numbers) ?: [];
                    $serialNumbers = array_values(array_unique(array_filter(array_map('trim', $serialNumbers))));
                }

                $isSerialTracked = ProductUnit::where('product_id', $product->id)->exists();
                if ($isSerialTracked) {
                    if (count($serialNumbers) !== (int) $detail->quantity) {
                        throw new InvalidArgumentException(__('Please select serial numbers equal to quantity for :sku', ['sku' => $product->sku]));
                    }
                    // If units were reserved while OPEN (keep), release them back to in_stock first.
                    // This keeps sellUnits() strict (only sells in_stock) and prevents other sales from selling kept units.
                    $this->stockMutationService->unreserveUnits(
                        $product,
                        $locType,
                        $locId,
                        $serialNumbers,
                        strict: true
                    );
                    $this->stockMutationService->sellUnits(
                        $product,
                        $locType,
                        $locId,
                        $serialNumbers,
                        Carbon::parse($saleDate)
                    );
                } else {
                    $this->stockMutationService->reduceStock(
                        $product,
                        $locType,
                        $locId,
                        (int) $detail->quantity
                    );
                }
            }

            // Save payments
            SalePayment::where('sale_id', $sale->id)->delete();
            foreach ($payments as $p) {
                $amt = round((float) ($p['amount'] ?? 0), 2);
                if ($amt <= 0) {
                    continue;
                }
                SalePayment::create([
                    'sale_id' => $sale->id,
                    'payment_method_id' => (int) $p['payment_method_id'],
                    'amount' => $amt,
                    'notes' => $p['notes'] ?? null,
                ]);
            }

            // Create cashflow IN entries (one per payment method)
            $penjualanCategory = IncomeCategory::resolveByCode('SALE', 'Penjualan');
            foreach (SalePayment::with('paymentMethod')->where('sale_id', $sale->id)->get() as $sp) {
                $pm = $sp->paymentMethod;
                $pmLabel = $pm ? $pm->display_label : __('Payment');

                CashFlow::create([
                    'branch_id' => $sale->branch_id,
                    'warehouse_id' => $sale->warehouse_id,
                    'type' => CashFlow::TYPE_IN,
                    'amount' => $sp->amount,
                    'description' => __('Sale') . ' ' . $sale->invoice_number . ' - ' . $pmLabel,
                    'reference_type' => CashFlow::REFERENCE_SALE,
                    'reference_id' => $sale->id,
                    'income_category_id' => $penjualanCategory->id,
                    'payment_method_id' => (int) $sp->payment_method_id,
                    'transaction_date' => $saleDate,
                    'user_id' => $userId ?? auth()->id(),
                ]);
            }

            // Proses tukar tambah: buat produk & unit baru, default nonaktif
            foreach ($tradeIns as $tradeIn) {
                $hpp = (float) $tradeIn->trade_in_value;
                $tradeInDistributor = Distributor::firstOrCreate(
                    ['name' => 'TUKAR TAMBAH'],
                    ['address' => null, 'phone' => null]
                );

                $existingSoldUnit = ProductUnit::where('serial_number', $tradeIn->serial_number)
                    ->where('status', ProductUnit::STATUS_SOLD)
                    ->lockForUpdate()
                    ->first();
                if ($existingSoldUnit && ! $allowSoldSerialReuse) {
                    throw new InvalidArgumentException(
                        __('Nomor serial tukar tambah sudah pernah terjual. Konfirmasi update data terlebih dahulu: :serial', [
                            'serial' => $tradeIn->serial_number,
                        ])
                    );
                }

                $sellingPrice = $hpp;
                $isActive = $sellingPrice > 0;
                $unitStatus = $sellingPrice > 0 ? ProductUnit::STATUS_IN_STOCK : ProductUnit::STATUS_INACTIVE;

                $productPayload = [
                    'category_id' => $tradeIn->category_id,
                    'distributor_id' => $tradeInDistributor->id,
                    'user_id' => $userId ?? auth()->id(),
                    'brand' => $tradeIn->brand ?? '',
                    'series' => $tradeIn->series ?? '',
                    'processor' => $tradeIn->processor ?? '',
                    'ram' => $tradeIn->ram ?? '',
                    'storage' => $tradeIn->storage ?? '',
                    'color' => $tradeIn->color ?? '',
                    'specs' => $tradeIn->specs ?? '',
                    'laptop_type' => 'bekas',
                    'purchase_price' => $hpp,
                    'selling_price' => $hpp,
                    'is_active' => $isActive,
                    'location_type' => $locType,
                    'location_id' => $locId,
                ];

                if ($existingSoldUnit) {
                    $existingProduct = Product::find($existingSoldUnit->product_id);
                    if (! $existingProduct) {
                        throw new InvalidArgumentException(__('Produk lama untuk serial :serial tidak ditemukan.', ['serial' => $tradeIn->serial_number]));
                    }
                    $existingProduct->update(array_merge($productPayload, [
                        'sku' => $existingProduct->sku ?: $tradeIn->sku,
                    ]));
                    $tradeIn->update(['product_id' => $existingProduct->id]);

                    $existingSoldUnit->update([
                        'product_id' => $existingProduct->id,
                        'user_id' => $userId ?? auth()->id(),
                        'harga_hpp' => $hpp,
                        'harga_jual' => $sellingPrice,
                        'location_type' => $locType,
                        'location_id' => $locId,
                        'status' => $unitStatus,
                        'received_date' => $saleDate,
                        'sold_at' => null,
                        'notes' => null,
                    ]);

                    Stock::updateOrCreate(
                        [
                            'product_id' => $existingProduct->id,
                            'location_type' => $locType,
                            'location_id' => $locId,
                        ],
                        ['quantity' => 0]
                    );
                } else {
                    // Buat produk baru dari laptop tukar (SKU, brand, series, specs, kategori dari input; HPP = nilai tukar)
                    // Lokasi mengikuti lokasi penjualan (cabang / gudang)
                    $sku = $this->ensureUniqueTradeInSku($tradeIn->sku);
                    $newProduct = Product::create(array_merge($productPayload, ['sku' => $sku]));
                    $tradeIn->update(['product_id' => $newProduct->id]);

                    // Unit: inactive jika harga jual 0, in_stock jika ada harga jual
                    ProductUnit::create([
                        'product_id' => $newProduct->id,
                        'user_id' => $userId,
                        'serial_number' => $tradeIn->serial_number,
                        'location_type' => $locType,
                        'location_id' => $locId,
                        'status' => $unitStatus,
                        'received_date' => $saleDate,
                    ]);
                    Stock::updateOrCreate(
                        [
                            'product_id' => $newProduct->id,
                            'location_type' => $locType,
                            'location_id' => $locId,
                        ],
                        ['quantity' => 0]
                    );
                }
            }

            $sale->update([
                'status' => Sale::STATUS_RELEASED,
                'released_at' => now(),
                'sale_date' => $saleDate,
                'total_paid' => $totalPaid,
            ]);

            return $sale->fresh()->load(['saleDetails', 'payments.paymentMethod']);
        });
    }

    /**
     * Add a partial payment to an already-released sale.
     */
    public function addPayment(
        Sale $sale,
        int $paymentMethodId,
        float $amount,
        ?int $userId = null,
        ?string $transactionDate = null,
        ?string $notes = null
    ): SalePayment {
        if ($sale->status !== Sale::STATUS_RELEASED) {
            throw new InvalidArgumentException(__('Pembayaran hanya bisa ditambahkan pada penjualan yang sudah dirilis.'));
        }

        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException(__('Nominal pembayaran harus lebih dari 0.'));
        }

        $remaining = round((float) $sale->total - (float) $sale->total_paid, 2);
        if ($amount > $remaining + 0.02) {
            throw new InvalidArgumentException(
                __('Nominal pembayaran (:amount) melebihi sisa tagihan (:remaining).', [
                    'amount' => number_format($amount, 0, ',', '.'),
                    'remaining' => number_format($remaining, 0, ',', '.'),
                ])
            );
        }

        $transactionDate = $transactionDate ?: now()->toDateString();

        return DB::transaction(function () use ($sale, $paymentMethodId, $amount, $userId, $transactionDate, $notes) {
            $payment = SalePayment::create([
                'sale_id' => $sale->id,
                'payment_method_id' => $paymentMethodId,
                'amount' => $amount,
                'notes' => $notes,
            ]);

            $pm = $payment->paymentMethod;
            $pmLabel = $pm ? $pm->display_label : __('Payment');
            $penjualanCategory = IncomeCategory::resolveByCode('SALE', 'Penjualan');

            CashFlow::create([
                'branch_id' => $sale->branch_id,
                'warehouse_id' => $sale->warehouse_id,
                'type' => CashFlow::TYPE_IN,
                'amount' => $amount,
                'description' => __('Sale') . ' ' . $sale->invoice_number . ' - ' . $pmLabel,
                'reference_type' => CashFlow::REFERENCE_SALE,
                'reference_id' => $sale->id,
                'income_category_id' => $penjualanCategory->id,
                'payment_method_id' => $paymentMethodId,
                'transaction_date' => $transactionDate,
                'user_id' => $userId ?? auth()->id(),
            ]);

            $newTotalPaid = round((float) $sale->total_paid + $amount, 2);
            $sale->update(['total_paid' => $newTotalPaid]);

            return $payment;
        });
    }

    /**
     * @param  array<int, array{amount?: float}>  $payments
     */
    private function sumPayments(array $payments): float
    {
        $sum = 0.0;
        foreach ($payments as $p) {
            $amt = (float) ($p['amount'] ?? 0);
            if ($amt > 0) {
                $sum += $amt;
            }
        }

        return round($sum, 2);
    }

    /**
     * @param  array<int, array{trade_in_value?: float}>  $tradeIns
     */
    private function sumTradeIns(array $tradeIns): float
    {
        $sum = 0.0;
        foreach ($tradeIns as $ti) {
            $val = (float) ($ti['trade_in_value'] ?? 0);
            if ($val > 0) {
                $sum += $val;
            }
        }

        return round($sum, 2);
    }

    /**
     * @param  array<int, array{sku?: string, serial_number?: string, brand?: string, series?: string, processor?: string, ram?: string, storage?: string, color?: string, specs?: string, category_id?: int, trade_in_value?: float}>  $tradeIns
     * @return array<int, array{sku: string, serial_number: string, brand: string, series?: string, processor?: string, ram?: string, storage?: string, color?: string, specs?: string, category_id: int, trade_in_value: float}>
     */
    private function buildTradeIns(array $tradeIns, bool $allowSoldSerialReuse = false): array
    {
        $result = [];
        foreach ($tradeIns as $ti) {
            $sku = trim((string) ($ti['sku'] ?? ''));
            $serial = trim((string) ($ti['serial_number'] ?? ''));
            $brand = trim((string) ($ti['brand'] ?? ''));
            $categoryId = (int) ($ti['category_id'] ?? 0);
            $value = (float) ($ti['trade_in_value'] ?? 0);
            if ($sku === '' || $serial === '' || $brand === '' || $categoryId <= 0 || $value <= 0) {
                continue;
            }
            $result[] = [
                'sku' => $sku,
                'serial_number' => $serial,
                'brand' => $brand,
                'series' => $ti['series'] ?? null,
                'processor' => $ti['processor'] ?? null,
                'ram' => $ti['ram'] ?? null,
                'storage' => $ti['storage'] ?? null,
                'color' => $ti['color'] ?? null,
                'specs' => $ti['specs'] ?? null,
                'category_id' => $categoryId,
                'trade_in_value' => round($value, 2),
            ];
        }

        if (! empty($result)) {
            $serialKeys = [];
            $duplicates = [];
            foreach ($result as $row) {
                $serialKey = strtoupper((string) $row['serial_number']);
                if (isset($serialKeys[$serialKey])) {
                    $duplicates[] = $row['serial_number'];
                } else {
                    $serialKeys[$serialKey] = true;
                }
            }
            if (! empty($duplicates)) {
                throw new InvalidArgumentException(
                    __('Nomor serial tukar tambah tidak boleh duplikat: :serials', [
                        'serials' => implode(', ', array_values(array_unique($duplicates))),
                    ])
                );
            }

            $serials = array_map(fn ($row) => (string) $row['serial_number'], $result);
            $existingUnits = ProductUnit::whereIn('serial_number', $serials)
                ->get(['serial_number', 'status']);
            $blocked = [];
            $sold = [];
            foreach ($existingUnits as $unit) {
                if ($unit->status === ProductUnit::STATUS_SOLD) {
                    $sold[] = $unit->serial_number;
                } else {
                    $blocked[] = $unit->serial_number;
                }
            }
            if (! empty($blocked)) {
                throw new InvalidArgumentException(
                    __('Nomor serial tukar tambah sudah terdaftar (bukan SOLD): :serials', ['serials' => implode(', ', array_values(array_unique($blocked)))])
                );
            }
            if (! empty($sold) && ! $allowSoldSerialReuse) {
                throw new InvalidArgumentException(
                    __('Nomor serial tukar tambah sudah pernah terjual. Konfirmasi update data terlebih dahulu: :serials', [
                        'serials' => implode(', ', array_values(array_unique($sold))),
                    ])
                );
            }
        }

        return $result;
    }

    private function ensureUniqueTradeInSku(string $sku): string
    {
        $base = preg_replace('/[^a-zA-Z0-9\-_]/', '-', substr($sku, 0, 50));
        if ($base === '') {
            $base = 'TT-unknown';
        }

        $exists = Product::where('sku', $base)->exists();
        if (! $exists) {
            return $base;
        }

        $seq = 1;
        do {
            $candidate = $base . '-' . $seq;
            $exists = Product::where('sku', $candidate)->exists();
            $seq++;
        } while ($exists);

        return $base . '-' . ($seq - 1);
    }

    /**
     * Cancel an OPEN sale (void): return reserved units to in_stock if possible, set status to cancel.
     * Data penjualan tetap tersimpan.
     * Jika unit serial sudah sold, tetap batalkan invoice tanpa mengubah status unit.
     */
    public function cancelSale(Sale $sale, int $userId, string $reason): Sale
    {
        if (! in_array($sale->status, [Sale::STATUS_OPEN, Sale::STATUS_RELEASED], true)) {
            throw new InvalidArgumentException(__('Hanya penjualan OPEN atau RELEASED yang bisa dibatalkan.'));
        }

        return DB::transaction(function () use ($sale, $userId, $reason) {
            $locType = $sale->stockLocationType();
            $locId = $sale->stockLocationId();

            if ($sale->status === Sale::STATUS_OPEN) {
                try {
                    $this->unreserveSaleUnits($sale, strict: false);
                } catch (InvalidArgumentException $e) {
                    // Abaikan jika unit tidak dapat dikembalikan
                }
            }

            if ($sale->status === Sale::STATUS_RELEASED) {
                $details = SaleDetail::where('sale_id', $sale->id)->get();
                foreach ($details as $detail) {
                    $productId = (int) $detail->product_id;
                    $product = Product::find($productId);
                    if (! $product) {
                        continue;
                    }
                    $serialNumbers = $this->parseSerialNumbersText($detail->serial_numbers);
                    $isSerialTracked = ProductUnit::query()->where('product_id', $productId)->exists();
                    if ($isSerialTracked && ! empty($serialNumbers)) {
                        ProductUnit::where('product_id', $productId)
                            ->where('location_type', $locType)
                            ->where('location_id', $locId)
                            ->whereIn('serial_number', $serialNumbers)
                            ->update([
                                'status' => ProductUnit::STATUS_IN_STOCK,
                                'sold_at' => null,
                            ]);
                        $this->recalculateLocationStock($productId, $locType, $locId);
                    } else {
                        $stock = Stock::firstOrCreate(
                            [
                                'product_id' => $productId,
                                'location_type' => $locType,
                                'location_id' => $locId,
                            ],
                            ['quantity' => 0]
                        );
                        $stock->increment('quantity', (int) $detail->quantity);
                    }
                }
            }

            $refundDate = now()->toDateString();
            $reversalCategory = ExpenseCategory::firstOrCreate(
                ['code' => 'REVERSAL'],
                [
                    'name' => 'Reversal',
                    'description' => 'Pengembalian dana pembatalan transaksi',
                    'is_active' => true,
                ]
            );
            $payments = SalePayment::with('paymentMethod')->where('sale_id', $sale->id)->get();
            foreach ($payments as $sp) {
                $pmLabel = $sp->paymentMethod?->display_label ?? __('Payment');
                CashFlow::create([
                    'branch_id' => $sale->branch_id,
                    'warehouse_id' => $sale->warehouse_id,
                    'type' => CashFlow::TYPE_OUT,
                    'amount' => $sp->amount,
                    'description' => __('Pengembalian dana pembatalan penjualan') . ' ' . $sale->invoice_number . ' - ' . $pmLabel,
                    'reference_type' => CashFlow::REFERENCE_SALE,
                    'reference_id' => $sale->id,
                    'expense_category_id' => $reversalCategory->id,
                    'payment_method_id' => $sp->payment_method_id,
                    'transaction_date' => $refundDate,
                    'user_id' => $userId ?? auth()->id(),
                ]);
            }
            SalePayment::where('sale_id', $sale->id)->delete();

            // Tukar tambah dibatalkan: nonaktifkan produk & set unit status cancel
            $tradeIns = SaleTradeIn::where('sale_id', $sale->id)->get();
            foreach ($tradeIns as $ti) {
                if (! $ti->product_id) {
                    continue;
                }
                Product::where('id', $ti->product_id)->update(['is_active' => false]);
                ProductUnit::where('product_id', $ti->product_id)
                    ->when($ti->serial_number, fn ($q) => $q->where('serial_number', $ti->serial_number))
                    ->update(['status' => ProductUnit::STATUS_CANCEL]);
                Stock::where('product_id', $ti->product_id)
                    ->where('location_type', $locType)
                    ->where('location_id', $locId)
                    ->update(['quantity' => 0]);
            }

            $sale->update([
                'status' => Sale::STATUS_CANCEL,
                'released_at' => null,
                'cancel_date' => now()->toDateString(),
                'cancel_user_id' => $userId,
                'cancel_reason' => $reason,
            ]);

            return $sale->fresh();
        });
    }

    private function recalculateLocationStock(int $productId, string $locationType, int $locationId): void
    {
        $isSerialTracked = ProductUnit::where('product_id', $productId)->exists();
        if (! $isSerialTracked) {
            return;
        }
        $qty = ProductUnit::where('product_id', $productId)
            ->where('location_type', $locationType)
            ->where('location_id', $locationId)
            ->where('status', ProductUnit::STATUS_IN_STOCK)
            ->count();
        Stock::updateOrCreate(
            [
                'product_id' => $productId,
                'location_type' => $locationType,
                'location_id' => $locationId,
            ],
            ['quantity' => $qty]
        );
    }

    /**
     * Delete an OPEN sale and return reserved units to in_stock (hapus fisik record).
     */
    public function deleteDraftSale(Sale $sale): void
    {
        if ($sale->status !== Sale::STATUS_OPEN) {
            throw new InvalidArgumentException(__('Only OPEN sales can be deleted.'));
        }

        DB::transaction(function () use ($sale) {
            $this->unreserveSaleUnits($sale, strict: false);
            $sale->delete();
        });
    }

    private function unreserveSaleUnits(Sale $sale, bool $strict = true): void
    {
        $details = SaleDetail::where('sale_id', $sale->id)->get(['product_id', 'serial_numbers']);
        foreach ($details as $d) {
            $serialNumbers = $this->parseSerialNumbersText($d->serial_numbers);
            if (empty($serialNumbers)) {
                continue;
            }

            $productId = (int) $d->product_id;
            $isSerialTracked = ProductUnit::query()->where('product_id', $productId)->exists();
            if (! $isSerialTracked) {
                continue;
            }

            $product = Product::find($productId);
            if (! $product) {
                continue;
            }

            $this->stockMutationService->unreserveUnits(
                $product,
                $sale->stockLocationType(),
                $sale->stockLocationId(),
                $serialNumbers,
                $strict
            );
        }
    }

    /**
     * Parse serial numbers stored as newline/comma separated.
     *
     * @return array<int, string>
     */
    private function parseSerialNumbersText(?string $text): array
    {
        if (! $text) {
            return [];
        }
        $serials = preg_split('/[\r\n,]+/', (string) $text) ?: [];
        $serials = array_values(array_unique(array_filter(array_map('trim', $serials))));

        return $serials;
    }

    /**
     * @param  array<int, array{product_id: int, quantity: int, price: float, serial_numbers?: array<int,string>}>  $items
     * @return array{0: array<int, array{product_id:int, quantity:int, price:float, hpp?: float, serial_numbers: array<int,string>}>, 1: float}
     */
    private function buildDetails(array $items, string $locationType, int $locationId = 0): array
    {
        $subTotal = 0.0;
        $details = [];

        foreach ($items as $item) {
            $quantity = (int) ($item['quantity'] ?? 0);
            $price = (float) ($item['price'] ?? 0);
            if ($quantity <= 0 || $price < 0) {
                continue;
            }
            $serialNumbers = $item['serial_numbers'] ?? [];
            if (! is_array($serialNumbers)) {
                $serialNumbers = [];
            }
            $serialNumbers = array_values(array_unique(array_filter(array_map('trim', $serialNumbers))));

            $productId = (int) $item['product_id'];

            // Jika ada serial numbers, ambil HPP dari ProductUnit. Harga jual SELALU dari input user.
            if (! empty($serialNumbers) && $locationId > 0) {
                $units = ProductUnit::query()
                    ->where('product_id', $productId)
                    ->where('location_type', $locationType)
                    ->where('location_id', $locationId)
                    ->whereIn('serial_number', $serialNumbers)
                    ->get(['serial_number', 'harga_hpp']);

                if ($units->count() === count($serialNumbers)) {
                    $totalHpp = $units->sum(fn ($u) => (float) ($u->harga_hpp ?? 0));
                    $hppPerUnit = $quantity > 0 ? round($totalHpp / $quantity, 2) : 0;
                    $details[] = [
                        'product_id' => $productId,
                        'quantity' => $quantity,
                        'price' => $price, // Selalu dari input Harga Jual
                        'hpp' => $hppPerUnit, // Dari ProductUnit.harga_hpp
                        'serial_numbers' => $serialNumbers,
                    ];
                } else {
                    $details[] = [
                        'product_id' => $productId,
                        'quantity' => $quantity,
                        'price' => $price,
                        'serial_numbers' => $serialNumbers,
                    ];
                }
            } else {
                $details[] = [
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'price' => $price,
                    'serial_numbers' => $serialNumbers,
                ];
            }

            $subTotal += $quantity * ($details[array_key_last($details)]['price'] ?? $price);
        }

        if (empty($details)) {
            throw new InvalidArgumentException(__('Invalid sale items.'));
        }

        return [$details, round($subTotal, 2)];
    }

    private function generateInvoiceNumber(): string
    {
        $prefix = 'INV-' . date('Ymd') . '-';
        $last = Sale::where('invoice_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->first();
        $seq = $last ? (int) substr($last->invoice_number, -4) + 1 : 1;

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
