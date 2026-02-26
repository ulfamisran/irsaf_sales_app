<?php

namespace App\Repositories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class BranchRepository
{
    public function __construct(
        protected Branch $model
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
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('pic_name', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function find(int $id): ?Branch
    {
        return $this->model->find($id);
    }

    public function create(array $data): Branch
    {
        return $this->model->create($data);
    }

    public function update(Branch $branch, array $data): bool
    {
        return $branch->update($data);
    }

    public function delete(Branch $branch): bool
    {
        return $branch->delete();
    }
}
