<?php

namespace App\Repositories;

use App\Models\Customer;
use Illuminate\Pagination\LengthAwarePaginator;

class CustomerRepository
{
    public function __construct(
        protected Customer $model
    ) {}

    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->with(['branch', 'warehouse'])->orderBy('name')->orderBy('id');

        $user = $filters['user'] ?? null;
        $locationType = $filters['location_type'] ?? null;
        $locationId = $filters['location_id'] ?? null;

        if ($user && ! $user->isSuperAdminOrAdminPusat()) {
            if ($user->warehouse_id) {
                $query->where('warehouse_id', $user->warehouse_id);
            } elseif ($user->branch_id) {
                $query->where('branch_id', $user->branch_id);
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($locationType && $locationId) {
            if ($locationType === 'cabang') {
                $query->where('branch_id', $locationId);
            } else {
                $query->where('warehouse_id', $locationId);
            }
        }

        if (! empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('phone', 'like', "%{$s}%")
                    ->orWhere('address', 'like', "%{$s}%")
                    ->orWhere('notes', 'like', "%{$s}%");
            });
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function create(array $data): Customer
    {
        if (! array_key_exists('is_active', $data)) {
            $data['is_active'] = true;
        }

        return $this->model->create($data);
    }

    public function update(Customer $customer, array $data): bool
    {
        if (! array_key_exists('is_active', $data)) {
            $data['is_active'] = false;
        }

        return $customer->update($data);
    }

    public function delete(Customer $customer): bool
    {
        return $customer->delete();
    }
}

