<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Role;
use App\Models\Stock;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockMonitoringController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $canFilterLocation = $user->isSuperAdminOrAdminPusat();
        $filterLocked = false;
        $locationType = null;
        $locationId = null;
        $locationLabel = null;

        $query = Stock::query()
            ->with(['product.category'])
            ->where('quantity', '>', 0);

        if (! $canFilterLocation) {
            if ($user->hasAnyRole([Role::ADMIN_CABANG]) && $user->branch_id) {
                $filterLocked = true;
                $locationType = Stock::LOCATION_BRANCH;
                $locationId = (int) $user->branch_id;
                $branch = Branch::find($locationId);
                $locationLabel = __('Cabang') . ': ' . ($branch?->name ?? ('#' . $locationId));
            } elseif ($user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->warehouse_id) {
                $filterLocked = true;
                $locationType = Stock::LOCATION_WAREHOUSE;
                $locationId = (int) $user->warehouse_id;
                $warehouse = Warehouse::find($locationId);
                $locationLabel = __('Gudang') . ': ' . ($warehouse?->name ?? ('#' . $locationId));
            } else {
                abort(403, __('Unauthorized.'));
            }
        } else {
            $locationType = (string) $request->get('location_type', '');
            if ($locationType !== Stock::LOCATION_BRANCH && $locationType !== Stock::LOCATION_WAREHOUSE) {
                $locationType = '';
            }
            if ($locationType === Stock::LOCATION_BRANCH) {
                $locationId = (int) $request->get('location_id', $request->get('branch_id', 0));
            } elseif ($locationType === Stock::LOCATION_WAREHOUSE) {
                $locationId = (int) $request->get('location_id', $request->get('warehouse_id', 0));
            } else {
                $locationId = 0;
            }
            if ($locationType === '') {
                $locationId = 0;
            }
        }

        if ($locationType) {
            $query->where('location_type', $locationType);
        }
        if ($locationId > 0) {
            $query->where('location_id', $locationId);
        }
        if ($request->filled('category_id')) {
            $categoryId = (int) $request->category_id;
            if ($categoryId > 0) {
                $query->whereHas('product', fn ($q) => $q->where('category_id', $categoryId));
            }
        }
        if ($request->filled('product_id')) {
            $query->where('product_id', (int) $request->product_id);
        }
        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->whereHas('product', function ($pq) use ($search) {
                    $pq->where('sku', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('series', 'like', "%{$search}%")
                        ->orWhere('specs', 'like', "%{$search}%");
                });
            });
        }

        $rows = $query->get();
        $products = \App\Models\Product::orderBy('sku')->get(['id', 'sku', 'brand']);
        $categories = \App\Models\Category::orderBy('name')->get(['id', 'name']);
        $branches = Branch::orderBy('name')->get(['id', 'name']);
        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);

        $locationNames = [
            Stock::LOCATION_BRANCH => $branches->pluck('name', 'id')->all(),
            Stock::LOCATION_WAREHOUSE => $warehouses->pluck('name', 'id')->all(),
        ];

        $locationLabelFor = function (string $type, int $id) use ($locationNames): string {
            $prefix = $type === Stock::LOCATION_WAREHOUSE ? __('Gudang') : __('Cabang');
            $name = $locationNames[$type][$id] ?? ('#' . $id);

            return $prefix . ': ' . $name;
        };

        $overallQty = (int) $rows->sum('quantity');
        $totalProducts = (int) $rows->pluck('product_id')->unique()->count();

        $locationSummaries = $rows
            ->groupBy(fn ($row) => $row->location_type . ':' . $row->location_id)
            ->map(function ($items, $key) use ($locationLabelFor) {
                [$type, $id] = explode(':', (string) $key);

                return [
                    'key' => (string) $key,
                    'location_type' => $type,
                    'location_id' => (int) $id,
                    'location_label' => $locationLabelFor($type, (int) $id),
                    'total_qty' => (int) $items->sum('quantity'),
                ];
            })
            ->sortByDesc('total_qty')
            ->values();

        $locationCategorySummaries = $rows
            ->groupBy(fn ($row) => $row->location_type . ':' . $row->location_id)
            ->map(function ($items, $key) use ($locationLabelFor) {
                [$type, $id] = explode(':', (string) $key);

                $categories = $items
                    ->groupBy(function ($row) {
                        $cat = $row->product?->category;
                        return $cat?->id ? 'cat:' . $cat->id : 'cat:none';
                    })
                    ->map(function ($catItems) {
                        $first = $catItems->first();
                        $catName = $first?->product?->category?->name ?? __('Tanpa Kategori');

                        $byType = $catItems->groupBy(function ($row) {
                            $t = strtolower(trim((string) ($row->product?->laptop_type ?? '')));

                            return match ($t) {
                                'baru' => 'baru',
                                'bekas' => 'bekas',
                                default => 'other',
                            };
                        });

                        $typeBreakdown = collect([
                            ['key' => 'baru', 'label' => __('Baru'), 'qty' => (int) ($byType->get('baru')?->sum('quantity') ?? 0)],
                            ['key' => 'bekas', 'label' => __('Bekas'), 'qty' => (int) ($byType->get('bekas')?->sum('quantity') ?? 0)],
                            ['key' => 'other', 'label' => __('Lainnya'), 'qty' => (int) ($byType->get('other')?->sum('quantity') ?? 0)],
                        ])->filter(fn ($row) => $row['qty'] > 0)->values()->all();

                        return [
                            'category_name' => $catName,
                            'qty' => (int) $catItems->sum('quantity'),
                            'type_breakdown' => $typeBreakdown,
                        ];
                    })
                    ->sortByDesc('qty')
                    ->values();

                return [
                    'location_label' => $locationLabelFor($type, (int) $id),
                    'total_qty' => (int) $items->sum('quantity'),
                    'categories' => $categories,
                ];
            })
            ->sortByDesc('total_qty')
            ->values();

        $categorySummaries = $rows
            ->groupBy(function ($row) {
                $cat = $row->product?->category;

                return $cat?->id ? 'cat:' . $cat->id : 'cat:none';
            })
            ->map(function ($items) use ($locationLabelFor) {
                $first = $items->first();
                $category = $first?->product?->category;
                $categoryName = $category?->name ?? __('Tanpa Kategori');

                $productDetails = $items
                    ->groupBy('product_id')
                    ->map(function ($productItems) use ($locationLabelFor) {
                        $firstProduct = $productItems->first()?->product;
                        $productName = trim(implode(' ', array_filter([
                            $firstProduct?->sku,
                            $firstProduct?->brand,
                            $firstProduct?->series,
                        ])));

                        $perLocation = $productItems
                            ->groupBy(fn ($row) => $row->location_type . ':' . $row->location_id)
                            ->map(function ($locItems, $key) use ($locationLabelFor) {
                                [$type, $id] = explode(':', (string) $key);

                                return [
                                    'location_label' => $locationLabelFor($type, (int) $id),
                                    'qty' => (int) $locItems->sum('quantity'),
                                ];
                            })
                            ->sortByDesc('qty')
                            ->values();

                        return [
                            'product_name' => $productName !== '' ? $productName : __('Produk #') . ($firstProduct?->id ?? '-'),
                            'total_qty' => (int) $productItems->sum('quantity'),
                            'per_location' => $perLocation,
                        ];
                    })
                    ->sortByDesc('total_qty')
                    ->values();

                return [
                    'category_name' => $categoryName,
                    'total_qty' => (int) $items->sum('quantity'),
                    'product_count' => (int) $items->pluck('product_id')->unique()->count(),
                    'products' => $productDetails,
                ];
            })
            ->sortByDesc('total_qty')
            ->values();

        return view('stock-monitoring.index', [
            'overallQty' => $overallQty,
            'totalProducts' => $totalProducts,
            'totalCategories' => $categorySummaries->count(),
            'categorySummaries' => $categorySummaries,
            'locationSummaries' => $locationSummaries,
            'locationCategorySummaries' => $locationCategorySummaries,
            'branches' => $branches,
            'warehouses' => $warehouses,
            'canFilterLocation' => $canFilterLocation,
            'filterLocked' => $filterLocked,
            'locationType' => $locationType,
            'locationId' => $locationId,
            'locationLabel' => $locationLabel,
            'products' => $products,
            'categories' => $categories,
        ]);
    }
}
