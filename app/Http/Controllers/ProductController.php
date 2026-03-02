<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductUnit;
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

        $products = $this->productRepository->paginate(15, [
            'search' => $request->get('search'),
            'category_id' => $request->get('category_id'),
            'branch_id' => $user && ! $user->isSuperAdminOrAdminPusat() ? $user->branch_id : null,
        ]);
        $categories = $this->categoryRepository->all();
        return view('products.index', compact('products', 'categories'));
    }

    /**
     * Show the form for creating a new product.
     */
    public function create(): View
    {
        $categories = $this->categoryRepository->all();
        $distributors = $this->distributorRepository->all();
        return view('products.create', compact('categories', 'distributors'));
    }

    /**
     * Store a newly created product.
     */
    public function store(ProductRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()?->id;
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
     */
    public function edit(Product $product): View
    {
        $categories = $this->categoryRepository->all();
        $distributors = $this->distributorRepository->all();
        return view('products.edit', compact('product', 'categories', 'distributors'));
    }

    /**
     * Update the specified product.
     */
    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $data = $request->validated();
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
    public function destroy(Product $product): RedirectResponse
    {
        $this->productRepository->delete($product);
        return redirect()->route('products.index')->with('success', __('Product deleted successfully.'));
    }

    /**
     * Toggle active status for the specified product.
     */
    public function toggleActive(Product $product): RedirectResponse
    {
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
}
