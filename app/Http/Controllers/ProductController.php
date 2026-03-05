<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Role;
use App\Models\Stock;
use App\Models\Warehouse;
use App\Models\AuditLog;
use App\Repositories\CategoryRepository;
use App\Repositories\DistributorRepository;
use App\Repositories\ProductRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(
        protected ProductRepository $productRepository,
        protected CategoryRepository $categoryRepository,
        protected DistributorRepository $distributorRepository
    ) {}

    /**
     * Display a listing of products.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);
        $branches = Branch::orderBy('name')->get(['id', 'name']);

        $defaultLocationType = null;
        $defaultLocationId = null;
        if ($user) {
            if ($user->warehouse_id) {
                $defaultLocationType = Product::LOCATION_WAREHOUSE;
                $defaultLocationId = $user->warehouse_id;
            } elseif ($user->branch_id) {
                $defaultLocationType = Product::LOCATION_BRANCH;
                $defaultLocationId = $user->branch_id;
            }
        }

        $locationType = $request->has('location_type') ? $request->get('location_type') : $defaultLocationType;
        $locationId = $request->has('location_id') ? $request->get('location_id') : $defaultLocationId;
        if ($locationType && $locationId) {
            $locationId = (int) $locationId;
        } else {
            $locationType = null;
            $locationId = null;
        }

        $filters = [
            'search' => $request->get('search'),
            'category_id' => $request->get('category_id'),
            'location_type' => $locationType,
            'location_id' => $locationId,
        ];
        if ($locationType === Product::LOCATION_BRANCH && $locationId) {
            $filters['branch_id'] = $locationId;
        }

        $products = $this->productRepository->paginate(15, $filters);
        $categories = $this->categoryRepository->all();

        return view('products.index', compact('products', 'categories', 'warehouses', 'branches', 'locationType', 'locationId', 'defaultLocationType', 'defaultLocationId'));
    }

    /**
     * Show the form for creating a new product.
     */
    public function create(Request $request): View
    {
        $user = $request->user();
        if ($user && $user->hasAnyRole([Role::ADMIN_GUDANG]) && ! $user->branch_id && ! $user->warehouse_id) {
            abort(403, __('Staff gudang harus memiliki cabang atau gudang yang ditetapkan untuk menambah produk.'));
        }
        $categories = $this->categoryRepository->all();
        $distributors = $this->distributorRepository->all($request->user());
        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);
        $branches = Branch::orderBy('name')->get(['id', 'name']);

        $defaultLocationType = null;
        $defaultLocationId = null;
        if ($user) {
            if ($user->warehouse_id) {
                $defaultLocationType = Product::LOCATION_WAREHOUSE;
                $defaultLocationId = $user->warehouse_id;
            } elseif ($user->branch_id) {
                $defaultLocationType = Product::LOCATION_BRANCH;
                $defaultLocationId = $user->branch_id;
            }
        }

        return view('products.create', compact('categories', 'distributors', 'warehouses', 'branches', 'defaultLocationType', 'defaultLocationId'));
    }

    /**
     * Store a newly created product.
     */
    public function store(ProductRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()?->id;

        $user = $request->user();
        if (isset($data['location_type']) && isset($data['location_id']) && $data['location_type'] && $data['location_id']) {
            $data['location_type'] = $data['location_type'] === 'branch' ? Product::LOCATION_BRANCH : Product::LOCATION_WAREHOUSE;
            $data['location_id'] = (int) $data['location_id'];
        } else {
            $data['location_type'] = null;
            $data['location_id'] = null;
        }

        $sku = trim((string) $request->input('sku', ''));
        if ($sku !== '' && ! Product::where('sku', $sku)->exists()) {
            $data['sku'] = $sku;
        } else {
            $data['sku'] = Product::generateSku($data);
        }
        $product = $this->productRepository->create($data);
        $userId = $request->user()?->id;
        AuditLog::create([
            'user_id' => $userId,
            'action' => 'product.create',
            'reference_type' => 'product',
            'reference_id' => $product?->id,
            'description' => 'Create product ' . ($product?->sku ?? ''),
        ]);
        return redirect()->route('products.index')->with('success', __('Product created successfully.'));
    }

    /**
     * Show the form for editing the specified product.
     * Edit hanya diperbolehkan jika belum ada unit yang terjual.
     */
    public function edit(Request $request, Product $product): View
    {
        $user = $request->user();
        if ($product->hasSoldUnits()) {
            abort(403, __('Produk tidak dapat diedit karena sudah ada unit yang terjual.'));
        }
        if ($user && $user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->branch_id) {
            if ($product->location_type !== Product::LOCATION_BRANCH || (int) $product->location_id !== (int) $user->branch_id) {
                abort(403, __('Anda tidak dapat mengakses produk cabang lain.'));
            }
        }
        $categories = $this->categoryRepository->all();
        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);
        $branches = Branch::orderBy('name')->get(['id', 'name']);
        $defaultLocationType = in_array($product->location_type, ['warehouse', 'branch', 'gudang', 'cabang'])
            ? (in_array($product->location_type, ['warehouse', 'gudang']) ? Product::LOCATION_WAREHOUSE : Product::LOCATION_BRANCH)
            : ($user?->warehouse_id ? Product::LOCATION_WAREHOUSE : ($user?->branch_id ? Product::LOCATION_BRANCH : 'warehouse'));
        $defaultLocationId = $product->location_id ?: $user?->warehouse_id ?: $user?->branch_id;
        return view('products.edit', compact('product', 'categories', 'warehouses', 'branches', 'defaultLocationType', 'defaultLocationId'));
    }

    /**
     * Update the specified product.
     * Update hanya diperbolehkan jika belum ada unit yang terjual.
     */
    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $user = $request->user();
        if ($product->hasSoldUnits()) {
            abort(403, __('Produk tidak dapat diedit karena sudah ada unit yang terjual.'));
        }
        if ($user && $user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->branch_id) {
            if ($product->location_type !== Product::LOCATION_BRANCH || (int) $product->location_id !== (int) $user->branch_id) {
                abort(403, __('Anda tidak dapat mengakses produk cabang lain.'));
            }
        }

        $data = $request->validated();
        if ($user && $user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->branch_id) {
            $data['location_type'] = Product::LOCATION_BRANCH;
            $data['location_id'] = $user->branch_id;
        } elseif (!empty($data['location_type']) && !empty($data['location_id'])) {
            $data['location_type'] = $data['location_type'] === 'branch' ? Product::LOCATION_BRANCH : Product::LOCATION_WAREHOUSE;
            $data['location_id'] = (int) $data['location_id'];
        }
        $sku = trim((string) $request->input('sku', ''));
        if ($sku !== '' && $sku !== $product->sku) {
            $request->validate([
                'sku' => ['unique:products,sku,' . $product->id],
            ]);
            $data['sku'] = $sku;
        }
        $this->productRepository->update($product, $data);
        return redirect()->route('products.index')->with('success', __('Product updated successfully.'));
    }

    /**
     * Remove the specified product.
     */
    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $user = $request->user();
        if ($user && $user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->branch_id) {
            if ($product->location_type !== Product::LOCATION_BRANCH || (int) $product->location_id !== (int) $user->branch_id) {
                abort(403, __('Anda tidak dapat mengakses produk cabang lain.'));
            }
        }
        $this->productRepository->delete($product);
        return redirect()->route('products.index')->with('success', __('Product deleted successfully.'));
    }

    /**
     * Toggle active status for the specified product.
     */
    public function toggleActive(Request $request, Product $product): RedirectResponse
    {
        $user = $request->user();
        if ($user && $user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->branch_id) {
            if ($product->location_type !== Product::LOCATION_BRANCH || (int) $product->location_id !== (int) $user->branch_id) {
                abort(403, __('Anda tidak dapat mengakses produk cabang lain.'));
            }
        }
        $product->is_active = ! (bool) $product->is_active;
        $product->save();

        $status = $product->is_active ? __('activated') : __('deactivated');
        return redirect()->route('products.index')->with('success', __('Product :status successfully.', ['status' => $status]));
    }

    /**
     * Display the specified product including serial-numbered units.
     */
    public function show(Request $request, Product $product): View
    {
        $user = $request->user();
        if ($user && $user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->branch_id) {
            if ($product->location_type !== Product::LOCATION_BRANCH || (int) $product->location_id !== (int) $user->branch_id) {
                abort(403, __('Anda tidak dapat mengakses produk cabang lain.'));
            }
        }
        $product->load(['category', 'user']);

        $query = ProductUnit::with(['warehouse', 'branch', 'user'])
            ->where('product_id', $product->id)
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('location_type')) {
            $query->where('location_type', $request->location_type);
        }
        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }
        if ($request->filled('serial')) {
            $query->where('serial_number', 'like', '%'.$request->serial.'%');
        }

        $units = $query->paginate(25)->withQueryString();

        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);
        $branches = Branch::orderBy('name')->get(['id', 'name']);

        return view('products.show', compact('product', 'units', 'warehouses', 'branches'));
    }

    /**
     * Hapus product unit yang belum terjual (status in_stock).
     */
    public function destroyUnit(Request $request, Product $product, ProductUnit $unit): RedirectResponse
    {
        if ($unit->product_id !== (int) $product->id) {
            abort(404, __('Unit tidak termasuk dalam produk ini.'));
        }

        if ($unit->status !== ProductUnit::STATUS_IN_STOCK) {
            return redirect()->route('products.show', $product)
                ->with('error', __('Hanya unit dengan status In Stock yang dapat dihapus.'));
        }

        $user = $request->user();
        if (! $user->isSuperAdmin() && ! $user->isAdminPusat()) {
            if ($user->hasRole(Role::ADMIN_CABANG) && $user->branch_id) {
                if ($unit->location_type !== Stock::LOCATION_BRANCH || (int) $unit->location_id !== (int) $user->branch_id) {
                    abort(403, __('Anda tidak dapat menghapus unit di cabang lain.'));
                }
            } else {
                abort(403, __('Unauthorized.'));
            }
        }

        $serialNumber = $unit->serial_number;
        $unit->delete();

        return redirect()->back()->with('success', __('Product unit :serial berhasil dihapus.', ['serial' => $serialNumber]));
    }
}
