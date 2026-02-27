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

        if (! $user->isSuperAdmin() && $user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR])) {
            if (! $user->branch_id) {
                abort(403, __('User branch not set.'));
            }
            $branchId = $user->branch_id;
            $query->where(function ($q) use ($branchId) {
                $q->where(function ($q2) use ($branchId) {
                    $q2->where('from_location_type', 'branch')
                        ->where('from_location_id', $branchId);
                })->orWhere(function ($q2) use ($branchId) {
                    $q2->where('to_location_type', 'branch')
                        ->where('to_location_id', $branchId);
                });
            });
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
        $products = Product::orderBy('sku')->get(['id', 'sku', 'brand']);

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

        return view('stock-mutations.index', compact('mutations', 'products', 'warehousesById', 'branchesById'));
    }

    public function create(): View
    {
        $user = auth()->user();
        if (! $user->isSuperAdmin() && ! $user->hasAnyRole([Role::STAFF_GUDANG])) {
            abort(403, __('Unauthorized.'));
        }

        $products = Product::orderBy('sku')->get();
        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);
        $branches = Branch::orderBy('name')->get(['id', 'name']);

        return view('stock-mutations.create', compact('products', 'warehouses', 'branches'));
    }

    public function availableSerials(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdmin() && ! $user->hasAnyRole([Role::STAFF_GUDANG])) {
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
        if (! $user->isSuperAdmin() && ! $user->hasAnyRole([Role::STAFF_GUDANG])) {
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

        return array_values(array_unique($out));
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
            return array_values(array_unique($out));
        }

        if (is_string($input)) {
            // Backward compatibility (old textarea input)
            return $this->parseSerialNumbers($input);
        }

        return [];
    }
}
