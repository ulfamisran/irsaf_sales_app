<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Category;
use App\Models\DamagedGood;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Role;
use App\Models\Stock;
use App\Models\Warehouse;
use App\Services\StockMutationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DamagedGoodController extends Controller
{
    public function __construct(
        protected StockMutationService $stockMutationService
    ) {}
    public function index(Request $request): View
    {
        $user = $request->user();
        if (! $user->isSuperAdminOrAdminPusat() && ! $user->hasAnyRole([Role::ADMIN_CABANG])) {
            abort(403, __('Unauthorized.'));
        }

        $query = DamagedGood::with(['productUnit.product.category', 'user'])
            ->whereNull('reactivated_at')
            ->orderByDesc('recorded_date')
            ->orderByDesc('id');

        $canFilterLocation = false;
        $filterLocked = false;
        $locationLabel = null;
        $lockedBranchId = null;
        $lockedWarehouseId = null;

        if (! $user->isSuperAdminOrAdminPusat()) {
            if ($user->hasAnyRole([Role::ADMIN_CABANG]) && $user->branch_id) {
                $query->whereHas('productUnit', fn ($q) => $q
                    ->where('location_type', Stock::LOCATION_BRANCH)
                    ->where('location_id', $user->branch_id));
                $filterLocked = true;
                $lockedBranchId = (int) $user->branch_id;
                $branch = Branch::find($user->branch_id);
                $locationLabel = __('Cabang') . ': ' . ($branch?->name ?? '#' . $user->branch_id);
            } elseif (! $user->branch_id) {
                abort(403, __('User branch not set.'));
            }
        } else {
            $canFilterLocation = true;
            $locType = (string) $request->input('location_type', '');
            $locId = (int) $request->input('location_id', 0);
            if ($locType === Stock::LOCATION_WAREHOUSE && $locId > 0) {
                $query->whereHas('productUnit', fn ($q) => $q
                    ->where('location_type', Stock::LOCATION_WAREHOUSE)
                    ->where('location_id', $locId));
            } elseif ($locType === Stock::LOCATION_BRANCH && $locId > 0) {
                $query->whereHas('productUnit', fn ($q) => $q
                    ->where('location_type', Stock::LOCATION_BRANCH)
                    ->where('location_id', $locId));
            } else {
                // Backward compatibility parameter lama.
                if ($request->filled('warehouse_id')) {
                    $query->whereHas('productUnit', fn ($q) => $q
                        ->where('location_type', Stock::LOCATION_WAREHOUSE)
                        ->where('location_id', $request->warehouse_id));
                } elseif ($request->filled('branch_id')) {
                    $query->whereHas('productUnit', fn ($q) => $q
                        ->where('location_type', Stock::LOCATION_BRANCH)
                        ->where('location_id', $request->branch_id));
                }
            }
        }

        if ($request->filled('category_id')) {
            $categoryId = (int) $request->category_id;
            if ($categoryId > 0) {
                $query->whereHas('productUnit.product', fn ($q) => $q->where('category_id', $categoryId));
            }
        }
        if ($request->filled('product_id')) {
            $productId = (int) $request->product_id;
            if ($productId > 0) {
                $query->whereHas('productUnit', fn ($q) => $q->where('product_id', $productId));
            }
        }
        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('serial_number', 'like', "%{$search}%")
                    ->orWhere('damage_description', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('productUnit.product', function ($pq) use ($search) {
                        $pq->where('sku', 'like', "%{$search}%")
                            ->orWhere('brand', 'like', "%{$search}%")
                            ->orWhere('series', 'like', "%{$search}%");
                    });
            });
        }
        if ($request->filled('date_from')) {
            $query->whereDate('recorded_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('recorded_date', '<=', $request->date_to);
        }

        $damagedGoods = $query->paginate(20)->withQueryString();
        $products = Product::orderBy('sku')->get(['id', 'sku', 'brand']);
        $categories = Category::orderBy('name')->get(['id', 'name']);
        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);
        $branches = Branch::orderBy('name')->get(['id', 'name']);
        $totalHpp = (clone $query)->sum('harga_hpp');

        return view('damaged-goods.index', compact(
            'damagedGoods',
            'products',
            'categories',
            'warehouses',
            'branches',
            'canFilterLocation',
            'filterLocked',
            'locationLabel',
            'lockedBranchId',
            'lockedWarehouseId',
            'totalHpp'
        ));
    }

    public function create(Request $request): View
    {
        $user = $request->user();
        if (! $user->isSuperAdminOrAdminPusat() && ! $user->hasAnyRole([Role::ADMIN_CABANG])) {
            abort(403, __('Unauthorized.'));
        }

        $categories = Category::orderBy('name')->get(['id', 'name']);
        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);
        $branches = Branch::orderBy('name')->get(['id', 'name']);

        $canFilterLocation = ! $user->isSuperAdminOrAdminPusat();
        $filterLocked = false;
        $defaultLocationType = null;
        $defaultLocationId = null;
        $locationLabel = null;

        if (! $user->isSuperAdminOrAdminPusat()) {
            $filterLocked = true;
            if ($user->branch_id) {
                $defaultLocationType = Stock::LOCATION_BRANCH;
                $defaultLocationId = (int) $user->branch_id;
                $br = Branch::find($defaultLocationId);
                $locationLabel = __('Cabang') . ': ' . ($br?->name ?? '#' . $defaultLocationId);
            } else {
                abort(403, __('User branch not set.'));
            }
        } else {
            $defaultLocationType = old('location_type', $request->get('location_type', 'warehouse'));
            $defaultLocationId = old('location_id', $request->get('location_id'));
            if (! $defaultLocationId && $defaultLocationType === 'warehouse') {
                $firstWh = $warehouses->first();
                $defaultLocationId = $firstWh?->id;
            } elseif (! $defaultLocationId && $defaultLocationType === 'branch') {
                $firstBr = $branches->first();
                $defaultLocationId = $firstBr?->id;
            }
        }

        return view('damaged-goods.create', compact(
            'categories',
            'warehouses',
            'branches',
            'canFilterLocation',
            'filterLocked',
            'defaultLocationType',
            'defaultLocationId',
            'locationLabel'
        ));
    }

    public function availableProducts(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdminOrAdminPusat() && ! $user->hasAnyRole([Role::ADMIN_CABANG])) {
            abort(403, __('Unauthorized.'));
        }

        $validated = $request->validate([
            'location_type' => ['required', 'in:'.Stock::LOCATION_WAREHOUSE.','.Stock::LOCATION_BRANCH],
            'location_id' => ['required', 'integer', 'min:1'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        $locType = (string) $validated['location_type'];
        $locId = (int) $validated['location_id'];

        if ($user->hasAnyRole([Role::ADMIN_CABANG]) && $user->branch_id) {
            if ($locType !== Stock::LOCATION_BRANCH || $locId !== (int) $user->branch_id) {
                abort(403, __('Unauthorized.'));
            }
        }
        $categoryId = $validated['category_id'] ?? null;

        $productIds = ProductUnit::query()
            ->where('location_type', $locType)
            ->where('location_id', $locId)
            ->where('status', ProductUnit::STATUS_IN_STOCK)
            ->select('product_id')
            ->distinct()
            ->pluck('product_id');

        $query = Product::query()
            ->with('category')
            ->whereIn('id', $productIds)
            ->where('is_active', true)
            ->withCount([
                'units as in_stock_count' => fn ($q) => $q
                    ->where('location_type', $locType)
                    ->where('location_id', $locId)
                    ->where('status', ProductUnit::STATUS_IN_STOCK),
            ])
            ->orderBy('brand')
            ->orderBy('series')
            ->orderBy('sku');

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $products = $query->limit(500)->get(['id', 'sku', 'brand', 'series', 'category_id']);

        return response()->json([
            'products' => $products->map(fn ($p) => [
                'id' => $p->id,
                'sku' => $p->sku ?? '',
                'brand' => $p->brand ?? '',
                'series' => $p->series ?? '',
                'category_name' => $p->category?->name ?? '',
                'in_stock_count' => $p->in_stock_count ?? 0,
            ])->values(),
        ]);
    }

    public function availableSerials(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdminOrAdminPusat() && ! $user->hasAnyRole([Role::ADMIN_CABANG])) {
            abort(403, __('Unauthorized.'));
        }

        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'location_type' => ['required', 'in:'.Stock::LOCATION_WAREHOUSE.','.Stock::LOCATION_BRANCH],
            'location_id' => ['required', 'integer', 'min:1'],
        ]);

        $locType = (string) $validated['location_type'];
        $locId = (int) $validated['location_id'];
        if ($user->hasAnyRole([Role::ADMIN_CABANG]) && $user->branch_id) {
            if ($locType !== Stock::LOCATION_BRANCH || $locId !== (int) $user->branch_id) {
                abort(403, __('Unauthorized.'));
            }
        }

        $units = ProductUnit::query()
            ->where('product_id', (int) $validated['product_id'])
            ->where('location_type', (string) $validated['location_type'])
            ->where('location_id', (int) $validated['location_id'])
            ->where('status', ProductUnit::STATUS_IN_STOCK)
            ->orderBy('serial_number')
            ->limit(500)
            ->get(['id', 'serial_number', 'harga_hpp', 'harga_jual']);

        return response()->json([
            'units' => $units->map(fn ($u) => [
                'id' => $u->id,
                'serial_number' => $u->serial_number,
                'harga_hpp' => (float) ($u->harga_hpp ?? 0),
                'harga_jual' => (float) ($u->harga_jual ?? 0),
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdminOrAdminPusat() && ! $user->hasAnyRole([Role::ADMIN_CABANG])) {
            abort(403, __('Unauthorized.'));
        }

        $validated = $request->validate([
            'product_unit_ids' => ['required', 'array', 'min:1'],
            'product_unit_ids.*' => ['required', 'integer', 'exists:product_units,id'],
            'recorded_date' => ['required', 'date'],
            'damage_description' => ['required', 'string', 'max:2000'],
        ]);

        $unitIds = array_unique(array_map('intval', $validated['product_unit_ids']));
        $units = ProductUnit::whereIn('id', $unitIds)->get()->keyBy('id');

        foreach ($unitIds as $uid) {
            $unit = $units->get($uid);
            if (! $unit) {
                continue;
            }
            if ($user->hasAnyRole([Role::ADMIN_CABANG]) && $user->branch_id) {
                if ($unit->location_type !== Stock::LOCATION_BRANCH || (int) $unit->location_id !== (int) $user->branch_id) {
                    return back()->withInput()->with('error', __('Unit :serial tidak diizinkan untuk lokasi Anda.', ['serial' => $unit->serial_number]));
                }
            }
            if ($unit->status !== ProductUnit::STATUS_IN_STOCK) {
                return back()->withInput()->with('error', __('Unit :serial sudah tidak tersedia (status: :status).', ['serial' => $unit->serial_number, 'status' => $unit->status]));
            }
            if (DamagedGood::where('product_unit_id', $unit->id)->whereNull('reactivated_at')->exists()) {
                return back()->withInput()->with('error', __('Unit :serial sudah tercatat sebagai barang rusak.', ['serial' => $unit->serial_number]));
            }
        }

        $count = 0;
        DB::transaction(function () use ($units, $unitIds, $validated, $user, &$count) {
            $recalcKeys = [];
            foreach ($unitIds as $uid) {
                $unit = $units->get($uid);
                if (! $unit) {
                    continue;
                }
                DamagedGood::create([
                    'product_unit_id' => $unit->id,
                    'serial_number' => $unit->serial_number,
                    'recorded_date' => $validated['recorded_date'],
                    'damage_description' => $validated['damage_description'],
                    'harga_hpp' => round((float) ($unit->harga_hpp ?? 0), 2),
                    'user_id' => $user->id,
                ]);
                $unit->update(['status' => ProductUnit::STATUS_RESERVED]);
                $recalcKeys[$unit->product_id . '-' . $unit->location_type . '-' . $unit->location_id] = [$unit->product_id, $unit->location_type, $unit->location_id];
                $count++;
            }
            foreach ($recalcKeys as [$productId, $locType, $locId]) {
                $this->stockMutationService->recalculateStockQuantityIfExists($productId, $locType, $locId);
            }
        });

        $msg = $count === 1 ? __('Barang rusak berhasil dicatat.') : __(':count unit berhasil dicatat sebagai barang rusak.', ['count' => $count]);
        return redirect()->route('damaged-goods.index')->with('success', $msg);
    }

    public function reactivateForm(DamagedGood $damagedGood): View
    {
        $user = request()->user();
        if (! $user->isSuperAdminOrAdminPusat() && ! $user->hasAnyRole([Role::ADMIN_CABANG])) {
            abort(403, __('Unauthorized.'));
        }

        if ($user->hasAnyRole([Role::ADMIN_CABANG]) && $user->branch_id) {
            $unit = $damagedGood->productUnit;
            if (! $unit || $unit->location_type !== Stock::LOCATION_BRANCH || (int) $unit->location_id !== (int) $user->branch_id) {
                abort(403, __('Unauthorized.'));
            }
        }

        if ($damagedGood->reactivated_at) {
            return redirect()->route('damaged-goods.index')->with('error', __('Barang ini sudah diaktifkan kembali.'));
        }

        $damagedGood->load(['productUnit.product']);

        return view('damaged-goods.reactivate', compact('damagedGood'));
    }

    public function reactivate(Request $request, DamagedGood $damagedGood): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdminOrAdminPusat() && ! $user->hasAnyRole([Role::ADMIN_CABANG])) {
            abort(403, __('Unauthorized.'));
        }

        if ($user->hasAnyRole([Role::ADMIN_CABANG]) && $user->branch_id) {
            $unitCheck = $damagedGood->productUnit;
            if (! $unitCheck || $unitCheck->location_type !== Stock::LOCATION_BRANCH || (int) $unitCheck->location_id !== (int) $user->branch_id) {
                abort(403, __('Unauthorized.'));
            }
        }

        if ($damagedGood->reactivated_at) {
            return redirect()->route('damaged-goods.index')->with('error', __('Barang ini sudah diaktifkan kembali.'));
        }

        $validated = $request->validate([
            'harga_hpp' => ['required', 'numeric', 'min:0'],
            'harga_jual' => ['required', 'numeric', 'min:0'],
        ]);

        $unit = $damagedGood->productUnit;
        if ($unit->status !== ProductUnit::STATUS_RESERVED) {
            return back()->withInput()->with('error', __('Status unit tidak valid.'));
        }

        DB::transaction(function () use ($damagedGood, $unit, $validated, $user) {
            $unit->update([
                'status' => ProductUnit::STATUS_IN_STOCK,
                'harga_hpp' => round((float) $validated['harga_hpp'], 2),
                'harga_jual' => round((float) $validated['harga_jual'], 2),
            ]);

            $damagedGood->update([
                'reactivated_at' => now(),
                'reactivated_by' => $user->id,
            ]);

            $this->stockMutationService->recalculateStockQuantityIfExists(
                $unit->product_id,
                $unit->location_type,
                $unit->location_id
            );
        });

        return redirect()->route('damaged-goods.index')->with('success', __('Barang berhasil diaktifkan kembali ke stok.'));
    }
}
