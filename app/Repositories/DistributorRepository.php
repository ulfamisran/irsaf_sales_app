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

    public function all(): Collection
    {
        return $this->model->orderBy('name')->get();
    }

    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->orderBy('name');

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
