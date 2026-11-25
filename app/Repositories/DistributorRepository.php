<?php

namespace App\Repositories;

use App\Models\Distributor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class DistributorRepository
{
    public function __construct(
        protected Distributor $model
    ) {}

    public function all(?\App\Models\User $user = null): Collection
    {
        $query = $this->model->orderBy('name');
        if ($user && ! $user->hasUnrestrictedLocationAccess()) {
            $branchIds = $user->accessibleBranchIds();
            $warehouseIds = $user->accessibleWarehouseIds();
            $query->where(function ($q) use ($branchIds, $warehouseIds) {
                if ($warehouseIds !== []) {
                    $q->orWhereIn('warehouse_id', $warehouseIds);
                }
                if ($branchIds !== []) {
                    $q->orWhereIn('branch_id', $branchIds);
                }
                $q->orWhere(function ($q2) {
                    $q2->whereNull('branch_id')->whereNull('warehouse_id');
                });
                if ($branchIds === [] && $warehouseIds === []) {
                    $q->whereRaw('1 = 0');
                }
            });
        }

        return $query->get();
    }

    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->with(['branch', 'warehouse'])->orderBy('name');

        $user = $filters['user'] ?? null;
        $locationType = $filters['location_type'] ?? null;
        $locationId = $filters['location_id'] ?? null;

        if ($user && ! $user->hasUnrestrictedLocationAccess()) {
            $branchIds = $user->accessibleBranchIds();
            $warehouseIds = $user->accessibleWarehouseIds();
            $query->where(function ($q) use ($branchIds, $warehouseIds) {
                if ($warehouseIds !== []) {
                    $q->orWhereIn('warehouse_id', $warehouseIds);
                }
                if ($branchIds !== []) {
                    $q->orWhereIn('branch_id', $branchIds);
                }
                $q->orWhere(function ($q2) {
                    $q2->whereNull('branch_id')->whereNull('warehouse_id');
                });
                if ($branchIds === [] && $warehouseIds === []) {
                    $q->whereRaw('1 = 0');
                }
            });
            if ($locationType && $locationId) {
                if (in_array($locationType, ['cabang', 'branch'], true) && $user->canAccessBranch((int) $locationId)) {
                    $query->where(function ($q) use ($locationId) {
                        $q->where('branch_id', $locationId)
                            ->orWhere(function ($q2) {
                                $q2->whereNull('branch_id')->whereNull('warehouse_id');
                            });
                    });
                } elseif (in_array($locationType, ['gudang', 'warehouse'], true) && $user->canAccessWarehouse((int) $locationId)) {
                    $query->where(function ($q) use ($locationId) {
                        $q->where('warehouse_id', $locationId)
                            ->orWhere(function ($q2) {
                                $q2->whereNull('branch_id')->whereNull('warehouse_id');
                            });
                    });
                }
            }
        } elseif ($locationType && $locationId) {
            if (in_array($locationType, ['cabang', 'branch'], true)) {
                $query->where(function ($q) use ($locationId) {
                    $q->where('branch_id', $locationId)
                        ->orWhere(function ($q2) {
                            $q2->whereNull('branch_id')->whereNull('warehouse_id');
                        });
                });
            } elseif (in_array($locationType, ['gudang', 'warehouse'], true)) {
                $query->where(function ($q) use ($locationId) {
                    $q->where('warehouse_id', $locationId)
                        ->orWhere(function ($q2) {
                            $q2->whereNull('branch_id')->whereNull('warehouse_id');
                        });
                });
            }
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function find(int $id): ?Distributor
    {
        return $this->model->find($id);
    }

    public function create(array $data): Distributor
    {
        return $this->model->create($data);
    }

    public function update(Distributor $distributor, array $data): bool
    {
        return $distributor->update($data);
    }

    public function delete(Distributor $distributor): bool
    {
        return $distributor->delete();
    }
}
