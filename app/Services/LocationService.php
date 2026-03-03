<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\User;
use App\Models\Warehouse;

class LocationService
{
    /**
     * Get branches and warehouses available for the user to select.
     * - Super admin & Admin pusat: all branches and warehouses
     * - Admin cabang, Admin gudang, Kasir, staff_gudang: only their assigned location
     */
    public static function getLocationOptionsForUser(?User $user): array
    {
        $canChoose = $user && $user->isSuperAdminOrAdminPusat();
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

        if ($canChoose) {
            $branches = Branch::query()->orderBy('name')->get(['id', 'name']);
            $warehouses = Warehouse::query()->orderBy('name')->get(['id', 'name']);
            $defaultPlacementType = old('placement_type');
            $defaultBranchId = old('branch_id');
            $defaultWarehouseId = old('warehouse_id');
        } else {
            if ($user->warehouse_id) {
                $defaultPlacementType = User::PLACEMENT_GUDANG;
                $defaultWarehouseId = $user->warehouse_id;
                $warehouses = Warehouse::where('id', $user->warehouse_id)->get(['id', 'name']);
            } elseif ($user->branch_id) {
                $defaultPlacementType = User::PLACEMENT_CABANG;
                $defaultBranchId = $user->branch_id;
                $branches = Branch::where('id', $user->branch_id)->get(['id', 'name']);
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

    /**
     * Check if user (restricted role) has a valid location assigned.
     */
    public static function userHasLocation(?User $user): bool
    {
        return $user && ($user->branch_id || $user->warehouse_id);
    }

    /**
     * Resolve placement_type, branch_id, warehouse_id from request for non-super-admin users.
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

        if ($user->isSuperAdminOrAdminPusat()) {
            $placementType = $input['placement_type'] ?? null;
            if ($placementType === User::PLACEMENT_CABANG) {
                return [
                    'placement_type' => User::PLACEMENT_CABANG,
                    'branch_id' => $input['branch_id'] ?? null,
                    'warehouse_id' => null,
                ];
            }
            if ($placementType === User::PLACEMENT_GUDANG) {
                return [
                    'placement_type' => User::PLACEMENT_GUDANG,
                    'branch_id' => null,
                    'warehouse_id' => $input['warehouse_id'] ?? null,
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
     * - Super admin & Admin pusat: can choose branch/warehouse filter (filter not locked)
     * - Admin cabang, Kasir (cabang), Admin gudang, Staff gudang: filter locked to their location
     */
    public static function getLocationFilterForUser(?User $user, ?string $requestLocationType = null, ?int $requestLocationId = null): array
    {
        $branches = Branch::query()->orderBy('name')->get(['id', 'name']);
        $warehouses = Warehouse::query()->orderBy('name')->get(['id', 'name']);

        if (! $user) {
            return [
                'filterLocked' => true,
                'locationType' => null,
                'locationId' => null,
                'locationLabel' => null,
                'branches' => $branches,
                'warehouses' => $warehouses,
            ];
        }

        if ($user->isSuperAdminOrAdminPusat()) {
            $locationType = $requestLocationType;
            $locationId = $requestLocationId ? (int) $requestLocationId : null;
            $locationLabel = null;
            if ($locationType === User::PLACEMENT_CABANG && $locationId) {
                $branch = $branches->firstWhere('id', $locationId);
                $locationLabel = $branch?->name ?? null;
            } elseif ($locationType === User::PLACEMENT_GUDANG && $locationId) {
                $wh = $warehouses->firstWhere('id', $locationId);
                $locationLabel = $wh?->name ?? null;
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
