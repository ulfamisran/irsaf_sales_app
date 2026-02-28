<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\CashFlow;
use App\Models\ExpenseCategory;
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
     * @param  array<int, array{sku: string, serial_number: string, brand: string, series?: string, specs?: string, category_id: int, trade_in_value: float}>  $tradeIns  Tukar tambah (laptop bekas)
     */
    public function createDraftSale(
        int $branchId,
        array $items,
        string $saleDate,
        ?int $customerId = null,
        float $discountAmount = 0,
        float $taxAmount = 0,
        ?string $description = null,
        ?int $userId = null,
        array $payments = [],
        array $tradeIns = []
    ): Sale {
        if (empty($items)) {
            throw new InvalidArgumentException(__('Sale must have at least one item.'));
        }

        $branch = Branch::findOrFail($branchId);

        return DB::transaction(function () use ($branch, $items, $saleDate, $customerId, $discountAmount, $taxAmount, $description, $userId, $payments, $tradeIns) {
            [$details, $subTotal] = $this->buildDetails($items);

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
                'branch_id' => $branch->id,
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

            foreach ($this->buildTradeIns($tradeIns) as $ti) {
                SaleTradeIn::create([
                    'sale_id' => $sale->id,
                    'sku' => $ti['sku'],
                    'serial_number' => $ti['serial_number'],
                    'brand' => $ti['brand'],
                    'series' => $ti['series'] ?? null,
                    'specs' => $ti['specs'] ?? null,
                    'category_id' => $ti['category_id'],
                    'trade_in_value' => $ti['trade_in_value'],
                ]);
            }

            foreach ($details as $detail) {
                $serialNumbers = $detail['serial_numbers'] ?? [];
                $product = Product::find($detail['product_id']);
                $hpp = $product ? (float) $product->purchase_price : 0;

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
                        Stock::LOCATION_BRANCH,
                        (int) $branch->id,
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
        int $branchId,
        array $items,
        string $saleDate,
        array $payments,
        ?int $customerId = null,
        float $discountAmount = 0,
        float $taxAmount = 0,
        ?string $description = null,
        ?int $userId = null,
        array $tradeIns = []
    ): Sale {
        $sale = $this->createDraftSale($branchId, $items, $saleDate, $customerId, $discountAmount, $taxAmount, $description, $userId, $payments, $tradeIns);

        return $this->releaseSale($sale, $payments, $saleDate, $userId);
    }

    /**
     * Update an OPEN sale draft.
     *
     * @param  array<int, array{payment_method_id: int, amount: float, notes?: string|null}>  $payments
     * @param  array<int, array{sku: string, serial_number: string, brand: string, series?: string, specs?: string, category_id: int, trade_in_value: float}>  $tradeIns
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
        array $tradeIns = []
    ): Sale {
        if ($sale->status !== Sale::STATUS_OPEN) {
            throw new InvalidArgumentException(__('Sale is already released.'));
        }

        return DB::transaction(function () use ($sale, $items, $saleDate, $customerId, $discountAmount, $taxAmount, $description, $payments, $tradeIns) {
            // Release old reservations first (keep -> in_stock)
            $this->unreserveSaleUnits($sale, strict: false);

            [$details, $subTotal] = $this->buildDetails($items);

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
            foreach ($this->buildTradeIns($tradeIns) as $ti) {
                SaleTradeIn::create([
                    'sale_id' => $sale->id,
                    'sku' => $ti['sku'],
                    'serial_number' => $ti['serial_number'],
                    'brand' => $ti['brand'],
                    'series' => $ti['series'] ?? null,
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
                $hpp = $product ? (float) $product->purchase_price : 0;

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
                        Stock::LOCATION_BRANCH,
                        (int) $sale->branch_id,
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
    public function releaseSale(Sale $sale, array $payments, string $saleDate, ?int $userId = null): Sale
    {
        if ($sale->status !== Sale::STATUS_OPEN) {
            throw new InvalidArgumentException(__('Sale is already released.'));
        }

        $branch = Branch::findOrFail($sale->branch_id);

        return DB::transaction(function () use ($sale, $branch, $payments, $saleDate, $userId) {
            $details = SaleDetail::where('sale_id', $sale->id)->get();
            if ($details->isEmpty()) {
                throw new InvalidArgumentException(__('Invalid sale items.'));
            }

            $sum = $this->sumPayments($payments);
            $tradeIns = $sale->tradeIns()->get();
            $tradeInTotal = round((float) $tradeIns->sum('trade_in_value'), 2);
            $totalPaid = $sum + $tradeInTotal;
            $total = round((float) $sale->total, 2);

            if ($totalPaid < 0.01) {
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
                        Stock::LOCATION_BRANCH,
                        $branch->id,
                        $serialNumbers,
                        strict: true
                    );
                    $this->stockMutationService->sellUnits(
                        $product,
                        Stock::LOCATION_BRANCH,
                        $branch->id,
                        $serialNumbers,
                        Carbon::parse($saleDate)
                    );
                } else {
                    $this->stockMutationService->reduceStock(
                        $product,
                        Stock::LOCATION_BRANCH,
                        $branch->id,
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
            foreach (SalePayment::with('paymentMethod')->where('sale_id', $sale->id)->get() as $sp) {
                $pm = $sp->paymentMethod;
                $pmLabel = $pm ? $pm->display_label : __('Payment');

                CashFlow::create([
                    'branch_id' => $branch->id,
                    'type' => CashFlow::TYPE_IN,
                    'amount' => $sp->amount,
                    'description' => __('Sale') . ' ' . $sale->invoice_number . ' - ' . $pmLabel,
                    'reference_type' => CashFlow::REFERENCE_SALE,
                    'reference_id' => $sale->id,
                    'transaction_date' => $saleDate,
                    'user_id' => $userId ?? auth()->id(),
                ]);
            }

            // Proses tukar tambah: buat produk baru dari input manual, tambah ke stok cabang
            foreach ($tradeIns as $tradeIn) {
                $hpp = (float) $tradeIn->trade_in_value;

                // Buat produk baru dari laptop tukar (SKU, brand, series, specs, kategori dari input; HPP = nilai tukar)
                $sku = $this->ensureUniqueTradeInSku($tradeIn->sku);
                $newProduct = Product::create([
                    'category_id' => $tradeIn->category_id,
                    'sku' => $sku,
                    'brand' => $tradeIn->brand ?? '',
                    'series' => $tradeIn->series ?? '',
                    'specs' => $tradeIn->specs ?? '',
                    'purchase_price' => $hpp,
                    'selling_price' => $hpp,
                ]);

                $tradeIn->update(['product_id' => $newProduct->id]);

                // Tambah unit ke stok cabang (serial-based)
                $this->stockMutationService->addStock(
                    $newProduct,
                    Stock::LOCATION_BRANCH,
                    (int) $branch->id,
                    1,
                    $userId,
                    [$tradeIn->serial_number],
                    $saleDate
                );
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
     * @param  array<int, array{sku?: string, serial_number?: string, brand?: string, series?: string, specs?: string, category_id?: int, trade_in_value?: float}>  $tradeIns
     * @return array<int, array{sku: string, serial_number: string, brand: string, series?: string, specs?: string, category_id: int, trade_in_value: float}>
     */
    private function buildTradeIns(array $tradeIns): array
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
                'specs' => $ti['specs'] ?? null,
                'category_id' => $categoryId,
                'trade_in_value' => round($value, 2),
            ];
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
    public function cancelSale(Sale $sale): Sale
    {
        if ($sale->status !== Sale::STATUS_OPEN) {
            throw new InvalidArgumentException(__('Hanya penjualan OPEN yang bisa dibatalkan.'));
        }

        return DB::transaction(function () use ($sale) {
            try {
                $this->unreserveSaleUnits($sale, strict: false);
            } catch (InvalidArgumentException $e) {
                // Jika unit sudah sold atau error lain, tetap batalkan invoice tanpa mengubah unit
            }
            $sale->update(['status' => Sale::STATUS_CANCEL]);

            return $sale->fresh();
        });
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
                Stock::LOCATION_BRANCH,
                (int) $sale->branch_id,
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
     * @return array{0: array<int, array{product_id:int, quantity:int, price:float, serial_numbers: array<int,string>}>, 1: float}
     */
    private function buildDetails(array $items): array
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

            $details[] = [
                'product_id' => (int) $item['product_id'],
                'quantity' => $quantity,
                'price' => $price,
                'serial_numbers' => $serialNumbers,
            ];
            $subTotal += $quantity * $price;
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
