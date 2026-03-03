<?php

namespace App\Http\Controllers;

use App\Http\Requests\StockMutationRequest;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Role;
use App\Models\Stock;
use App\Models\StockMutation;
use App\Models\Warehouse;
use App\Services\StockMutationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class StockMutationController extends Controller
{
    public function __construct(
        protected StockMutationService $stockMutationService
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $query = StockMutation::with(['product', 'user'])
            ->orderByDesc('mutation_date')
            ->orderByDesc('id');

        $filterLocked = false;
        $locationType = null;
        $locationId = null;
        $locationLabel = null;

        if (! $user->isSuperAdminOrAdminPusat()) {
            if ($user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR]) && $user->branch_id) {
                $branchId = (int) $user->branch_id;
                $query->where(function ($q) use ($branchId) {
                    $q->where(function ($q2) use ($branchId) {
                        $q2->where('from_location_type', Stock::LOCATION_BRANCH)
                            ->where('from_location_id', $branchId);
                    })->orWhere(function ($q2) use ($branchId) {
                        $q2->where('to_location_type', Stock::LOCATION_BRANCH)
                            ->where('to_location_id', $branchId);
                    });
                });
                $filterLocked = true;
                $branch = Branch::find($branchId);
                $locationType = 'branch';
                $locationId = $branchId;
                $locationLabel = __('Cabang') . ': ' . ($branch?->name ?? '#' . $branchId);
            } elseif ($user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->warehouse_id) {
                $warehouseId = (int) $user->warehouse_id;
                $query->where(function ($q) use ($warehouseId) {
                    $q->where(function ($q2) use ($warehouseId) {
                        $q2->where('from_location_type', Stock::LOCATION_WAREHOUSE)
                            ->where('from_location_id', $warehouseId);
                    })->orWhere(function ($q2) use ($warehouseId) {
                        $q2->where('to_location_type', Stock::LOCATION_WAREHOUSE)
                            ->where('to_location_id', $warehouseId);
                    });
                });
                $filterLocked = true;
                $warehouse = Warehouse::find($warehouseId);
                $locationType = 'warehouse';
                $locationId = $warehouseId;
                $locationLabel = __('Gudang') . ': ' . ($warehouse?->name ?? '#' . $warehouseId);
            } elseif (! $user->branch_id && ! $user->warehouse_id) {
                abort(403, __('User branch or warehouse not set.'));
            }
        }

        if ($user->isSuperAdminOrAdminPusat() && $request->filled('location_type') && $request->filled('location_id')) {
            $locType = (string) $request->location_type;
            $locId = (int) $request->location_id;
            if (in_array($locType, [Stock::LOCATION_WAREHOUSE, Stock::LOCATION_BRANCH]) && $locId > 0) {
                $query->where(function ($q) use ($locType, $locId) {
                    $q->where(function ($q2) use ($locType, $locId) {
                        $q2->where('from_location_type', $locType)->where('from_location_id', $locId);
                    })->orWhere(function ($q2) use ($locType, $locId) {
                        $q2->where('to_location_type', $locType)->where('to_location_id', $locId);
                    });
                });
            }
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('mutation_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('mutation_date', '<=', $request->date_to);
        }

        $mutations = $query->paginate(20)->withQueryString();
        $products = Product::withCount([
            'units as in_stock_count' => fn ($q) => $q->where('status', ProductUnit::STATUS_IN_STOCK),
        ])->having('in_stock_count', '>', 0)->orderBy('sku')->get(['id', 'sku', 'brand']);

        // Resolve location names (avoid N+1 in Blade)
        $items = $mutations->getCollection();
        $warehouseIds = $items->flatMap(function ($m) {
            $ids = [];
            if ($m->from_location_type === Stock::LOCATION_WAREHOUSE) {
                $ids[] = (int) $m->from_location_id;
            }
            if ($m->to_location_type === Stock::LOCATION_WAREHOUSE) {
                $ids[] = (int) $m->to_location_id;
            }
            return $ids;
        })->filter()->unique()->values();

        $branchIds = $items->flatMap(function ($m) {
            $ids = [];
            if ($m->from_location_type === Stock::LOCATION_BRANCH) {
                $ids[] = (int) $m->from_location_id;
            }
            if ($m->to_location_type === Stock::LOCATION_BRANCH) {
                $ids[] = (int) $m->to_location_id;
            }
            return $ids;
        })->filter()->unique()->values();

        $warehousesById = $warehouseIds->isNotEmpty()
            ? Warehouse::query()->whereIn('id', $warehouseIds)->pluck('name', 'id')
            : collect();
        $branchesById = $branchIds->isNotEmpty()
            ? Branch::query()->whereIn('id', $branchIds)->pluck('name', 'id')
            : collect();

        $branches = Branch::orderBy('name')->get(['id', 'name']);
        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);
        $canFilterLocation = $user->isSuperAdminOrAdminPusat();

        return view('stock-mutations.index', compact(
            'mutations',
            'products',
            'warehousesById',
            'branchesById',
            'branches',
            'warehouses',
            'canFilterLocation',
            'filterLocked',
            'locationType',
            'locationId',
            'locationLabel'
        ));
    }

    public function create(): View
    {
        $user = auth()->user();
        if (! $user->isSuperAdminOrAdminPusat() && ! $user->hasAnyRole([Role::ADMIN_GUDANG])) {
            abort(403, __('Unauthorized.'));
        }

        $products = Product::withCount([
            'units as in_stock_count' => fn ($q) => $q->where('status', ProductUnit::STATUS_IN_STOCK),
        ])->having('in_stock_count', '>', 0)->orderBy('sku')->get();

        $productsForDropdown = $products->map(fn ($p) => [
            'id' => $p->id,
            'sku' => $p->sku,
            'brand' => $p->brand,
            'series' => $p->series ?? '',
            'in_stock_count' => $p->in_stock_count ?? 0,
        ])->values();

        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);
        $branches = Branch::orderBy('name')->get(['id', 'name']);

        return view('stock-mutations.create', compact('products', 'productsForDropdown', 'warehouses', 'branches'));
    }

    public function availableSerials(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdminOrAdminPusat() && ! $user->hasAnyRole([Role::ADMIN_GUDANG])) {
            abort(403, __('Unauthorized.'));
        }

        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'from_location_type' => ['required', 'in:'.Stock::LOCATION_WAREHOUSE.','.Stock::LOCATION_BRANCH],
            'from_location_id' => ['required', 'integer', 'min:1'],
        ]);

        $limit = 500;
        $query = ProductUnit::query()
            ->where('product_id', (int) $validated['product_id'])
            ->where('location_type', (string) $validated['from_location_type'])
            ->where('location_id', (int) $validated['from_location_id'])
            ->where('status', ProductUnit::STATUS_IN_STOCK);

        $total = (clone $query)->count();
        $serials = $query
            ->orderBy('id')
            ->limit($limit)
            ->pluck('serial_number')
            ->all();

        return response()->json([
            'serial_numbers' => $serials,
            'total_available' => $total,
            'truncated' => $total > $limit,
        ]);
    }

    public function store(StockMutationRequest $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdminOrAdminPusat() && ! $user->hasAnyRole([Role::ADMIN_GUDANG])) {
            abort(403, __('Unauthorized.'));
        }

        try {
            $product = Product::findOrFail($request->product_id);
            $serialNumbers = $this->normalizeSerialNumbersInput($request->input('serial_numbers'));
            $quantity = ! empty($serialNumbers) ? count($serialNumbers) : (int) $request->quantity;
            $this->stockMutationService->mutate(
                $product,
                $request->from_location_type,
                (int) $request->from_location_id,
                $request->to_location_type,
                (int) $request->to_location_id,
                $quantity,
                $request->mutation_date,
                $request->notes,
                $user->id,
                $serialNumbers
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('stock-mutations.index')->with('success', __('Distribusi stok berhasil dibuat.'));
    }

    /**
     * @return array<int, string>
     */
    private function parseSerialNumbers(?string $input): array
    {
        if (! $input) {
            return [];
        }

        $parts = preg_split('/[\r\n,]+/', $input) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '') {
                continue;
            }
            $out[] = $p;
        }

        return $this->ensureUniqueSerials($out);
    }

    /**
     * @param  mixed  $input
     * @return array<int, string>
     */
    private function normalizeSerialNumbersInput(mixed $input): array
    {
        if (is_array($input)) {
            $out = [];
            foreach ($input as $sn) {
                $sn = trim((string) $sn);
                if ($sn === '') {
                    continue;
                }
                $out[] = $sn;
            }
            return $this->ensureUniqueSerials($out);
        }

        if (is_string($input)) {
            // Backward compatibility (old textarea input)
            return $this->parseSerialNumbers($input);
        }

        return [];
    }

    /**
     * @param  array<int, string>  $serials
     * @return array<int, string>
     */
    private function ensureUniqueSerials(array $serials): array
    {
        $serials = array_values($serials);
        $counts = array_count_values($serials);
        $duplicates = array_keys(array_filter($counts, fn ($count) => $count > 1));
        if (! empty($duplicates)) {
            throw new InvalidArgumentException(
                __('Nomor serial tidak boleh duplikat: :serials', ['serials' => implode(', ', $duplicates)])
            );
        }

        return $serials;
    }
}
