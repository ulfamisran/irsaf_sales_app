<?php

namespace App\Repositories;

use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class PaymentMethodRepository
{
    public function __construct(
        protected PaymentMethod $model
    ) {}

    public function all(?\App\Models\User $user = null): Collection
    {
        $query = $this->model->orderBy('jenis_pembayaran')->orderBy('nama_bank');
        if ($user && ! $user->isSuperAdminOrAdminPusat()) {
            if ($user->warehouse_id) {
                $query->where('warehouse_id', $user->warehouse_id);
            } elseif ($user->branch_id) {
                $query->where('branch_id', $user->branch_id);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return $query->get();
    }

    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->with(['branch', 'warehouse'])
            ->orderBy('jenis_pembayaran')->orderBy('nama_bank')->orderBy('id');

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
                $q->where('jenis_pembayaran', 'like', "%{$s}%")
                    ->orWhere('nama_bank', 'like', "%{$s}%")
                    ->orWhere('atas_nama_bank', 'like', "%{$s}%")
                    ->orWhere('no_rekening', 'like', "%{$s}%")
                    ->orWhere('keterangan', 'like', "%{$s}%");
            });
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function create(array $data): PaymentMethod
    {
        if (! array_key_exists('is_active', $data)) {
            $data['is_active'] = true;
        }
        return $this->model->create($data);
    }

    public function update(PaymentMethod $paymentMethod, array $data): bool
    {
        // When checkbox unchecked, it will be absent -> treat as false on update
        if (! array_key_exists('is_active', $data)) {
            $data['is_active'] = false;
        }
        return $paymentMethod->update($data);
    }

    public function delete(PaymentMethod $paymentMethod): bool
    {
        return $paymentMethod->delete();
    }
}

