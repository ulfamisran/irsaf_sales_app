<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Product;
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
        ?array $serialNumbers = null
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
                $userId
            );
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
                'product_id' => $product->id,
                'from_location_type' => $fromLocationType,
                'from_location_id' => $fromLocationId,
                'to_location_type' => $toLocationType,
                'to_location_id' => $toLocationId,
                'quantity' => $quantity,
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
        ?string $receivedDate = null
    ): Stock {
        $this->validateLocation($locationType, $locationId);

        $serialNumbers = $this->normalizeSerialNumbers($serialNumbers);
        if (! empty($serialNumbers)) {
            return $this->addStockUnits($product, $locationType, $locationId, $serialNumbers, $receivedDate, $userId);
        }

        return DB::transaction(function () use ($product, $locationType, $locationId, $quantity, $userId) {
            // If this product already uses serial-numbered units, require serial numbers.
            $isSerialTracked = ProductUnit::where('product_id', $product->id)->exists();
            if ($isSerialTracked) {
                throw new InvalidArgumentException(__('This product uses serial numbers. Please input serial numbers.'));
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
        ?int $userId = null
    ): StockMutation {
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
            $serialNumbers,
            $mutationDate,
            $notes,
            $userId
        ) {
            $units = ProductUnit::where('product_id', $product->id)
                ->where('location_type', $fromLocationType)
                ->where('location_id', $fromLocationId)
                ->where('status', ProductUnit::STATUS_IN_STOCK)
                ->whereIn('serial_number', $serialNumbers)
                ->lockForUpdate()
                ->get(['id', 'serial_number']);

            if ($units->count() !== count($serialNumbers)) {
                $found = $units->pluck('serial_number')->all();
                $missing = array_values(array_diff($serialNumbers, $found));
                throw new InvalidArgumentException(
                    __('Some serial numbers are not available in source location: :serials', ['serials' => implode(', ', $missing)])
                );
            }

            ProductUnit::whereIn('id', $units->pluck('id')->all())->update([
                'location_type' => $toLocationType,
                'location_id' => $toLocationId,
            ]);

            // Keep quantity cache in sync for involved locations
            $this->recalculateStockQuantity($product->id, $fromLocationType, $fromLocationId);
            $this->recalculateStockQuantity($product->id, $toLocationType, $toLocationId);

            return StockMutation::create([
                'product_id' => $product->id,
                'from_location_type' => $fromLocationType,
                'from_location_id' => $fromLocationId,
                'to_location_type' => $toLocationType,
                'to_location_id' => $toLocationId,
                'quantity' => count($serialNumbers),
                'mutation_date' => $mutationDate,
                'notes' => $notes,
                'serial_numbers' => implode("\n", $serialNumbers),
                'user_id' => $userId,
            ]);
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
        ?int $userId = null
    ): Stock {
        $this->validateLocation($locationType, $locationId);

        $receivedDate = $receivedDate ?: Carbon::now()->toDateString();

        return DB::transaction(function () use ($product, $locationType, $locationId, $serialNumbers, $receivedDate) {
            $exists = ProductUnit::whereIn('serial_number', $serialNumbers)
                ->pluck('serial_number')
                ->all();
            if (! empty($exists)) {
                throw new InvalidArgumentException(
                    __('Serial number already exists: :serials', ['serials' => implode(', ', $exists)])
                );
            }

            foreach ($serialNumbers as $sn) {
                ProductUnit::create([
                    'product_id' => $product->id,
                    'serial_number' => $sn,
                    'location_type' => $locationType,
                    'location_id' => $locationId,
                    'status' => ProductUnit::STATUS_IN_STOCK,
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
}
