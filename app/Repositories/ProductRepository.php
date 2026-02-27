<?php

namespace App\Repositories;

use App\Models\Product;
use App\Models\Stock;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductRepository
{
    public function __construct(
        protected Product $model
    ) {}

    public function all(): Collection
    {
        return $this->model->with('category')->orderBy('sku')->get();
    }

    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = $this->model
            ->select('products.*')
            ->with('category')
            ->orderBy('sku');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('series', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        // Stok ready = total stok di cabang (ready untuk dijual)
        $query->selectSub(function ($q) use ($filters) {
            $q->from('stocks')
                ->selectRaw('COALESCE(SUM(quantity), 0)')
                ->whereColumn('stocks.product_id', 'products.id')
                ->where('stocks.location_type', Stock::LOCATION_BRANCH)
                ->when(! empty($filters['branch_id']), function ($q2) use ($filters) {
                    $q2->where('stocks.location_id', (int) $filters['branch_id']);
                });
        }, 'ready_stock');

        // Total stok di gudang
        $query->selectSub(function ($q) {
            $q->from('stocks')
                ->selectRaw('COALESCE(SUM(quantity), 0)')
                ->whereColumn('stocks.product_id', 'products.id')
                ->where('stocks.location_type', Stock::LOCATION_WAREHOUSE);
        }, 'warehouse_stock');

        // Total stok di cabang (bisa difilter per cabang)
        $query->selectSub(function ($q) use ($filters) {
            $q->from('stocks')
                ->selectRaw('COALESCE(SUM(quantity), 0)')
                ->whereColumn('stocks.product_id', 'products.id')
                ->where('stocks.location_type', Stock::LOCATION_BRANCH)
                ->when(! empty($filters['branch_id']), function ($q2) use ($filters) {
                    $q2->where('stocks.location_id', (int) $filters['branch_id']);
                });
        }, 'branch_stock');

        return $query->paginate($perPage)->withQueryString();
    }

    public function find(int $id): ?Product
    {
        return $this->model->with('category')->find($id);
    }

    public function findBySku(string $sku): ?Product
    {
        return $this->model->where('sku', $sku)->first();
    }

    public function create(array $data): Product
    {
        return $this->model->create($data);
    }

    public function update(Product $product, array $data): bool
    {
        return $product->update($data);
    }

    public function delete(Product $product): bool
    {
        return $product->delete();
    }
}
