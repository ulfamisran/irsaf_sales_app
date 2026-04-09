<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\CashFlow;
use App\Models\ExpenseCategory;
use App\Models\IncomeCategory;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\PurchasePayment;
use App\Models\Service;
use App\Models\Stock;
use App\Models\StockMutation;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PurchaseService
{
    public function __construct(
        protected StockMutationService $stockMutationService
    ) {}

    /**
     * Create a purchase with details and optional initial payments.
     * Adds stock to the selected location and creates CashFlow OUT for each payment.
     *
     * @param  array<int, array{product_id: int, quantity: int, unit_price: float, serial_numbers?: array<int,string>}>  $items
     * @param  array<int, array{payment_method_id: int, amount: float, notes?: string|null}>  $payments
     */
    public function createPurchase(
        int $distributorId,
        string $locationType,
        int $locationId,
        array $items,
        string $purchaseDate,
        ?string $description = null,
        ?string $termin = null,
        ?string $dueDate = null,
        ?int $userId = null,
        array $payments = [],
        ?string $invoiceNumber = null,
        bool $allowSoldSerialReuse = false,
        string $jenisPembelian = Purchase::JENIS_PEMBELIAN_UNIT,
        ?int $serviceId = null
    ): Purchase {
        $userId = $userId ?? auth()->id();
        $this->validateLocation($locationType, $locationId);

        $allowedJenis = [
            Purchase::JENIS_PEMBELIAN_UNIT,
            Purchase::JENIS_DISTRIBUSI_UNIT,
            Purchase::JENIS_PEMBELIAN_SPAREPART_SERVICE,
        ];
        if (! in_array($jenisPembelian, $allowedJenis, true)) {
            $jenisPembelian = Purchase::JENIS_PEMBELIAN_UNIT;
        }

        if ($jenisPembelian === Purchase::JENIS_PEMBELIAN_SPAREPART_SERVICE) {
            if ($locationType !== Stock::LOCATION_BRANCH) {
                throw new InvalidArgumentException(__('Pembelian Sparepart Service hanya dapat dilakukan ke lokasi Cabang.'));
            }
            if (! $serviceId) {
                throw new InvalidArgumentException(__('Pilih nomor invoice service sebagai referensi.'));
            }
            $service = Service::find($serviceId);
            if (! $service || $service->status !== Service::STATUS_OPEN) {
                throw new InvalidArgumentException(__('Invoice service tidak valid atau sudah tidak berstatus open.'));
            }
            if ((int) $service->branch_id !== $locationId) {
                throw new InvalidArgumentException(__('Cabang pembelian harus sama dengan cabang invoice service.'));
            }
        } else {
            $serviceId = null;
        }

        if (empty($items)) {
            throw new InvalidArgumentException(__('Pembelian harus memiliki minimal satu barang.'));
        }

        $totalPaid = $this->sumPayments($payments);
        [$details, $total] = $this->buildDetails($items);

        if ($totalPaid > $total + 0.02) {
            throw new InvalidArgumentException(
                __('Total pembayaran tidak boleh melebihi total pembelian.')
            );
        }

        $warehouseId = $locationType === Stock::LOCATION_WAREHOUSE ? $locationId : null;
        $branchId = $locationType === Stock::LOCATION_BRANCH ? $locationId : null;

        return DB::transaction(function () use (
            $distributorId,
            $locationType,
            $locationId,
            $warehouseId,
            $branchId,
            $details,
            $total,
            $totalPaid,
            $purchaseDate,
            $description,
            $termin,
            $dueDate,
            $userId,
            $payments,
            $invoiceNumber,
            $allowSoldSerialReuse,
            $jenisPembelian,
            $serviceId
        ) {
            $invoiceNumber = ! empty(trim((string) $invoiceNumber))
                ? trim($invoiceNumber)
                : $this->generateInvoiceNumber();
            $purchase = Purchase::create([
                'invoice_number' => $invoiceNumber,
                'jenis_pembelian' => $jenisPembelian,
                'distributor_id' => $distributorId,
                'location_type' => $locationType,
                'warehouse_id' => $warehouseId,
                'branch_id' => $branchId,
                'service_id' => $serviceId,
                'purchase_date' => $purchaseDate,
                'total' => $total,
                'total_paid' => $totalPaid,
                'description' => $description,
                'termin' => $termin,
                'due_date' => $dueDate ?: null,
                'user_id' => $userId,
            ]);

            foreach ($details as $detail) {
                PurchaseDetail::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $detail['product_id'],
                    'quantity' => $detail['quantity'],
                    'unit_price' => $detail['unit_price'],
                    'subtotal' => $detail['subtotal'],
                    'serial_numbers' => ! empty($detail['serial_numbers']) ? implode("\n", $detail['serial_numbers']) : null,
                ]);

                $product = Product::findOrFail($detail['product_id']);
                $product->update(['is_active' => true]);
                $serialNumbers = $detail['serial_numbers'] ?? [];
                // Pembelian selalu menambah stok (IN).
                $this->stockMutationService->addStock(
                    $product,
                    $locationType,
                    $locationId,
                    $detail['quantity'],
                    $userId,
                    ! empty($serialNumbers) ? $serialNumbers : null,
                    $purchaseDate,
                    null,
                    null,
                    $allowSoldSerialReuse
                );

                // Khusus pembelian sparepart service: catat juga di riwayat mutasi stok sebagai IN.
                if ($purchase->isSparepartService()) {
                    StockMutation::create([
                        'invoice_number' => $purchase->invoice_number,
                        'product_id' => $product->id,
                        'from_location_type' => $locationType,
                        'from_location_id' => $locationId,
                        'to_location_type' => $locationType,
                        'to_location_id' => $locationId,
                        'quantity' => (int) $detail['quantity'],
                        'biaya_distribusi_per_unit' => 0,
                        'distribution_payment_method_id' => null,
                        'mutation_date' => $purchaseDate,
                        'notes' => 'IN Pembelian Sparepart User (SERVICE) - ' . $purchase->invoice_number,
                        'serial_numbers' => ! empty($serialNumbers) ? implode("\n", $serialNumbers) : null,
                        'user_id' => $userId,
                    ]);
                }
            }

            foreach ($payments as $p) {
                $amt = round((float) ($p['amount'] ?? 0), 2);
                if ($amt <= 0) {
                    continue;
                }
                $this->recordPurchasePayment($purchase, (int) $p['payment_method_id'], $amt, $purchaseDate, $userId, $p['notes'] ?? null);
            }

            return $purchase->fresh()->load(['details.product', 'payments.paymentMethod', 'distributor', 'service']);
        });
    }

    /**
     * Add a partial payment to an existing purchase.
     */
    public function addPayment(
        Purchase $purchase,
        int $paymentMethodId,
        float $amount,
        string $paymentDate,
        ?int $userId = null,
        ?string $notes = null
    ): PurchasePayment {
        $userId = $userId ?? auth()->id();
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException(__('Nominal pembayaran harus lebih dari 0.'));
        }

        $total = (float) $purchase->total;
        $totalPaid = (float) $purchase->total_paid;
        $remaining = $total - $totalPaid;
        if ($amount > $remaining + 0.02) {
            throw new InvalidArgumentException(
                __('Pembayaran tidak boleh melebihi sisa hutang: Rp :sisa', [
                    'sisa' => number_format($remaining, 0, ',', '.'),
                ])
            );
        }

        return DB::transaction(function () use ($purchase, $paymentMethodId, $amount, $paymentDate, $userId, $notes) {
            $payment = PurchasePayment::create([
                'purchase_id' => $purchase->id,
                'payment_method_id' => $paymentMethodId,
                'amount' => $amount,
                'payment_date' => $paymentDate,
                'notes' => $notes,
                'user_id' => $userId,
            ]);

            $purchase->increment('total_paid', $amount);

            $this->createCashFlowForPayment($purchase, $payment, $userId);

            return $payment->load('paymentMethod');
        });
    }

    private function recordPurchasePayment(
        Purchase $purchase,
        int $paymentMethodId,
        float $amount,
        string $paymentDate,
        ?int $userId,
        ?string $notes
    ): PurchasePayment {
        $payment = PurchasePayment::create([
            'purchase_id' => $purchase->id,
            'payment_method_id' => $paymentMethodId,
            'amount' => $amount,
            'payment_date' => $paymentDate,
            'notes' => $notes,
            'user_id' => $userId,
        ]);

        $this->createCashFlowForPayment($purchase, $payment, $userId);

        return $payment;
    }

    /**
     * Cancel a purchase: mark units as cancel, deactivate products, refund payments via Cash In.
     */
    public function cancelPurchase(Purchase $purchase, ?int $userId = null): void
    {
        $userId = $userId ?? auth()->id();

        if ($purchase->status === Purchase::STATUS_CANCELLED) {
            throw new InvalidArgumentException(__('Pembelian ini sudah dibatalkan.'));
        }

        $locationType = $purchase->warehouse_id ? Stock::LOCATION_WAREHOUSE : Stock::LOCATION_BRANCH;
        $locationId = (int) ($purchase->warehouse_id ?? $purchase->branch_id);

        DB::transaction(function () use ($purchase, $locationType, $locationId, $userId) {
            $purchase->load(['details.product', 'payments.paymentMethod']);

            foreach ($purchase->details as $detail) {
                $product = $detail->product;
                $serialNumbers = $detail->serial_numbers
                    ? array_filter(array_map('trim', explode("\n", $detail->serial_numbers)))
                    : [];

                if (! empty($serialNumbers)) {
                    $units = ProductUnit::where('product_id', $product->id)
                        ->where('location_type', $locationType)
                        ->where('location_id', $locationId)
                        ->whereIn('serial_number', $serialNumbers)
                        ->whereIn('status', [ProductUnit::STATUS_IN_STOCK, ProductUnit::STATUS_KEEP])
                        ->get();

                    ProductUnit::whereIn('id', $units->pluck('id'))->update([
                        'status' => ProductUnit::STATUS_CANCEL,
                    ]);

                    $this->stockMutationService->recalculateStockQuantityIfExists($product->id, $locationType, $locationId);
                } else {
                    $stock = Stock::firstOrCreate(
                        [
                            'product_id' => $product->id,
                            'location_type' => $locationType,
                            'location_id' => $locationId,
                        ],
                        ['quantity' => 0]
                    );
                    $qty = min($detail->quantity, $stock->quantity);
                    if ($qty > 0) {
                        $stock->decrement('quantity', $qty);
                    }
                }

                $product->update(['is_active' => false]);
            }

            $incomeCategory = IncomeCategory::firstOrCreate(
                ['code' => 'RTR'],
                [
                    'name' => 'Retur Pembelian',
                    'description' => 'Pengembalian dana dari pembatalan/retur pembelian',
                    'is_active' => true,
                ]
            );

            foreach ($purchase->payments as $payment) {
                $pmLabel = $payment->paymentMethod?->display_label ?? __('Pembayaran');
                CashFlow::create([
                    'branch_id' => $purchase->branch_id,
                    'warehouse_id' => $purchase->warehouse_id,
                    'type' => CashFlow::TYPE_IN,
                    'amount' => $payment->amount,
                    'description' => __('Retur Pembelian') . ' ' . $purchase->invoice_number . ' - ' . $pmLabel,
                    'reference_type' => CashFlow::REFERENCE_PURCHASE_RETURN,
                    'reference_id' => $purchase->id,
                    'income_category_id' => $incomeCategory->id,
                    'payment_method_id' => $payment->payment_method_id,
                    'transaction_date' => $payment->payment_date,
                    'user_id' => $userId,
                ]);
            }

            $purchase->update(['status' => Purchase::STATUS_CANCELLED]);
        });
    }

    private function createCashFlowForPayment(Purchase $purchase, PurchasePayment $payment, ?int $userId): void
    {
        if ($purchase->isDistribusiUnit()) {
            $expenseCategory = ExpenseCategory::updateOrCreate(
                ['code' => ExpenseCategory::CODE_DISTRIBUSI_BARANG],
                [
                    'name' => 'Distribusi Barang',
                    'description' => __('Kas keluar pembayaran hutang biaya distribusi barang antar lokasi (tidak mempengaruhi laba rugi).'),
                    'is_active' => true,
                    'affects_profit_loss' => false,
                ]
            );
            $descPrefix = __('Distribusi Barang');
        } elseif ($purchase->isSparepartService()) {
            $expenseCategory = ExpenseCategory::firstOrCreate(
                ['code' => 'SP-SVC'],
                [
                    'name' => 'Pembelian Sparepart User (SERVICE)',
                    'description' => 'Pengeluaran pembelian sparepart untuk service pelanggan',
                    'is_active' => true,
                ]
            );
            $descPrefix = 'Pembelian Sparepart User (SERVICE)';
        } else {
            $expenseCategory = ExpenseCategory::firstOrCreate(
                ['code' => 'PEMBELIAN'],
                [
                    'name' => 'Pembelian',
                    'description' => 'Pembelian barang dari distributor',
                    'is_active' => true,
                ]
            );
            $descPrefix = __('Pembelian');
        }

        $branchId = $purchase->branch_id;
        $warehouseId = $purchase->warehouse_id;
        $pmLabel = $payment->paymentMethod?->display_label ?? __('Pembayaran');

        CashFlow::create([
            'branch_id' => $branchId,
            'warehouse_id' => $warehouseId,
            'type' => CashFlow::TYPE_OUT,
            'amount' => $payment->amount,
            'description' => $descPrefix . ' ' . $purchase->invoice_number . ' - ' . $pmLabel,
            'reference_type' => CashFlow::REFERENCE_PURCHASE,
            'reference_id' => $purchase->id,
            'expense_category_id' => $expenseCategory->id,
            'payment_method_id' => $payment->payment_method_id,
            'transaction_date' => $payment->payment_date,
            'user_id' => $userId,
        ]);
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
     * @param  array<int, array{product_id: int, quantity: int, unit_price: float, serial_numbers?: array<int,string>}>  $items
     * @return array{0: array<int, array{product_id:int, quantity:int, unit_price:float, subtotal:float, serial_numbers: array<int,string>}>, 1: float}
     */
    private function buildDetails(array $items): array
    {
        $total = 0.0;
        $details = [];

        foreach ($items as $item) {
            $quantity = (int) ($item['quantity'] ?? 0);
            $unitPrice = round((float) ($item['unit_price'] ?? 0), 2);
            if ($quantity <= 0 || $unitPrice < 0) {
                continue;
            }
            $serialNumbers = $item['serial_numbers'] ?? [];
            if (! is_array($serialNumbers)) {
                $serialNumbers = [];
            }
            $serialNumbers = array_values(array_unique(array_filter(array_map('trim', $serialNumbers))));

            if (! empty($serialNumbers) && count($serialNumbers) !== $quantity) {
                $product = Product::find($item['product_id']);
                throw new InvalidArgumentException(
                    __('Jumlah nomor serial harus sama dengan quantity untuk :sku', [
                        'sku' => $product?->sku ?? $item['product_id'],
                    ])
                );
            }

            $subtotal = round($quantity * $unitPrice, 2);
            $details[] = [
                'product_id' => (int) $item['product_id'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
                'serial_numbers' => $serialNumbers,
            ];
            $total += $subtotal;
        }

        if (empty($details)) {
            throw new InvalidArgumentException(__('Data barang pembelian tidak valid.'));
        }

        return [$details, round($total, 2)];
    }

    private function validateLocation(string $locationType, int $locationId): void
    {
        if ($locationType === Stock::LOCATION_WAREHOUSE) {
            if (! Warehouse::find($locationId)) {
                throw new InvalidArgumentException(__('Gudang tidak valid.'));
            }
        } elseif ($locationType === Stock::LOCATION_BRANCH) {
            if (! Branch::find($locationId)) {
                throw new InvalidArgumentException(__('Cabang tidak valid.'));
            }
        } else {
            throw new InvalidArgumentException(__('Tipe lokasi tidak valid.'));
        }
    }

    private function generateInvoiceNumber(): string
    {
        $prefix = 'PBL-' . date('Ymd') . '-';
        $last = Purchase::where('invoice_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->first();
        $seq = $last ? (int) substr($last->invoice_number, -4) + 1 : 1;

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
