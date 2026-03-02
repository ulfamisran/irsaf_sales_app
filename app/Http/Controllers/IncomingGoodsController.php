<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\IncomingGood;
use App\Models\Product;
use App\Models\Role;
use App\Models\Stock;
use App\Models\Branch;
use App\Models\Warehouse;
use App\Models\AuditLog;
use App\Services\StockMutationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class IncomingGoodsController extends Controller
{
    public function __construct(
        protected StockMutationService $stockMutationService
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        if (! $user->isSuperAdminOrAdminPusat() && ! $user->hasAnyRole([Role::STAFF_GUDANG, Role::ADMIN_CABANG])) {
            abort(403, __('Unauthorized.'));
        }

        $isBranchUser = ! $user->isSuperAdminOrAdminPusat() && $user->hasAnyRole([Role::ADMIN_CABANG]);
        if ($isBranchUser && ! $user->branch_id) {
            abort(403, __('User branch not set.'));
        }

        $query = IncomingGood::with(['product', 'warehouse', 'branch', 'user'])
            ->orderByDesc('received_date')
            ->orderByDesc('id');

        if ($isBranchUser) {
            $query->where('branch_id', (int) $user->branch_id);
        }

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
        if ($isBranchUser) {
            $productsQuery->where('laptop_type', 'baru');
        }
        if ($request->filled('category_id')) {
            $productsQuery->where('category_id', $request->category_id);
        }
        $products = $productsQuery->get(['id', 'sku', 'brand', 'category_id']);
        $categories = Category::orderBy('name')->get(['id', 'name']);
        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);
        $branch = $isBranchUser ? Branch::find($user->branch_id) : null;

        return view('incoming-goods.index', compact('records', 'products', 'categories', 'warehouses', 'isBranchUser', 'branch'));
    }

    public function create(Request $request): View
    {
        $user = auth()->user();
        if (! $user->isSuperAdminOrAdminPusat() && ! $user->hasAnyRole([Role::STAFF_GUDANG, Role::ADMIN_CABANG])) {
            abort(403, __('Unauthorized.'));
        }

        $isBranchUser = ! $user->isSuperAdminOrAdminPusat() && $user->hasAnyRole([Role::ADMIN_CABANG]);
        if ($isBranchUser && ! $user->branch_id) {
            abort(403, __('User branch not set.'));
        }

        $productsQuery = Product::orderBy('sku')
            ->when($isBranchUser, fn ($q) => $q->where('laptop_type', 'baru'));
        $selectedProduct = null;
        if ($request->filled('product_id')) {
            $selectedProduct = (clone $productsQuery)->whereKey($request->product_id)->first();
            if (! $selectedProduct) {
                abort(404, __('Product not found.'));
            }
            if (! $selectedProduct->is_active) {
                abort(403, __('Produk nonaktif tidak bisa ditambah unit.'));
            }
            $products = collect([$selectedProduct]);
        } else {
            $products = $productsQuery->get();
        }
        $warehouses = Warehouse::orderBy('name')->get();
        $selectedWarehouse = null;
        if ($request->filled('warehouse_id')) {
            $selectedWarehouse = $warehouses->firstWhere('id', (int) $request->warehouse_id);
            if (! $selectedWarehouse) {
                abort(404, __('Warehouse not found.'));
            }
        } elseif ($warehouses->count() === 1) {
            $selectedWarehouse = $warehouses->first();
        }
        $branch = $isBranchUser ? Branch::find($user->branch_id) : null;

        return view('incoming-goods.create', compact('products', 'warehouses', 'isBranchUser', 'branch', 'selectedProduct', 'selectedWarehouse'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdminOrAdminPusat() && ! $user->hasAnyRole([Role::STAFF_GUDANG, Role::ADMIN_CABANG])) {
            abort(403, __('Unauthorized.'));
        }

        $isBranchUser = ! $user->isSuperAdminOrAdminPusat() && $user->hasAnyRole([Role::ADMIN_CABANG]);
        if ($isBranchUser && ! $user->branch_id) {
            abort(403, __('User branch not set.'));
        }

        $rules = [
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'required_without:serial_numbers'],
            'serial_numbers' => ['nullable', 'string', 'required_without:quantity'],
        ];
        if (! $isBranchUser) {
            $rules['warehouse_id'] = ['required', 'exists:warehouses,id'];
        }
        $validated = $request->validate($rules);

        try {
            $product = Product::findOrFail($validated['product_id']);
            if ($isBranchUser && $product->laptop_type !== 'baru') {
                return back()->withInput()->with('error', __('Hanya produk jenis baru yang boleh dicatat sebagai barang masuk cabang.'));
            }
            $serialNumbers = $this->parseSerialNumbers($validated['serial_numbers'] ?? null);
            $quantity = ! empty($serialNumbers) ? count($serialNumbers) : (int) $validated['quantity'];

            $this->stockMutationService->addStock(
                $product,
                $isBranchUser ? Stock::LOCATION_BRANCH : Stock::LOCATION_WAREHOUSE,
                $isBranchUser ? (int) $user->branch_id : (int) $validated['warehouse_id'],
                $quantity,
                $user->id,
                $serialNumbers,
                now()->toDateString()
            );

            IncomingGood::create([
                'product_id' => $product->id,
                'warehouse_id' => $isBranchUser ? null : (int) $validated['warehouse_id'],
                'branch_id' => $isBranchUser ? (int) $user->branch_id : null,
                'quantity' => $quantity,
                'received_date' => now()->toDateString(),
                'notes' => ! empty($serialNumbers) ? implode("\n", $serialNumbers) : null,
                'user_id' => $user->id,
            ]);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'incoming_goods.create',
                'reference_type' => 'incoming_goods',
                'reference_id' => null,
            'description' => 'Incoming goods for product ' . ($product->sku ?? '') . ' qty ' . $quantity,
            ]);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

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

        return $this->ensureUniqueSerials($out);
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
