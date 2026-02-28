<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\CashFlow;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Rental;
use App\Models\RentalItem;
use App\Models\RentalPayment;
use App\Models\Stock;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RentalService
{
    /**
     * Create a new rental. DP wajib.
     *
     * @param  array<int, array{product_id:int, serial_number:string, rental_price:float}>  $items
     * @param  array<int, array{payment_method_id:int, amount:float, notes?:string|null}>  $payments
     */
    public function create(
        int $branchId,
        int $warehouseId,
        ?int $customerId,
        string $pickupDate,
        string $returnDate,
        array $items,
        float $taxAmount,
        float $penaltyAmount,
        array $payments,
        ?string $description = null,
        ?int $userId = null
    ): Rental {
        if (empty($items)) {
            throw new InvalidArgumentException(__('Penyewaan wajib berisi item.'));
        }
        if (empty($payments)) {
            throw new InvalidArgumentException(__('Pembayaran DP wajib diisi.'));
        }

        $branch = Branch::findOrFail($branchId);
        $warehouse = Warehouse::findOrFail($warehouseId);

        $days = $this->calculateDays($pickupDate, $returnDate);
        if ($days <= 0) {
            throw new InvalidArgumentException(__('Jumlah hari sewa tidak valid.'));
        }

        [$details, $subTotal] = $this->buildItems($items, $days, $warehouse->id);

        $taxAmount = max(0, round($this->parseMoney($taxAmount), 2));
        $penaltyAmount = max(0, round($this->parseMoney($penaltyAmount), 2));
        $grandTotal = max(0, round($subTotal + $taxAmount + $penaltyAmount, 2));

        $paymentSum = $this->sumPayments($payments);
        if ($paymentSum < 0.01) {
            throw new InvalidArgumentException(__('DP minimal Rp 0,01.'));
        }
        if ($paymentSum > $grandTotal + 0.02) {
            throw new InvalidArgumentException(
                __('Total pembayaran (:sum) tidak boleh melebihi total sewa (:total).', [
                    'sum' => number_format($paymentSum, 2, ',', '.'),
                    'total' => number_format($grandTotal, 2, ',', '.'),
                ])
            );
        }

        return DB::transaction(function () use (
            $branch,
            $warehouse,
            $customerId,
            $pickupDate,
            $returnDate,
            $days,
            $details,
            $subTotal,
            $taxAmount,
            $penaltyAmount,
            $grandTotal,
            $payments,
            $paymentSum,
            $description,
            $userId
        ) {
            $invoiceNumber = $this->generateInvoiceNumber();
            $isPaidOff = $paymentSum >= $grandTotal - 0.02;

            $rental = Rental::create([
                'invoice_number' => $invoiceNumber,
                'branch_id' => $branch->id,
                'warehouse_id' => $warehouse->id,
                'customer_id' => $customerId,
                'user_id' => $userId ?? auth()->id(),
                'pickup_date' => $pickupDate,
                'return_date' => $returnDate,
                'total_days' => $days,
                'subtotal' => $subTotal,
                'tax_amount' => $taxAmount,
                'penalty_amount' => $penaltyAmount,
                'total' => $grandTotal,
                'total_paid' => $paymentSum,
                'payment_status' => $isPaidOff ? Rental::PAYMENT_LUNAS : Rental::PAYMENT_BELUM_LUNAS,
                'return_status' => Rental::RETURN_BELUM,
                'status' => Rental::STATUS_OPEN,
                'description' => $description,
            ]);

            foreach ($details as $detail) {
                RentalItem::create([
                    'rental_id' => $rental->id,
                    'product_id' => $detail['product_id'],
                    'serial_number' => $detail['serial_number'],
                    'rental_price' => $detail['rental_price'],
                    'days' => $detail['days'],
                    'total' => $detail['total'],
                ]);

                $this->markUnitRented(
                    $detail['product_id'],
                    $warehouse->id,
                    $detail['serial_number']
                );
            }

            foreach ($payments as $p) {
                $amt = round($this->parseMoney($p['amount'] ?? 0), 2);
                if ($amt <= 0) {
                    continue;
                }
                RentalPayment::create([
                    'rental_id' => $rental->id,
                    'payment_method_id' => (int) $p['payment_method_id'],
                    'amount' => $amt,
                    'notes' => $p['notes'] ?? null,
                ]);
            }

            foreach (RentalPayment::with('paymentMethod')->where('rental_id', $rental->id)->get() as $rp) {
                $pm = $rp->paymentMethod;
                $pmLabel = $pm ? $pm->display_label : __('Payment');

                CashFlow::create([
                    'branch_id' => null,
                    'warehouse_id' => $warehouse->id,
                    'type' => CashFlow::TYPE_IN,
                    'amount' => $rp->amount,
                    'description' => __('Sewa') . ' ' . $rental->invoice_number . ' - ' . $pmLabel,
                    'reference_type' => CashFlow::REFERENCE_RENTAL,
                    'reference_id' => $rental->id,
                    'payment_method_id' => $rp->payment_method_id,
                    'transaction_date' => $pickupDate,
                    'user_id' => $userId ?? auth()->id(),
                ]);
            }

            return $rental->fresh()->load(['items.product', 'payments.paymentMethod', 'branch', 'warehouse', 'customer', 'user']);
        });
    }

    /**
     * Add additional payment (pelunasan).
     *
     * @param  array<int, array{payment_method_id:int, amount:float, notes?:string|null}>  $payments
     */
    public function addPayment(
        Rental $rental,
        array $payments,
        ?int $userId = null
    ): Rental {
        if ($rental->status !== Rental::STATUS_OPEN) {
            throw new InvalidArgumentException(__('Penyewaan sudah selesai atau dibatalkan.'));
        }
        if (empty($payments)) {
            throw new InvalidArgumentException(__('Pembayaran wajib diisi.'));
        }
        $newSum = $this->sumPayments($payments);
        if ($newSum < 0.01) {
            throw new InvalidArgumentException(__('Nominal pembayaran minimal Rp 0,01.'));
        }

        $totalPrice = (float) $rental->total;
        $currentPaid = (float) $rental->total_paid;
        $totalPaid = $currentPaid + $newSum;
        if ($totalPaid > $totalPrice + 0.02) {
            throw new InvalidArgumentException(__('Total pembayaran tidak boleh melebihi total sewa.'));
        }

        $branch = Branch::findOrFail($rental->branch_id);
        $warehouse = \App\Models\Warehouse::findOrFail($rental->warehouse_id);

        return DB::transaction(function () use ($rental, $payments, $branch, $warehouse, $totalPaid, $totalPrice, $userId) {
            foreach ($payments as $p) {
                $amt = round($this->parseMoney($p['amount'] ?? 0), 2);
                if ($amt <= 0) {
                    continue;
                }
                RentalPayment::create([
                    'rental_id' => $rental->id,
                    'payment_method_id' => (int) $p['payment_method_id'],
                    'amount' => $amt,
                    'notes' => $p['notes'] ?? null,
                ]);

                $pm = \App\Models\PaymentMethod::find($p['payment_method_id'] ?? 0);
                $pmLabel = $pm ? $pm->display_label : __('Payment');

                CashFlow::create([
                    'branch_id' => null,
                    'warehouse_id' => $warehouse->id,
                    'type' => CashFlow::TYPE_IN,
                    'amount' => $amt,
                    'description' => __('Sewa') . ' ' . $rental->invoice_number . ' - ' . $pmLabel,
                    'reference_type' => CashFlow::REFERENCE_RENTAL,
                    'reference_id' => $rental->id,
                    'payment_method_id' => (int) ($p['payment_method_id'] ?? 0),
                    'transaction_date' => $rental->pickup_date->toDateString(),
                    'user_id' => $userId ?? auth()->id(),
                ]);
            }

            $rental->update([
                'total_paid' => $totalPaid,
                'payment_status' => $totalPaid >= $totalPrice - 0.02 ? Rental::PAYMENT_LUNAS : Rental::PAYMENT_BELUM_LUNAS,
            ]);

            return $rental->fresh()->load(['items.product', 'payments.paymentMethod', 'branch', 'warehouse', 'customer', 'user']);
        });
    }

    /**
     * Mark rental as returned and return units to warehouse stock.
     * Pelunasan wajib jika belum lunas.
     */
    public function markReturned(Rental $rental, array $payments = [], ?int $userId = null): Rental
    {
        if ($rental->status !== Rental::STATUS_OPEN) {
            throw new InvalidArgumentException(__('Hanya penyewaan OPEN yang bisa diselesaikan.'));
        }

        $branch = Branch::findOrFail($rental->branch_id);
        $warehouse = \App\Models\Warehouse::findOrFail($rental->warehouse_id);

        return DB::transaction(function () use ($rental, $payments, $branch, $warehouse, $userId) {
            if (! empty($payments)) {
                foreach ($payments as $p) {
                    $amt = round($this->parseMoney($p['amount'] ?? 0), 2);
                    if ($amt <= 0) {
                        continue;
                    }
                    RentalPayment::create([
                        'rental_id' => $rental->id,
                        'payment_method_id' => (int) $p['payment_method_id'],
                        'amount' => $amt,
                        'notes' => $p['notes'] ?? null,
                    ]);

                    $pm = \App\Models\PaymentMethod::find($p['payment_method_id'] ?? 0);
                    $pmLabel = $pm ? $pm->display_label : __('Payment');

                    CashFlow::create([
                        'branch_id' => null,
                        'warehouse_id' => $warehouse->id,
                        'type' => CashFlow::TYPE_IN,
                        'amount' => $amt,
                        'description' => __('Sewa') . ' ' . $rental->invoice_number . ' - ' . $pmLabel,
                        'reference_type' => CashFlow::REFERENCE_RENTAL,
                        'reference_id' => $rental->id,
                        'payment_method_id' => (int) ($p['payment_method_id'] ?? 0),
                        'transaction_date' => $rental->pickup_date->toDateString(),
                        'user_id' => $userId ?? auth()->id(),
                    ]);
                }
            }

            $totalPaid = (float) ($rental->total_paid ?? 0) + $this->sumPayments($payments);
            if ($totalPaid < (float) $rental->total - 0.02) {
                throw new InvalidArgumentException(__('Pelunasan wajib sebelum pengembalian.'));
            }

            $items = RentalItem::where('rental_id', $rental->id)->get();
            foreach ($items as $item) {
                $this->markUnitReturned((int) $item->product_id, (int) $rental->warehouse_id, (string) $item->serial_number);
            }

            $rental->update([
                'total_paid' => $totalPaid,
                'payment_status' => Rental::PAYMENT_LUNAS,
                'return_status' => Rental::RETURN_SUDAH,
                'status' => Rental::STATUS_RELEASED,
            ]);

            return $rental->fresh()->load(['items.product', 'payments.paymentMethod', 'branch', 'warehouse', 'customer', 'user']);
        });
    }

    private function calculateDays(string $pickupDate, string $returnDate): int
    {
        $start = \Carbon\Carbon::parse($pickupDate)->startOfDay();
        $end = \Carbon\Carbon::parse($returnDate)->startOfDay();
        $days = $start->diffInDays($end) + 1;

        return max(1, $days);
    }

    /**
     * @param  array<int, array{product_id:int, serial_number:string, rental_price:float}>  $items
     * @return array{0: array<int, array{product_id:int, serial_number:string, rental_price:float, days:int, total:float}>, 1: float}
     */
    private function buildItems(array $items, int $days, int $warehouseId): array
    {
        $details = [];
        $subTotal = 0.0;
        $usedSerials = [];

        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $serial = trim((string) ($item['serial_number'] ?? ''));
            $price = $this->parseMoney($item['rental_price'] ?? 0);
            if ($productId <= 0 || $serial === '' || $price <= 0) {
                continue;
            }
            if (in_array($serial, $usedSerials, true)) {
                throw new InvalidArgumentException(__('Serial tidak boleh duplikat: :serial', ['serial' => $serial]));
            }

            $this->validateRentableUnit($productId, $warehouseId, $serial);

            $lineTotal = round($price * $days, 2);
            $details[] = [
                'product_id' => $productId,
                'serial_number' => $serial,
                'rental_price' => round($price, 2),
                'days' => $days,
                'total' => $lineTotal,
            ];
            $subTotal += $lineTotal;
            $usedSerials[] = $serial;
        }

        if (empty($details)) {
            throw new InvalidArgumentException(__('Item sewa tidak valid.'));
        }

        return [$details, round($subTotal, 2)];
    }

    private function validateRentableUnit(int $productId, int $warehouseId, string $serial): void
    {
        $product = Product::with('category')->find($productId);
        if (! $product) {
            throw new InvalidArgumentException(__('Produk tidak ditemukan.'));
        }

        $categoryName = strtoupper((string) ($product->category?->name ?? ''));
        $categoryCode = strtoupper((string) ($product->category?->code ?? ''));
        $isLaptopCategory = $categoryCode === 'LAP' || str_contains($categoryName, 'LAPTOP');
        if (! $isLaptopCategory) {
            throw new InvalidArgumentException(__('Hanya produk kategori Laptop yang bisa disewakan.'));
        }
        if ($product->laptop_type !== 'bekas') {
            throw new InvalidArgumentException(__('Hanya laptop bekas yang bisa disewakan.'));
        }

        $unit = ProductUnit::where('product_id', $productId)
            ->where('serial_number', $serial)
            ->where('location_type', Stock::LOCATION_WAREHOUSE)
            ->where('location_id', $warehouseId)
            ->first();

        if (! $unit) {
            throw new InvalidArgumentException(__('Serial tidak ditemukan di gudang.'));
        }
        if ($unit->status !== ProductUnit::STATUS_IN_STOCK) {
            throw new InvalidArgumentException(__('Serial tidak tersedia untuk disewa.'));
        }
    }

    private function markUnitRented(int $productId, int $warehouseId, string $serial): void
    {
        $unit = ProductUnit::where('product_id', $productId)
            ->where('serial_number', $serial)
            ->where('location_type', Stock::LOCATION_WAREHOUSE)
            ->where('location_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        if (! $unit) {
            throw new InvalidArgumentException(__('Serial tidak ditemukan di gudang.'));
        }
        if ($unit->status !== ProductUnit::STATUS_IN_STOCK) {
            throw new InvalidArgumentException(__('Serial tidak tersedia untuk disewa.'));
        }

        $unit->update(['status' => ProductUnit::STATUS_IN_RENT]);

        $this->recalculateWarehouseStock($productId, $warehouseId);
    }

    private function markUnitReturned(int $productId, int $warehouseId, string $serial): void
    {
        $unit = ProductUnit::where('product_id', $productId)
            ->where('serial_number', $serial)
            ->where('location_type', Stock::LOCATION_WAREHOUSE)
            ->where('location_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        if (! $unit) {
            return;
        }
        if ($unit->status !== ProductUnit::STATUS_IN_RENT) {
            return;
        }

        $unit->update(['status' => ProductUnit::STATUS_IN_STOCK]);
        $this->recalculateWarehouseStock($productId, $warehouseId);
    }

    private function recalculateWarehouseStock(int $productId, int $warehouseId): void
    {
        $qty = ProductUnit::where('product_id', $productId)
            ->where('location_type', Stock::LOCATION_WAREHOUSE)
            ->where('location_id', $warehouseId)
            ->where('status', ProductUnit::STATUS_IN_STOCK)
            ->count();

        Stock::updateOrCreate(
            [
                'product_id' => $productId,
                'location_type' => Stock::LOCATION_WAREHOUSE,
                'location_id' => $warehouseId,
            ],
            ['quantity' => $qty]
        );
    }

    /**
     * @param  array<int, array{amount?: float}>  $payments
     */
    private function sumPayments(array $payments): float
    {
        $sum = 0.0;
        foreach ($payments as $p) {
            $amt = $this->parseMoney($p['amount'] ?? 0);
            if ($amt > 0) {
                $sum += $amt;
            }
        }

        return round($sum, 2);
    }

    private function parseMoney(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $str = trim((string) $value);
        if ($str === '') {
            return 0.0;
        }

        // Treat plain decimals like 1500.25 as numeric
        if (preg_match('/^\d+\.\d{1,2}$/', $str)) {
            return (float) $str;
        }

        // Otherwise strip all non-digits (handles 150.000 / 150.000,00)
        $raw = preg_replace('/[^\d]/', '', $str);
        if ($raw === '' || $raw === null) {
            return 0.0;
        }

        return (float) $raw;
    }

    private function generateInvoiceNumber(): string
    {
        $prefix = 'RNT-' . date('Ymd') . '-';
        $last = Rental::where('invoice_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->first();
        $seq = $last ? (int) substr($last->invoice_number, -4) + 1 : 1;

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
