<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Category;
use App\Models\DamagedGood;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Stock;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\LocationService;
use App\Services\StockMutationService;
use App\Support\UserLocationScope;
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
        $this->assertHasLocation($user);

        $query = DamagedGood::with(['productUnit.product.category', 'user'])
            ->whereNull('reactivated_at')
            ->orderByDesc('recorded_date')
            ->orderByDesc('id');

        $canFilterLocation = $user->hasUnrestrictedLocationAccess()
            || $user->isAdminPusat()
            || (count($user->accessibleBranchIds()) + count($user->accessibleWarehouseIds()) > 1);
        $filterLocked = ! $canFilterLocation;
        $locationLabel = null;
        $lockedBranchId = null;
        $lockedWarehouseId = null;

        if (! $user->hasUnrestrictedLocationAccess()) {
            $branchIds = $user->accessibleBranchIds();
            $warehouseIds = $user->accessibleWarehouseIds();
            $query->whereHas('productUnit', function ($q) use ($branchIds, $warehouseIds) {
                $q->where(function ($inner) use ($branchIds, $warehouseIds) {
                    $has = false;
                    if ($branchIds !== []) {
                        $inner->orWhere(function ($b) use ($branchIds) {
                            $b->where('location_type', Stock::LOCATION_BRANCH)
                                ->whereIn('location_id', $branchIds);
                        });
                        $has = true;
                    }
                    if ($warehouseIds !== []) {
                        $inner->orWhere(function ($w) use ($warehouseIds) {
                            $w->where('location_type', Stock::LOCATION_WAREHOUSE)
                                ->whereIn('location_id', $warehouseIds);
                        });
                        $has = true;
                    }
                    if (! $has) {
                        $inner->whereRaw('1 = 0');
                    }
                });
            });
        }

        if ($filterLocked) {
            if ($user->warehouse_id) {
                $lockedWarehouseId = (int) $user->warehouse_id;
                $warehouse = Warehouse::find($lockedWarehouseId);
                $locationLabel = __('Gudang').': '.($warehouse?->name ?? '#'.$lockedWarehouseId);
            } elseif ($user->branch_id) {
                $lockedBranchId = (int) $user->branch_id;
                $branch = Branch::find($lockedBranchId);
                $locationLabel = __('Cabang').': '.($branch?->name ?? '#'.$lockedBranchId);
            }
        } elseif ($canFilterLocation) {
            $locType = (string) $request->input('location_type', '');
            $locId = (int) $request->input('location_id', 0);
            if ($locType === Stock::LOCATION_WAREHOUSE && $locId > 0 && $user->canAccessWarehouse($locId)) {
                $query->whereHas('productUnit', fn ($q) => $q
                    ->where('location_type', Stock::LOCATION_WAREHOUSE)
                    ->where('location_id', $locId));
            } elseif ($locType === Stock::LOCATION_BRANCH && $locId > 0 && $user->canAccessBranch($locId)) {
                $query->whereHas('productUnit', fn ($q) => $q
                    ->where('location_type', Stock::LOCATION_BRANCH)
                    ->where('location_id', $locId));
            } else {
                if ($request->filled('warehouse_id') && $user->canAccessWarehouse((int) $request->warehouse_id)) {
                    $query->whereHas('productUnit', fn ($q) => $q
                        ->where('location_type', Stock::LOCATION_WAREHOUSE)
                        ->where('location_id', $request->warehouse_id));
                } elseif ($request->filled('branch_id') && $user->canAccessBranch((int) $request->branch_id)) {
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
        $locationOptions = LocationService::getLocationOptionsForUser($user);
        $warehouses = $locationOptions['warehouses'];
        $branches = $locationOptions['branches'];
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
        $this->assertHasLocation($user);

        $categories = Category::orderBy('name')->get(['id', 'name']);
        $locationOptions = LocationService::getLocationOptionsForUser($user);
        $warehouses = $locationOptions['warehouses'];
        $branches = $locationOptions['branches'];

        $canFilterLocation = $user->hasUnrestrictedLocationAccess()
            || $user->isAdminPusat()
            || (count($user->accessibleBranchIds()) + count($user->accessibleWarehouseIds()) > 1);
        $filterLocked = ! $canFilterLocation;
        $defaultLocationType = null;
        $defaultLocationId = null;
        $locationLabel = null;

        if ($filterLocked) {
            if ($user->warehouse_id) {
                $defaultLocationType = Stock::LOCATION_WAREHOUSE;
                $defaultLocationId = (int) $user->warehouse_id;
                $wh = Warehouse::find($defaultLocationId);
                $locationLabel = __('Gudang').': '.($wh?->name ?? '#'.$defaultLocationId);
            } elseif ($user->branch_id) {
                $defaultLocationType = Stock::LOCATION_BRANCH;
                $defaultLocationId = (int) $user->branch_id;
                $br = Branch::find($defaultLocationId);
                $locationLabel = __('Cabang').': '.($br?->name ?? '#'.$defaultLocationId);
            }
        } else {
            $defaultLocationType = old('location_type', $request->get('location_type', $warehouses->isNotEmpty() ? 'warehouse' : 'branch'));
            $defaultLocationId = old('location_id', $request->get('location_id'));
            if (! $defaultLocationId && $defaultLocationType === 'warehouse') {
                $defaultLocationId = $warehouses->first()?->id;
            } elseif (! $defaultLocationId && $defaultLocationType === 'branch') {
                $defaultLocationId = $branches->first()?->id;
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
        $validated = $request->validate([
            'location_type' => ['required', 'in:'.Stock::LOCATION_WAREHOUSE.','.Stock::LOCATION_BRANCH],
            'location_id' => ['required', 'integer', 'min:1'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        $locType = (string) $validated['location_type'];
        $locId = (int) $validated['location_id'];
        $this->assertCanAccessStockLocation($user, $locType, $locId);
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
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'location_type' => ['required', 'in:'.Stock::LOCATION_WAREHOUSE.','.Stock::LOCATION_BRANCH],
            'location_id' => ['required', 'integer', 'min:1'],
        ]);

        $locType = (string) $validated['location_type'];
        $locId = (int) $validated['location_id'];
        $this->assertCanAccessStockLocation($user, $locType, $locId);

        $units = ProductUnit::query()
            ->where('product_id', (int) $validated['product_id'])
            ->where('location_type', $locType)
            ->where('location_id', $locId)
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
        $this->assertHasLocation($user);

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
            if (! $this->canAccessUnitLocation($user, $unit)) {
                return back()->withInput()->with('error', __('Unit :serial tidak diizinkan untuk lokasi Anda.', ['serial' => $unit->serial_number]));
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
                $recalcKeys[$unit->product_id.'-'.$unit->location_type.'-'.$unit->location_id] = [$unit->product_id, $unit->location_type, $unit->location_id];
                $count++;
            }
            foreach ($recalcKeys as [$productId, $locType, $locId]) {
                $this->stockMutationService->recalculateStockQuantityIfExists($productId, $locType, $locId);
            }
        });

        $msg = $count === 1 ? __('Barang rusak berhasil dicatat.') : __(':count unit berhasil dicatat sebagai barang rusak.', ['count' => $count]);

        return redirect()->route('damaged-goods.index')->with('success', $msg);
    }

    public function show(DamagedGood $damagedGood): View
    {
        $user = request()->user();
        $this->assertCanAccessDamagedGood($user, $damagedGood);

        $damagedGood->load(['user', 'reactivatedByUser', 'productUnit.product.category', 'productUnit.warehouse', 'productUnit.branch']);

        return view('damaged-goods.show', compact('damagedGood'));
    }

    public function reactivateForm(DamagedGood $damagedGood): View
    {
        $user = request()->user();
        $this->assertCanAccessDamagedGood($user, $damagedGood);

        if ($damagedGood->reactivated_at) {
            return redirect()->route('damaged-goods.index')->with('error', __('Barang ini sudah diaktifkan kembali.'));
        }

        $damagedGood->load(['productUnit.product']);

        return view('damaged-goods.reactivate', compact('damagedGood'));
    }

    public function reactivate(Request $request, DamagedGood $damagedGood): RedirectResponse
    {
        $user = $request->user();
        $this->assertCanAccessDamagedGood($user, $damagedGood);

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

    private function assertHasLocation(User $user): void
    {
        if ($user->hasUnrestrictedLocationAccess()) {
            return;
        }
        if ($user->isAdminPusat()) {
            if (! LocationService::userHasLocation($user)) {
                abort(403, __('Admin Pusat belum didaftarkan ke cabang/gudang mana pun.'));
            }

            return;
        }
        if (! $user->branch_id && ! $user->warehouse_id) {
            abort(403, __('User branch or warehouse not set.'));
        }
    }

    private function assertCanAccessStockLocation(User $user, string $locationType, int $locationId): void
    {
        if ($locationType === Stock::LOCATION_WAREHOUSE) {
            UserLocationScope::assertCanAccessLocation($user, null, $locationId);

            return;
        }

        UserLocationScope::assertCanAccessLocation($user, $locationId, null);
    }

    private function canAccessUnitLocation(User $user, ProductUnit $unit): bool
    {
        if ($unit->location_type === Stock::LOCATION_WAREHOUSE) {
            return $user->canAccessWarehouse((int) $unit->location_id);
        }
        if ($unit->location_type === Stock::LOCATION_BRANCH) {
            return $user->canAccessBranch((int) $unit->location_id);
        }

        return false;
    }

    private function assertCanAccessDamagedGood(User $user, DamagedGood $damagedGood): void
    {
        $unit = $damagedGood->productUnit;
        if (! $unit || ! $this->canAccessUnitLocation($user, $unit)) {
            abort(403, __('Unauthorized.'));
        }
    }
}
