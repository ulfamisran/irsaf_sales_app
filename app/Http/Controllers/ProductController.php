<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Warehouse;
use App\Repositories\CategoryRepository;
use App\Repositories\ProductRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(
        protected ProductRepository $productRepository,
        protected CategoryRepository $categoryRepository
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
            'branch_id' => $user && ! $user->isSuperAdmin() ? $user->branch_id : null,
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
        return view('products.create', compact('categories'));
    }

    /**
     * Store a newly created product.
     */
    public function store(ProductRequest $request): RedirectResponse
    {
        $this->productRepository->create($request->validated());
        return redirect()->route('products.index')->with('success', __('Product created successfully.'));
    }

    /**
     * Show the form for editing the specified product.
     */
    public function edit(Product $product): View
    {
        $categories = $this->categoryRepository->all();
        return view('products.edit', compact('product', 'categories'));
    }

    /**
     * Update the specified product.
     */
    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $this->productRepository->update($product, $request->validated());
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
     * Display the specified product including serial-numbered units.
     */
    public function show(Request $request, Product $product): View
    {
        $product->load('category');

        $query = ProductUnit::with(['warehouse', 'branch'])
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
