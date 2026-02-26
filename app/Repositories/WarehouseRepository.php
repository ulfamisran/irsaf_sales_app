<?php

namespace App\Repositories;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class WarehouseRepository
{
    public function __construct(
        protected Warehouse $model
    ) {}

    public function all(): Collection
    {
        return $this->model->orderBy('name')->get();
    }

    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->orderBy('name');

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('pic_name', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function find(int $id): ?Warehouse
    {
        return $this->model->find($id);
    }

    public function create(array $data): Warehouse
    {
        return $this->model->create($data);
    }

    public function update(Warehouse $warehouse, array $data): bool
    {
        return $warehouse->update($data);
    }

    public function delete(Warehouse $warehouse): bool
    {
        return $warehouse->delete();
    }
}
