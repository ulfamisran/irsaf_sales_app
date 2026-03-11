<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\CashFlow;
use App\Models\Distributor;
use App\Models\IncomeCategory;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\Stock;
use App\Models\StockMutation;
use App\Models\ProductUnit;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StockMutationService
{
    /**
     * Execute stock mutation from one location to another.
     *
     * @param  array<int, string>|null  $serialNumbers
     *
     * @throws InvalidArgumentException
     */
    public function mutate(
        Product $product,
        string $fromLocationType,
        int $fromLocationId,
        string $toLocationType,
        int $toLocationId,
        int $quantity,
        string $mutationDate,
        ?string $notes = null,
        ?int $userId = null,
        ?array $serialNumbers = null,
        float $biayaDistribusiPerUnit = 0,
        array $distributionPayments = []
    ): StockMutation {
        $serialNumbers = $this->normalizeSerialNumbers($serialNumbers);

        if (! empty($serialNumbers)) {
            return $this->mutateBySerialNumbers(
                $product,
                $fromLocationType,
                $fromLocationId,
                $toLocationType,
                $toLocationId,
                $serialNumbers,
                $mutationDate,
                $notes,
                $userId,
                $biayaDistribusiPerUnit,
                $distributionPayments
            );
        }

        if ($biayaDistribusiPerUnit > 0) {
            throw new InvalidArgumentException(__('Distribusi dengan biaya hanya untuk produk berserial. Pilih nomor serial.'));
        }

        if ($quantity <= 0) {
            throw new InvalidArgumentException(__('Quantity must be positive.'));
        }

        if ($fromLocationType === $toLocationType && $fromLocationId === $toLocationId) {
            throw new InvalidArgumentException(__('Source and destination cannot be the same.'));
        }

        $this->validateLocation($fromLocationType, $fromLocationId);
        $this->validateLocation($toLocationType, $toLocationId);

        return DB::transaction(function () use (
            $product,
            $fromLocationType,
            $fromLocationId,
            $toLocationType,
            $toLocationId,
            $quantity,
            $mutationDate,
            $notes,
            $userId,
            $serialNumbers
        ) {
            // If this product/location already uses serial-numbered units, force serial mutation.
            $hasUnitsAtFrom = ProductUnit::where('product_id', $product->id)
                ->where('location_type', $fromLocationType)
                ->where('location_id', $fromLocationId)
                ->exists();
            if ($hasUnitsAtFrom) {
                throw new InvalidArgumentException(__('This stock uses serial numbers. Please mutate by serial number list.'));
            }

            $fromStock = Stock::firstOrCreate(
                [
                    'product_id' => $product->id,
                    'location_type' => $fromLocationType,
                    'location_id' => $fromLocationId,
                ],
                ['quantity' => 0]
            );

            if ($fromStock->quantity < $quantity) {
                throw new InvalidArgumentException(
                    __('Insufficient stock. Available: :quantity', ['quantity' => $fromStock->quantity])
                );
            }

            $toStock = Stock::firstOrCreate(
                [
                    'product_id' => $product->id,
                    'location_type' => $toLocationType,
                    'location_id' => $toLocationId,
                ],
                ['quantity' => 0]
            );

            $fromStock->decrement('quantity', $quantity);
            $toStock->increment('quantity', $quantity);

            return StockMutation::create([
                'invoice_number' => $this->generateDistributionInvoiceNumber(),
                'product_id' => $product->id,
                'from_location_type' => $fromLocationType,
                'from_location_id' => $fromLocationId,
                'to_location_type' => $toLocationType,
                'to_location_id' => $toLocationId,
                'quantity' => $quantity,
                'biaya_distribusi_per_unit' => 0,
                'distribution_payment_method_id' => null,
                'mutation_date' => $mutationDate,
                'notes' => $notes,
                'serial_numbers' => null,
                'user_id' => $userId,
            ]);
        });
    }

    private function validateLocation(string $locationType, int $locationId): void
    {
        if ($locationType === Stock::LOCATION_WAREHOUSE) {
            if (! Warehouse::find($locationId)) {
                throw new InvalidArgumentException(__('Invalid warehouse.'));
            }
        } elseif ($locationType === Stock::LOCATION_BRANCH) {
            if (! Branch::find($locationId)) {
                throw new InvalidArgumentException(__('Invalid branch.'));
            }
        } else {
            throw new InvalidArgumentException(__('Invalid location type.'));
        }
    }

    /**
     * Add stock to a location (e.g., incoming goods).
     */
    public function addStock(
        Product $product,
        string $locationType,
        int $locationId,
        int $quantity,
        ?int $userId = null,
        ?array $serialNumbers = null,
        ?string $receivedDate = null,
        ?float $purchasePrice = null,
        ?float $sellingPrice = null
    ): Stock {
        $this->validateLocation($locationType, $locationId);

        $serialNumbers = $this->normalizeSerialNumbers($serialNumbers);
        if (! empty($serialNumbers)) {
            return $this->addStockUnits($product, $locationType, $locationId, $serialNumbers, $receivedDate, $userId, $purchasePrice, $sellingPrice);
        }

        return DB::transaction(function () use ($product, $locationType, $locationId, $quantity, $userId) {
            // If this product already uses serial-numbered units, require serial numbers.
            $isSerialTracked = ProductUnit::where('product_id', $product->id)->exists();
            if ($isSerialTracked) {
                throw new InvalidArgumentException(__('Produk ini memakai nomor serial. Mohon masukkan nomor serial.'));
            }

            $stock = Stock::firstOrCreate(
                [
                    'product_id' => $product->id,
                    'location_type' => $locationType,
                    'location_id' => $locationId,
                ],
                ['quantity' => 0]
            );

            $stock->increment('quantity', $quantity);

            return $stock->fresh();
        });
    }

    /**
     * Reduce stock from a location (e.g., sales).
     */
    public function reduceStock(
        Product $product,
        string $locationType,
        int $locationId,
        int $quantity
    ): Stock {
        $this->validateLocation($locationType, $locationId);

        return DB::transaction(function () use ($product, $locationType, $locationId, $quantity) {
            if ($quantity <= 0) {
                throw new InvalidArgumentException(__('Quantity must be positive.'));
            }

            // If this location/product is serial-tracked, reduce by marking units as sold.
            $isSerialTrackedAtLocation = ProductUnit::where('product_id', $product->id)
                ->where('location_type', $locationType)
                ->where('location_id', $locationId)
                ->exists();

            if ($isSerialTrackedAtLocation) {
                $unitIds = ProductUnit::where('product_id', $product->id)
                    ->where('location_type', $locationType)
                    ->where('location_id', $locationId)
                    ->where('status', ProductUnit::STATUS_IN_STOCK)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->limit($quantity)
                    ->pluck('id')
                    ->all();

                if (count($unitIds) < $quantity) {
                    throw new InvalidArgumentException(
                        __('Insufficient stock. Available: :quantity', ['quantity' => count($unitIds)])
                    );
                }

                ProductUnit::whereIn('id', $unitIds)->update([
                    'status' => ProductUnit::STATUS_SOLD,
                    'sold_at' => Carbon::now(),
                ]);

                $this->recalculateStockQuantity($product->id, $locationType, $locationId);

                return Stock::firstOrCreate(
                    [
                        'product_id' => $product->id,
                        'location_type' => $locationType,
                        'location_id' => $locationId,
                    ],
                    ['quantity' => 0]
                )->fresh();
            }

            $stock = Stock::firstOrCreate(
                [
                    'product_id' => $product->id,
                    'location_type' => $locationType,
                    'location_id' => $locationId,
                ],
                ['quantity' => 0]
            );

            if ($stock->quantity < $quantity) {
                throw new InvalidArgumentException(
                    __('Insufficient stock. Available: :quantity', ['quantity' => $stock->quantity])
                );
            }

            $stock->decrement('quantity', $quantity);

            return $stock->fresh();
        });
    }

    /**
     * Sell specific serial-numbered units from a location.
     *
     * @param  array<int, string>  $serialNumbers
     */
    public function sellUnits(
        Product $product,
        string $locationType,
        int $locationId,
        array $serialNumbers,
        ?\DateTimeInterface $soldAt = null
    ): void {
        $this->validateLocation($locationType, $locationId);

        $serialNumbers = $this->normalizeSerialNumbers($serialNumbers);
        if (empty($serialNumbers)) {
            throw new InvalidArgumentException(__('Serial numbers are required.'));
        }

        $soldAt = $soldAt ? Carbon::instance($soldAt) : Carbon::now();

        DB::transaction(function () use ($product, $locationType, $locationId, $serialNumbers, $soldAt) {
            $units = ProductUnit::where('product_id', $product->id)
                ->where('location_type', $locationType)
                ->where('location_id', $locationId)
                ->where('status', ProductUnit::STATUS_IN_STOCK)
                ->whereIn('serial_number', $serialNumbers)
                ->lockForUpdate()
                ->get(['id', 'serial_number']);

            if ($units->count() !== count($serialNumbers)) {
                $found = $units->pluck('serial_number')->all();
                $missing = array_values(array_diff($serialNumbers, $found));
                throw new InvalidArgumentException(
                    __('Some serial numbers are not available: :serials', ['serials' => implode(', ', $missing)])
                );
            }

            ProductUnit::whereIn('id', $units->pluck('id')->all())->update([
                'status' => ProductUnit::STATUS_SOLD,
                'sold_at' => $soldAt,
            ]);

            $this->recalculateStockQuantity($product->id, $locationType, $locationId);
        });
    }

    /**
     * Reserve specific units for an OPEN (draft/unpaid) sale.
     * This will mark units from in_stock -> keep, so they are no longer selectable by other sales.
     *
     * @param  array<int, string>  $serialNumbers
     */
    public function reserveUnits(
        Product $product,
        string $locationType,
        int $locationId,
        array $serialNumbers
    ): void {
        $this->validateLocation($locationType, $locationId);

        $serialNumbers = $this->normalizeSerialNumbers($serialNumbers);
        if (empty($serialNumbers)) {
            throw new InvalidArgumentException(__('Serial numbers are required.'));
        }

        DB::transaction(function () use ($product, $locationType, $locationId, $serialNumbers) {
            $units = ProductUnit::where('product_id', $product->id)
                ->where('location_type', $locationType)
                ->where('location_id', $locationId)
                ->whereIn('serial_number', $serialNumbers)
                ->lockForUpdate()
                ->get(['id', 'serial_number', 'status']);

            if ($units->count() !== count($serialNumbers)) {
                $found = $units->pluck('serial_number')->all();
                $missing = array_values(array_diff($serialNumbers, $found));
                throw new InvalidArgumentException(
                    __('Some serial numbers are not found: :serials', ['serials' => implode(', ', $missing)])
                );
            }

            $notAvailable = $units
                ->filter(fn ($u) => $u->status !== ProductUnit::STATUS_IN_STOCK)
                ->pluck('serial_number')
                ->all();
            if (! empty($notAvailable)) {
                throw new InvalidArgumentException(
                    __('Some serial numbers are not available: :serials', ['serials' => implode(', ', $notAvailable)])
                );
            }

            ProductUnit::whereIn('id', $units->pluck('id')->all())->update([
                'status' => ProductUnit::STATUS_KEEP,
            ]);

            $this->recalculateStockQuantity($product->id, $locationType, $locationId);
        });
    }

    /**
     * Release reservations for units (keep -> in_stock).
     *
     * When $strict is false, missing units will be ignored (useful for cleanup on delete).
     *
     * @param  array<int, string>  $serialNumbers
     */
    public function unreserveUnits(
        Product $product,
        string $locationType,
        int $locationId,
        array $serialNumbers,
        bool $strict = true
    ): void {
        $this->validateLocation($locationType, $locationId);

        $serialNumbers = $this->normalizeSerialNumbers($serialNumbers);
        if (empty($serialNumbers)) {
            return;
        }

        DB::transaction(function () use ($product, $locationType, $locationId, $serialNumbers, $strict) {
            $units = ProductUnit::where('product_id', $product->id)
                ->where('location_type', $locationType)
                ->where('location_id', $locationId)
                ->whereIn('serial_number', $serialNumbers)
                ->lockForUpdate()
                ->get(['id', 'serial_number', 'status']);

            if ($units->count() !== count($serialNumbers)) {
                if ($strict) {
                    $found = $units->pluck('serial_number')->all();
                    $missing = array_values(array_diff($serialNumbers, $found));
                    throw new InvalidArgumentException(
                        __('Some serial numbers are not found: :serials', ['serials' => implode(', ', $missing)])
                    );
                }
            }

            $sold = $units
                ->filter(fn ($u) => $u->status === ProductUnit::STATUS_SOLD)
                ->pluck('serial_number')
                ->all();
            if (! empty($sold)) {
                throw new InvalidArgumentException(
                    __('Some serial numbers are already sold: :serials', ['serials' => implode(', ', $sold)])
                );
            }

            $toUpdateIds = $units
                ->filter(fn ($u) => $u->status === ProductUnit::STATUS_KEEP)
                ->pluck('id')
                ->all();
            if (empty($toUpdateIds)) {
                return;
            }

            ProductUnit::whereIn('id', $toUpdateIds)->update([
                'status' => ProductUnit::STATUS_IN_STOCK,
            ]);

            $this->recalculateStockQuantity($product->id, $locationType, $locationId);
        });
    }

    /**
     * @param  array<int, string>  $serialNumbers
     */
    private function mutateBySerialNumbers(
        Product $product,
        string $fromLocationType,
        int $fromLocationId,
        string $toLocationType,
        int $toLocationId,
        array $serialNumbers,
        string $mutationDate,
        ?string $notes = null,
        ?int $userId = null,
        float $biayaDistribusiPerUnit = 0,
        array $distributionPayments = []
    ): StockMutation {
        if ($fromLocationType === $toLocationType && $fromLocationId === $toLocationId) {
            throw new InvalidArgumentException(__('Source and destination cannot be the same.'));
        }

        $this->validateLocation($fromLocationType, $fromLocationId);
        $this->validateLocation($toLocationType, $toLocationId);

        $biayaDistribusiPerUnit = round((float) $biayaDistribusiPerUnit, 2);
        $quantity = count($serialNumbers);

        return DB::transaction(function () use (
            $product,
            $fromLocationType,
            $fromLocationId,
            $toLocationType,
            $toLocationId,
            $serialNumbers,
            $quantity,
            $mutationDate,
            $notes,
            $userId,
            $biayaDistribusiPerUnit,
            $distributionPayments
        ) {
            $units = ProductUnit::where('product_id', $product->id)
                ->where('location_type', $fromLocationType)
                ->where('location_id', $fromLocationId)
                ->where('status', ProductUnit::STATUS_IN_STOCK)
                ->whereIn('serial_number', $serialNumbers)
                ->lockForUpdate()
                ->get(['id', 'serial_number', 'harga_hpp', 'harga_jual']);

            if ($units->count() !== $quantity) {
                $found = $units->pluck('serial_number')->all();
                $missing = array_values(array_diff($serialNumbers, $found));
                throw new InvalidArgumentException(
                    __('Some serial numbers are not available in source location: :serials', ['serials' => implode(', ', $missing)])
                );
            }

            $unitIds = $units->pluck('id')->all();
            $totalPurchaseValue = 0.0;

            foreach ($units as $unit) {
                $oldHpp = (float) ($unit->harga_hpp ?? 0);
                $oldJual = (float) ($unit->harga_jual ?? 0);
                $newHpp = round($oldHpp + $biayaDistribusiPerUnit, 2);
                $newJual = round($oldJual + $biayaDistribusiPerUnit, 2);
                $totalPurchaseValue += $newHpp;

                ProductUnit::where('id', $unit->id)->update([
                    'location_type' => $toLocationType,
                    'location_id' => $toLocationId,
                    'harga_hpp' => $newHpp,
                    'harga_jual' => $newJual,
                ]);
            }

            $stockMutation = StockMutation::create([
                'invoice_number' => $this->generateDistributionInvoiceNumber(),
                'product_id' => $product->id,
                'from_location_type' => $fromLocationType,
                'from_location_id' => $fromLocationId,
                'to_location_type' => $toLocationType,
                'to_location_id' => $toLocationId,
                'quantity' => $quantity,
                'biaya_distribusi_per_unit' => $biayaDistribusiPerUnit,
                'distribution_payment_method_id' => null,
                'mutation_date' => $mutationDate,
                'notes' => $notes,
                'serial_numbers' => implode("\n", $serialNumbers),
                'user_id' => $userId,
            ]);

            $fromBranchId = $fromLocationType === Stock::LOCATION_BRANCH ? $fromLocationId : null;
            $fromWarehouseId = $fromLocationType === Stock::LOCATION_WAREHOUSE ? $fromLocationId : null;
            $toBranchId = $toLocationType === Stock::LOCATION_BRANCH ? $toLocationId : null;
            $toWarehouseId = $toLocationType === Stock::LOCATION_WAREHOUSE ? $toLocationId : null;

            if ($biayaDistribusiPerUnit > 0 && ! empty($distributionPayments)) {
                $incomeCategory = IncomeCategory::firstOrCreate(
                    ['code' => 'DIST-BRG'],
                    [
                        'name' => 'Distribusi Barang',
                        'description' => 'Pemasukan dari biaya distribusi barang antar lokasi',
                        'is_active' => true,
                    ]
                );
                foreach ($distributionPayments as $payment) {
                    $pmId = (int) ($payment['payment_method_id'] ?? 0);
                    $amount = round((float) ($payment['amount'] ?? 0), 2);
                    if ($pmId <= 0 || $amount <= 0) {
                        continue;
                    }
                    $pm = \App\Models\PaymentMethod::find($pmId);
                    if ($pm) {
                        $pmBranch = (int) ($pm->branch_id ?? 0);
                        $pmWarehouse = (int) ($pm->warehouse_id ?? 0);
                        $validPm = ($fromLocationType === Stock::LOCATION_BRANCH && $pmBranch === $fromLocationId)
                            || ($fromLocationType === Stock::LOCATION_WAREHOUSE && $pmWarehouse === $fromLocationId);
                        if (! $validPm) {
                            throw new InvalidArgumentException(__('Metode pembayaran harus dari lokasi asal.'));
                        }
                    }
                    $pmLabel = $pm?->display_label ?? __('Pembayaran');
                    CashFlow::create([
                        'branch_id' => $fromBranchId,
                        'warehouse_id' => $fromWarehouseId,
                        'type' => CashFlow::TYPE_IN,
                        'amount' => $amount,
                        'description' => __('Distribusi Barang') . ' #' . $stockMutation->id . ' - ' . $pmLabel,
                        'reference_type' => CashFlow::REFERENCE_DISTRIBUTION,
                        'reference_id' => $stockMutation->id,
                        'income_category_id' => $incomeCategory->id,
                        'payment_method_id' => $pmId,
                        'transaction_date' => $mutationDate,
                        'user_id' => $userId,
                    ]);
                }
            }

            // Biaya pembelian/utang = jumlah_unit × biaya_distribusi_per_unit (bukan HPP atau harga jual)
            $totalBiayaDistribusi = round($quantity * $biayaDistribusiPerUnit, 2);
            if ($biayaDistribusiPerUnit > 0 && $totalBiayaDistribusi > 0) {
                $distributor = Distributor::firstOrCreate(
                    ['name' => 'Distribusi Internal'],
                    [
                        'placement_type' => Distributor::PLACEMENT_SEMUA,
                        'branch_id' => null,
                        'warehouse_id' => null,
                        'address' => null,
                        'phone' => null,
                    ]
                );
                $invoiceNumber = 'DST-' . date('Ymd') . '-' . str_pad((string) $stockMutation->id, 4, '0', STR_PAD_LEFT);
                $purchase = Purchase::create([
                    'invoice_number' => $invoiceNumber,
                    'jenis_pembelian' => Purchase::JENIS_DISTRIBUSI_UNIT,
                    'distributor_id' => $distributor->id,
                    'location_type' => $toLocationType,
                    'warehouse_id' => $toWarehouseId,
                    'branch_id' => $toBranchId,
                    'purchase_date' => $mutationDate,
                    'total' => $totalBiayaDistribusi,
                    'total_paid' => 0,
                    'description' => __('Pembelian dari distribusi') . ' #' . $stockMutation->id,
                    'termin' => null,
                    'due_date' => null,
                    'user_id' => $userId,
                    'stock_mutation_id' => $stockMutation->id,
                ]);
                PurchaseDetail::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $biayaDistribusiPerUnit,
                    'subtotal' => $totalBiayaDistribusi,
                    'serial_numbers' => implode("\n", $serialNumbers),
                ]);
            }

            $this->recalculateStockQuantity($product->id, $fromLocationType, $fromLocationId);
            $this->recalculateStockQuantity($product->id, $toLocationType, $toLocationId);

            return $stockMutation;
        });
    }

    /**
     * @param  array<int, string>  $serialNumbers
     */
    private function addStockUnits(
        Product $product,
        string $locationType,
        int $locationId,
        array $serialNumbers,
        ?string $receivedDate = null,
        ?int $userId = null,
        ?float $purchasePrice = null,
        ?float $sellingPrice = null
    ): Stock {
        $this->validateLocation($locationType, $locationId);

        $receivedDate = $receivedDate ?: Carbon::now()->toDateString();
        $hpp = $purchasePrice !== null ? round((float) $purchasePrice, 2) : (float) ($product->purchase_price ?? 0);
        $jual = $sellingPrice !== null ? round((float) $sellingPrice, 2) : (float) ($product->selling_price ?? 0);
        $unitStatus = $jual > 0 ? ProductUnit::STATUS_IN_STOCK : ProductUnit::STATUS_INACTIVE;

        return DB::transaction(function () use ($product, $locationType, $locationId, $serialNumbers, $receivedDate, $userId, $hpp, $jual, $unitStatus) {
            $exists = ProductUnit::whereIn('serial_number', $serialNumbers)
                ->pluck('serial_number')
                ->all();
            if (! empty($exists)) {
                throw new InvalidArgumentException(
                    __('Nomor serial sudah terdaftar: :serials', ['serials' => implode(', ', $exists)])
                );
            }

            foreach ($serialNumbers as $sn) {
                ProductUnit::create([
                    'product_id' => $product->id,
                    'user_id' => $userId,
                    'harga_hpp' => $hpp,
                    'harga_jual' => $jual,
                    'serial_number' => $sn,
                    'location_type' => $locationType,
                    'location_id' => $locationId,
                    'status' => $unitStatus,
                    'received_date' => $receivedDate,
                ]);
            }

            $this->recalculateStockQuantity($product->id, $locationType, $locationId);

            return Stock::firstOrCreate(
                [
                    'product_id' => $product->id,
                    'location_type' => $locationType,
                    'location_id' => $locationId,
                ],
                ['quantity' => 0]
            )->fresh();
        });
    }

    public function recalculateStockQuantityIfExists(int $productId, string $locationType, int $locationId): void
    {
        $this->recalculateStockQuantity($productId, $locationType, $locationId);
    }

    private function recalculateStockQuantity(int $productId, string $locationType, int $locationId): void
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
     * @param  array<int, string>|null  $serialNumbers
     * @return array<int, string>
     */
    private function normalizeSerialNumbers(?array $serialNumbers): array
    {
        if (! $serialNumbers) {
            return [];
        }

        $clean = [];
        foreach ($serialNumbers as $sn) {
            $sn = trim((string) $sn);
            if ($sn === '') {
                continue;
            }
            $clean[] = $sn;
        }

        $clean = array_values(array_unique($clean));

        return $clean;
    }

    private function generateDistributionInvoiceNumber(): string
    {
        $prefix = 'DIST-' . date('Ymd') . '-';
        $last = StockMutation::where('invoice_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->first();
        $seq = $last ? (int) substr($last->invoice_number, -4) + 1 : 1;

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
