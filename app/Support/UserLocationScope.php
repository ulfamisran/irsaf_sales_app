<?php

namespace App\Support;

use App\Models\Stock;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class UserLocationScope
{
    /**
     * Batasi query dokumen yang punya kolom branch_id / warehouse_id.
     */
    public static function constrainBranchWarehouse(
        Builder $query,
        User $user,
        string $branchColumn = 'branch_id',
        string $warehouseColumn = 'warehouse_id'
    ): void {
        if ($user->hasUnrestrictedLocationAccess()) {
            return;
        }

        $branchIds = $user->accessibleBranchIds();
        $warehouseIds = $user->accessibleWarehouseIds();

        $query->where(function (Builder $inner) use ($branchIds, $warehouseIds, $branchColumn, $warehouseColumn) {
            $has = false;
            if ($branchIds !== []) {
                $inner->orWhereIn($branchColumn, $branchIds);
                $has = true;
            }
            if ($warehouseIds !== []) {
                $inner->orWhereIn($warehouseColumn, $warehouseIds);
                $has = true;
            }
            if (! $has) {
                $inner->whereRaw('1 = 0');
            }
        });
    }

    /**
     * Batasi query stok/unit dengan location_type + location_id.
     */
    public static function constrainStockLocation(
        Builder $query,
        User $user,
        string $typeColumn = 'location_type',
        string $idColumn = 'location_id'
    ): void {
        if ($user->hasUnrestrictedLocationAccess()) {
            return;
        }

        $branchIds = $user->accessibleBranchIds();
        $warehouseIds = $user->accessibleWarehouseIds();

        $query->where(function (Builder $inner) use ($branchIds, $warehouseIds, $typeColumn, $idColumn) {
            $has = false;
            if ($branchIds !== []) {
                $inner->orWhere(function (Builder $branch) use ($branchIds, $typeColumn, $idColumn) {
                    $branch->where($typeColumn, Stock::LOCATION_BRANCH)
                        ->whereIn($idColumn, $branchIds);
                });
                $has = true;
            }
            if ($warehouseIds !== []) {
                $inner->orWhere(function (Builder $warehouse) use ($warehouseIds, $typeColumn, $idColumn) {
                    $warehouse->where($typeColumn, Stock::LOCATION_WAREHOUSE)
                        ->whereIn($idColumn, $warehouseIds);
                });
                $has = true;
            }
            if (! $has) {
                $inner->whereRaw('1 = 0');
            }
        });
    }

    public static function assertCanAccessLocation(User $user, ?int $branchId, ?int $warehouseId): void
    {
        if ($user->canAccessLocation($branchId, $warehouseId)) {
            return;
        }

        abort(403, __('Anda tidak memiliki akses ke lokasi ini.'));
    }

    /**
     * Terapkan filter request branch_id/warehouse_id bila valid untuk user.
     *
     * @return array{applied: bool, canFilter: bool, filterLocked: bool, locationLabel: string|null}
     */
    public static function applyOptionalRequestFilter(
        Builder $query,
        User $user,
        ?int $requestBranchId,
        ?int $requestWarehouseId,
        string $branchColumn = 'branch_id',
        string $warehouseColumn = 'warehouse_id'
    ): array {
        self::constrainBranchWarehouse($query, $user, $branchColumn, $warehouseColumn);

        $canFilter = $user->hasUnrestrictedLocationAccess()
            || $user->isAdminPusat()
            || (count($user->accessibleBranchIds()) + count($user->accessibleWarehouseIds()) > 1);
        $filterLocked = ! $canFilter;
        $locationLabel = null;

        if ($requestWarehouseId && $user->canAccessWarehouse($requestWarehouseId)) {
            $query->where($warehouseColumn, $requestWarehouseId);
            $locationLabel = __('Gudang').': #'.$requestWarehouseId;

            return ['applied' => true, 'canFilter' => $canFilter, 'filterLocked' => $filterLocked, 'locationLabel' => $locationLabel];
        }

        if ($requestBranchId && $user->canAccessBranch($requestBranchId)) {
            $query->where($branchColumn, $requestBranchId)->whereNull($warehouseColumn);
            $locationLabel = __('Cabang').': #'.$requestBranchId;

            return ['applied' => true, 'canFilter' => $canFilter, 'filterLocked' => $filterLocked, 'locationLabel' => $locationLabel];
        }

        if (! $user->hasUnrestrictedLocationAccess() && ! $user->isAdminPusat()) {
            $filterLocked = true;
            if ($user->warehouse_id) {
                $locationLabel = __('Gudang').': #'.$user->warehouse_id;
            } elseif ($user->branch_id) {
                $locationLabel = __('Cabang').': #'.$user->branch_id;
            }
        }

        return ['applied' => false, 'canFilter' => $canFilter, 'filterLocked' => $filterLocked, 'locationLabel' => $locationLabel];
    }
}
