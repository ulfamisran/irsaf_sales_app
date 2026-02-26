<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\IncomingGood;
use App\Models\Product;
use App\Models\Role;
use App\Models\Stock;
use App\Models\Warehouse;
use App\Services\StockMutationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IncomingGoodsController extends Controller
{
    public function __construct(
        protected StockMutationService $stockMutationService
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        if (! $user->isSuperAdmin() && ! $user->hasAnyRole([Role::STAFF_GUDANG])) {
            abort(403, __('Unauthorized.'));
        }

        $query = IncomingGood::with(['product', 'warehouse', 'user'])
            ->orderByDesc('received_date')
            ->orderByDesc('id');

        if ($request->filled('category_id')) {
            $query->whereHas('product', fn ($q) => $q->where('category_id', $request->category_id));
        }
        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }
        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('received_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('received_date', '<=', $request->date_to);
        }

        $records = $query->paginate(20)->withQueryString();

        $productsQuery = Product::with('category')->orderBy('sku');
        if ($request->filled('category_id')) {
            $productsQuery->where('category_id', $request->category_id);
        }
        $products = $productsQuery->get(['id', 'sku', 'brand', 'category_id']);
        $categories = Category::orderBy('name')->get(['id', 'name']);
        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);

        return view('incoming-goods.index', compact('records', 'products', 'categories', 'warehouses'));
    }

    public function create(): View
    {
        $user = auth()->user();
        if (! $user->isSuperAdmin() && ! $user->hasAnyRole([Role::STAFF_GUDANG])) {
            abort(403, __('Unauthorized.'));
        }

        $products = Product::orderBy('sku')->get();
        $warehouses = Warehouse::orderBy('name')->get();

        return view('incoming-goods.create', compact('products', 'warehouses'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdmin() && ! $user->hasAnyRole([Role::STAFF_GUDANG])) {
            abort(403, __('Unauthorized.'));
        }

        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'required_without:serial_numbers'],
            'serial_numbers' => ['nullable', 'string', 'required_without:quantity'],
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $serialNumbers = $this->parseSerialNumbers($validated['serial_numbers'] ?? null);
        $quantity = ! empty($serialNumbers) ? count($serialNumbers) : (int) $validated['quantity'];

        $this->stockMutationService->addStock(
            $product,
            Stock::LOCATION_WAREHOUSE,
            (int) $validated['warehouse_id'],
            $quantity,
            $user->id,
            $serialNumbers,
            now()->toDateString()
        );

        IncomingGood::create([
            'product_id' => $product->id,
            'warehouse_id' => (int) $validated['warehouse_id'],
            'quantity' => $quantity,
            'received_date' => now()->toDateString(),
            'notes' => ! empty($serialNumbers) ? implode("\n", $serialNumbers) : null,
            'user_id' => $user->id,
        ]);

        return redirect()->route('incoming-goods.create')->with('success', __('Incoming goods recorded successfully.'));
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
}
