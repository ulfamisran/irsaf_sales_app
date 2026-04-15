<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\IncomingGood;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Role;
use App\Models\Stock;
use App\Models\Branch;
use App\Models\Warehouse;
use App\Models\AuditLog;
use App\Services\StockMutationService;
use Illuminate\Http\JsonResponse;
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
        if (! $user->isSuperAdminOrAdminPusat() && ! $user->hasAnyRole([Role::ADMIN_GUDANG, Role::ADMIN_CABANG])) {
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
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
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
        $branches = Branch::orderBy('name')->get(['id', 'name']);
        $branch = $isBranchUser ? Branch::find($user->branch_id) : null;

        return view('incoming-goods.index', compact('records', 'products', 'categories', 'warehouses', 'branches', 'isBranchUser', 'branch'));
    }

    public function create(Request $request): View
    {
        $user = auth()->user();
        if (! $user->isSuperAdminOrAdminPusat() && ! $user->hasAnyRole([Role::ADMIN_GUDANG, Role::ADMIN_CABANG])) {
            abort(403, __('Unauthorized.'));
        }

        $isBranchUser = ! $user->isSuperAdminOrAdminPusat() && $user->hasAnyRole([Role::ADMIN_CABANG]);
        if ($isBranchUser && ! $user->branch_id) {
            abort(403, __('User branch not set.'));
        }

        $selectedProduct = null;
        if ($request->filled('product_id')) {
            $selectedProduct = Product::whereKey($request->product_id)->first();
            if (! $selectedProduct) {
                abort(404, __('Product not found.'));
            }
            if (! $selectedProduct->is_active) {
                abort(403, __('Produk nonaktif tidak bisa ditambah unit.'));
            }
        }
        $warehouses = Warehouse::orderBy('name')->get();
        $branches = $isBranchUser ? collect() : Branch::orderBy('name')->get();
        $selectedWarehouse = null;
        $selectedBranch = null;
        if ($request->filled('warehouse_id')) {
            $selectedWarehouse = $warehouses->firstWhere('id', (int) $request->warehouse_id);
            if (! $selectedWarehouse) {
                abort(404, __('Warehouse not found.'));
            }
        } elseif ($warehouses->count() === 1 && ! $isBranchUser) {
            $selectedWarehouse = $warehouses->first();
        }
        if ($request->filled('branch_id') && ! $isBranchUser) {
            $selectedBranch = $branches->firstWhere('id', (int) $request->branch_id);
            if (! $selectedBranch) {
                abort(404, __('Branch not found.'));
            }
        }
        $branch = $isBranchUser ? Branch::find($user->branch_id) : null;
        $categories = Category::orderBy('name')->get(['id', 'name']);

        return view('incoming-goods.create', compact('warehouses', 'branches', 'categories', 'isBranchUser', 'branch', 'selectedProduct', 'selectedWarehouse', 'selectedBranch'));
    }

    public function availableProducts(Request $request): JsonResponse
    {
        $user = $request->user();
        $isBranchUser = ! $user->isSuperAdminOrAdminPusat() && $user->hasAnyRole([Role::ADMIN_CABANG]);

        $locationType = $isBranchUser ? Stock::LOCATION_BRANCH : $request->input('location_type');
        $locationId = $isBranchUser ? (int) $user->branch_id : (int) $request->input('location_id');
        $categoryId = $request->filled('category_id') ? (int) $request->category_id : null;

        if (! $locationType || ! $locationId) {
            return response()->json(['products' => []]);
        }

        $locType = $locationType === 'branch' ? Product::LOCATION_BRANCH : Product::LOCATION_WAREHOUSE;

        $query = Product::query()
            ->where('is_active', true)
            ->where('location_type', $locType)
            ->where('location_id', $locationId)
            ->orderBy('brand')
            ->orderBy('series')
            ->orderBy('sku');

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $products = $query->limit(500)->get(['id', 'sku', 'brand', 'series', 'category_id', 'purchase_price', 'selling_price']);

        return response()->json([
            'products' => $products->map(fn ($p) => [
                'id' => $p->id,
                'sku' => $p->sku ?? '',
                'brand' => $p->brand ?? '',
                'series' => $p->series ?? '',
                'purchase_price' => (float) ($p->purchase_price ?? 0),
                'selling_price' => (float) ($p->selling_price ?? 0),
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdminOrAdminPusat() && ! $user->hasAnyRole([Role::ADMIN_GUDANG, Role::ADMIN_CABANG])) {
            abort(403, __('Unauthorized.'));
        }

        $isBranchUser = ! $user->isSuperAdminOrAdminPusat() && $user->hasAnyRole([Role::ADMIN_CABANG]);
        if ($isBranchUser && ! $user->branch_id) {
            abort(403, __('User branch not set.'));
        }

        $request->merge([
            'purchase_price' => $this->parseRupiahToFloat($request->input('purchase_price')),
            'selling_price' => $this->parseRupiahToFloat($request->input('selling_price')),
        ]);

        $rules = [
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'required_without:serial_numbers'],
            'serial_numbers' => ['nullable', 'string', 'required_without:quantity'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
        ];
        if ($isBranchUser) {
            // Cabang: fixed ke branch user
        } else {
            $rules['location_type'] = ['required', 'in:warehouse,branch'];
            $rules['warehouse_id'] = ['required_if:location_type,warehouse', 'nullable', 'exists:warehouses,id'];
            $rules['branch_id'] = ['required_if:location_type,branch', 'nullable', 'exists:branches,id'];
        }
        $validated = $request->validate($rules);

        try {
            $product = Product::findOrFail($validated['product_id']);
            $serialNumbers = $this->parseSerialNumbers($validated['serial_numbers'] ?? null);
            $quantity = ! empty($serialNumbers) ? count($serialNumbers) : (int) $validated['quantity'];

            $purchasePrice = $validated['purchase_price'] !== null && $validated['purchase_price'] !== ''
                ? round((float) $validated['purchase_price'], 2)
                : null;
            $sellingPrice = $validated['selling_price'] !== null && $validated['selling_price'] !== ''
                ? round((float) $validated['selling_price'], 2)
                : null;

            $locationType = $isBranchUser ? Stock::LOCATION_BRANCH : ($validated['location_type'] ?? 'warehouse');
            $locationId = $isBranchUser
                ? (int) $user->branch_id
                : (int) (($validated['location_type'] ?? '') === 'branch' ? ($validated['branch_id'] ?? 0) : ($validated['warehouse_id'] ?? 0));

            $this->stockMutationService->addStock(
                $product,
                $locationType,
                $locationId,
                $quantity,
                $user->id,
                $serialNumbers,
                now()->toDateString(),
                $purchasePrice,
                $sellingPrice
            );

            $warehouseId = $locationType === Stock::LOCATION_WAREHOUSE ? $locationId : null;
            $branchId = $locationType === Stock::LOCATION_BRANCH ? $locationId : null;

            IncomingGood::create([
                'product_id' => $product->id,
                'warehouse_id' => $warehouseId,
                'branch_id' => $branchId,
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
        } catch (\Throwable $e) {
            report($e);
            return back()->withInput()->with('error', $e->getMessage() ?: __('Terjadi kesalahan saat menyimpan.'));
        }

        return redirect()->route('incoming-goods.index')->with('success', __('Barang masuk berhasil dicatat.'));
    }

    public function detail(Request $request, IncomingGood $incomingGood): JsonResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdminOrAdminPusat() && ! $user->hasAnyRole([Role::ADMIN_GUDANG, Role::ADMIN_CABANG])) {
            abort(403, __('Unauthorized.'));
        }
        $isBranchUser = ! $user->isSuperAdminOrAdminPusat() && $user->hasAnyRole([Role::ADMIN_CABANG]);
        if ($isBranchUser && $incomingGood->branch_id && (int) $incomingGood->branch_id !== (int) $user->branch_id) {
            abort(403, __('Unauthorized.'));
        }

        $incomingGood->load(['product', 'warehouse', 'branch', 'user']);
        $serials = $this->parseSerialNumbersForDetail($incomingGood->notes);
        $units = [];
        if (! empty($serials)) {
            $productUnits = ProductUnit::with(['warehouse', 'branch'])
                ->where('product_id', $incomingGood->product_id)
                ->whereIn('serial_number', $serials)
                ->orderBy('serial_number')
                ->get();
            $statusLabels = [
                ProductUnit::STATUS_IN_STOCK => 'Tersedia',
                ProductUnit::STATUS_KEEP => 'Dipesan',
                ProductUnit::STATUS_SOLD => 'Terjual',
                ProductUnit::STATUS_IN_RENT => 'Disewa',
                ProductUnit::STATUS_INACTIVE => 'Nonaktif',
                ProductUnit::STATUS_CANCEL => 'Dibatalkan',
                ProductUnit::STATUS_NOT_IN_STOCK => 'Tidak di stok',
            ];
            foreach ($productUnits as $u) {
                if ($u->location_type === Stock::LOCATION_WAREHOUSE) {
                    $posisi = 'Gudang: ' . (Warehouse::find($u->location_id)?->name ?? '-');
                } else {
                    $posisi = 'Cabang: ' . (Branch::find($u->location_id)?->name ?? '-');
                }
                $units[] = [
                    'serial_number' => $u->serial_number,
                    'posisi' => $posisi,
                    'status' => $statusLabels[$u->status] ?? $u->status,
                ];
            }
        }

        $lokasi = $incomingGood->branch_id
            ? 'Cabang: ' . ($incomingGood->branch?->name ?? '#'.$incomingGood->branch_id)
            : 'Gudang: ' . ($incomingGood->warehouse?->name ?? '-');

        return response()->json([
            'tanggal' => $incomingGood->received_date->format('d/m/Y'),
            'produk' => ($incomingGood->product?->sku ?? '-') . ' - ' . ($incomingGood->product?->brand ?? '') . ($incomingGood->product?->series ? ' ' . $incomingGood->product->series : ''),
            'lokasi' => $lokasi,
            'qty' => $incomingGood->quantity,
            'user' => $incomingGood->user?->name ?? '-',
            'units' => $units,
            'has_serial' => ! empty($serials),
        ]);
    }

    private function parseRupiahToFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        $str = preg_replace('/[^\d]/', '', (string) $value);

        return $str !== '' ? (float) $str : null;
    }

    /**
     * @return array<int, string>
     */
    private function parseSerialNumbersForDetail(?string $input): array
    {
        if (! $input) {
            return [];
        }
        $parts = preg_split('/[\r\n,]+/', $input) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== '') {
                $out[] = $p;
            }
        }
        return array_values(array_unique($out));
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
