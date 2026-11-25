<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\User;
use App\Models\Warehouse;

class LocationService
{
    /**
     * Get branches and warehouses available for the user to select.
     * - Super admin: all branches and warehouses
     * - Admin pusat: only assigned branches/warehouses
     * - Other roles: only their single placement
     */
    public static function getLocationOptionsForUser(?User $user): array
    {
        $branches = collect();
        $warehouses = collect();
        $defaultPlacementType = null;
        $defaultBranchId = null;
        $defaultWarehouseId = null;

        if (! $user) {
            return [
                'canChoose' => false,
                'branches' => $branches,
                'warehouses' => $warehouses,
                'defaultPlacementType' => null,
                'defaultBranchId' => null,
                'defaultWarehouseId' => null,
            ];
        }

        if ($user->hasUnrestrictedLocationAccess()) {
            $branches = Branch::query()->orderBy('name')->get(['id', 'name']);
            $warehouses = Warehouse::query()->orderBy('name')->get(['id', 'name']);
            $defaultPlacementType = old('placement_type');
            $defaultBranchId = old('branch_id');
            $defaultWarehouseId = old('warehouse_id');

            return [
                'canChoose' => true,
                'branches' => $branches,
                'warehouses' => $warehouses,
                'defaultPlacementType' => $defaultPlacementType,
                'defaultBranchId' => $defaultBranchId,
                'defaultWarehouseId' => $defaultWarehouseId,
            ];
        }

        if ($user->isAdminPusat()) {
            $branchIds = $user->accessibleBranchIds();
            $warehouseIds = $user->accessibleWarehouseIds();
            $branches = $branchIds === []
                ? collect()
                : Branch::query()->whereIn('id', $branchIds)->orderBy('name')->get(['id', 'name']);
            $warehouses = $warehouseIds === []
                ? collect()
                : Warehouse::query()->whereIn('id', $warehouseIds)->orderBy('name')->get(['id', 'name']);
            $canChoose = $branches->count() + $warehouses->count() > 0;
            $defaultPlacementType = old('placement_type');
            $defaultBranchId = old('branch_id');
            $defaultWarehouseId = old('warehouse_id');
            if (! $defaultPlacementType) {
                if ($branches->isNotEmpty()) {
                    $defaultPlacementType = User::PLACEMENT_CABANG;
                    $defaultBranchId = $defaultBranchId ?: $branches->first()?->id;
                } elseif ($warehouses->isNotEmpty()) {
                    $defaultPlacementType = User::PLACEMENT_GUDANG;
                    $defaultWarehouseId = $defaultWarehouseId ?: $warehouses->first()?->id;
                }
            }

            return [
                'canChoose' => $canChoose,
                'branches' => $branches,
                'warehouses' => $warehouses,
                'defaultPlacementType' => $defaultPlacementType,
                'defaultBranchId' => $defaultBranchId,
                'defaultWarehouseId' => $defaultWarehouseId,
            ];
        }

        if ($user->warehouse_id) {
            $defaultPlacementType = User::PLACEMENT_GUDANG;
            $defaultWarehouseId = $user->warehouse_id;
            $warehouses = Warehouse::where('id', $user->warehouse_id)->get(['id', 'name']);
        } elseif ($user->branch_id) {
            $defaultPlacementType = User::PLACEMENT_CABANG;
            $defaultBranchId = $user->branch_id;
            $branches = Branch::where('id', $user->branch_id)->get(['id', 'name']);
        }

        return [
            'canChoose' => false,
            'branches' => $branches,
            'warehouses' => $warehouses,
            'defaultPlacementType' => $defaultPlacementType,
            'defaultBranchId' => $defaultBranchId,
            'defaultWarehouseId' => $defaultWarehouseId,
        ];
    }

    /**
     * Check if user (restricted role) has a valid location assigned.
     */
    public static function userHasLocation(?User $user): bool
    {
        if (! $user) {
            return false;
        }
        if ($user->hasUnrestrictedLocationAccess()) {
            return true;
        }
        if ($user->isAdminPusat()) {
            return $user->accessibleBranchIds() !== [] || $user->accessibleWarehouseIds() !== [];
        }

        return (bool) ($user->branch_id || $user->warehouse_id);
    }

    /**
     * Resolve placement_type, branch_id, warehouse_id from request for the acting user.
     */
    public static function resolveLocationFromUser(?User $user, array $input): array
    {
        if (! $user) {
            return [
                'placement_type' => null,
                'branch_id' => null,
                'warehouse_id' => null,
            ];
        }

        if ($user->hasUnrestrictedLocationAccess() || $user->isAdminPusat()) {
            $placementType = $input['placement_type'] ?? null;
            if ($placementType === User::PLACEMENT_CABANG) {
                $branchId = isset($input['branch_id']) ? (int) $input['branch_id'] : null;
                if ($user->isAdminPusat() && $branchId && ! $user->canAccessBranch($branchId)) {
                    $branchId = null;
                }

                return [
                    'placement_type' => User::PLACEMENT_CABANG,
                    'branch_id' => $branchId,
                    'warehouse_id' => null,
                ];
            }
            if ($placementType === User::PLACEMENT_GUDANG) {
                $warehouseId = isset($input['warehouse_id']) ? (int) $input['warehouse_id'] : null;
                if ($user->isAdminPusat() && $warehouseId && ! $user->canAccessWarehouse($warehouseId)) {
                    $warehouseId = null;
                }

                return [
                    'placement_type' => User::PLACEMENT_GUDANG,
                    'branch_id' => null,
                    'warehouse_id' => $warehouseId,
                ];
            }
            if ($placementType === 'semua' && $user->hasUnrestrictedLocationAccess()) {
                return [
                    'placement_type' => 'semua',
                    'branch_id' => null,
                    'warehouse_id' => null,
                ];
            }

            return [
                'placement_type' => null,
                'branch_id' => null,
                'warehouse_id' => null,
            ];
        }

        if ($user->warehouse_id) {
            return [
                'placement_type' => User::PLACEMENT_GUDANG,
                'branch_id' => null,
                'warehouse_id' => $user->warehouse_id,
            ];
        }
        if ($user->branch_id) {
            return [
                'placement_type' => User::PLACEMENT_CABANG,
                'branch_id' => $user->branch_id,
                'warehouse_id' => null,
            ];
        }

        return [
            'placement_type' => null,
            'branch_id' => null,
            'warehouse_id' => null,
        ];
    }

    /**
     * Get location filter options for index tables.
     */
    public static function getLocationFilterForUser(?User $user, ?string $requestLocationType = null, ?int $requestLocationId = null): array
    {
        if (! $user) {
            return [
                'filterLocked' => true,
                'locationType' => null,
                'locationId' => null,
                'locationLabel' => null,
                'branches' => collect(),
                'warehouses' => collect(),
            ];
        }

        if ($user->hasUnrestrictedLocationAccess()) {
            $branches = Branch::query()->orderBy('name')->get(['id', 'name']);
            $warehouses = Warehouse::query()->orderBy('name')->get(['id', 'name']);
            $locationType = in_array($requestLocationType, ['cabang', 'branch', 'gudang', 'warehouse'], true) ? $requestLocationType : null;
            $locationId = ($requestLocationId && (int) $requestLocationId > 0) ? (int) $requestLocationId : null;
            $locationLabel = null;
            if ($locationType === User::PLACEMENT_CABANG && $locationId) {
                $locationLabel = $branches->firstWhere('id', $locationId)?->name;
            } elseif ($locationType === User::PLACEMENT_GUDANG && $locationId) {
                $locationLabel = $warehouses->firstWhere('id', $locationId)?->name;
            }

            return [
                'filterLocked' => false,
                'locationType' => $locationType,
                'locationId' => $locationId,
                'locationLabel' => $locationLabel,
                'branches' => $branches,
                'warehouses' => $warehouses,
            ];
        }

        if ($user->isAdminPusat()) {
            $branchIds = $user->accessibleBranchIds();
            $warehouseIds = $user->accessibleWarehouseIds();
            $branches = $branchIds === []
                ? collect()
                : Branch::query()->whereIn('id', $branchIds)->orderBy('name')->get(['id', 'name']);
            $warehouses = $warehouseIds === []
                ? collect()
                : Warehouse::query()->whereIn('id', $warehouseIds)->orderBy('name')->get(['id', 'name']);
            $locationType = in_array($requestLocationType, ['cabang', 'branch', 'gudang', 'warehouse'], true) ? $requestLocationType : null;
            $locationId = ($requestLocationId && (int) $requestLocationId > 0) ? (int) $requestLocationId : null;
            if ($locationType === User::PLACEMENT_CABANG && $locationId && ! $user->canAccessBranch($locationId)) {
                $locationType = null;
                $locationId = null;
            }
            if ($locationType === User::PLACEMENT_GUDANG && $locationId && ! $user->canAccessWarehouse($locationId)) {
                $locationType = null;
                $locationId = null;
            }
            $locationLabel = null;
            if ($locationType === User::PLACEMENT_CABANG && $locationId) {
                $locationLabel = $branches->firstWhere('id', $locationId)?->name;
            } elseif ($locationType === User::PLACEMENT_GUDANG && $locationId) {
                $locationLabel = $warehouses->firstWhere('id', $locationId)?->name;
            }

            return [
                'filterLocked' => ($branches->count() + $warehouses->count()) <= 1,
                'locationType' => $locationType,
                'locationId' => $locationId,
                'locationLabel' => $locationLabel,
                'branches' => $branches,
                'warehouses' => $warehouses,
            ];
        }

        $branches = Branch::query()->orderBy('name')->get(['id', 'name']);
        $warehouses = Warehouse::query()->orderBy('name')->get(['id', 'name']);

        if ($user->warehouse_id) {
            $wh = $warehouses->firstWhere('id', $user->warehouse_id);

            return [
                'filterLocked' => true,
                'locationType' => User::PLACEMENT_GUDANG,
                'locationId' => $user->warehouse_id,
                'locationLabel' => $wh?->name ?? __('Gudang'),
                'branches' => $branches,
                'warehouses' => $warehouses,
            ];
        }
        if ($user->branch_id) {
            $branch = $branches->firstWhere('id', $user->branch_id);

            return [
                'filterLocked' => true,
                'locationType' => User::PLACEMENT_CABANG,
                'locationId' => $user->branch_id,
                'locationLabel' => $branch?->name ?? __('Cabang'),
                'branches' => $branches,
                'warehouses' => $warehouses,
            ];
        }

        return [
            'filterLocked' => true,
            'locationType' => null,
            'locationId' => null,
            'locationLabel' => null,
            'branches' => $branches,
            'warehouses' => $warehouses,
        ];
    }
}
