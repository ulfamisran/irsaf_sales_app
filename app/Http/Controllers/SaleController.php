<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaleRequest;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Stock;
use App\Models\Warehouse;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\AuditLog;
use App\Services\SaleService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use InvalidArgumentException;

class SaleController extends Controller
{
    public function __construct(
        protected SaleService $saleService
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $ctx = $this->buildSalesListQuery($request);
        $query = $ctx['query'];
        $summary = $this->computeSalesSummary($request, $user, $query);

        $sales = $query->paginate(20)->withQueryString();
        $branches = $user->isSuperAdminOrAdminPusat()
            ? Branch::orderBy('name')->get(['id', 'name'])
            : ($user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR]) && $user->branch_id ? Branch::whereKey($user->branch_id)->get(['id', 'name']) : collect());

        return view('sales.index', [
            'sales' => $sales,
            'branches' => $branches,
            'canFilterLocation' => $ctx['canFilterLocation'],
            'filterLocked' => $ctx['filterLocked'],
            'locationLabel' => $ctx['locationLabel'],
            ...$summary,
        ]);
    }

    /**
     * Export rekap penjualan ke Excel (HTML table, .xls) — query string sama seperti index.
     */
    public function export(Request $request): Response
    {
        $user = $request->user();
        $ctx = $this->buildSalesListQuery($request);
        $exportQuery = $this->salesExportBaseQuery($ctx['query']);
        $summary = $this->computeSalesSummary($request, $user, $exportQuery);
        $sales = (clone $exportQuery)->with(['payments', 'tradeIns'])->get();
        $filterMeta = $this->salesExportFilterMeta($request, $ctx, $sales);

        $filename = 'rekap-penjualan-' . now()->format('Ymd-His') . '.xls';
        $html = view('sales.export', array_merge($summary, compact('sales', 'filterMeta')))->render();

        return response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Export rekap penjualan ke PDF — query string sama seperti index.
     */
    public function exportPdf(Request $request)
    {
        $user = $request->user();
        $ctx = $this->buildSalesListQuery($request);
        $exportQuery = $this->salesExportBaseQuery($ctx['query']);
        $summary = $this->computeSalesSummary($request, $user, $exportQuery);
        $sales = (clone $exportQuery)->with(['payments', 'tradeIns'])->get();
        $filterMeta = $this->salesExportFilterMeta($request, $ctx, $sales);

        $pdf = Pdf::loadView('sales.export-pdf', array_merge($summary, compact('sales', 'filterMeta')))
            ->setPaper('a4', 'landscape');

        return $pdf->download('rekap-penjualan-' . now()->format('Ymd-His') . '.pdf');
    }

    public function create(): View
    {
        $user = auth()->user();
        if (! $user->isSuperAdminOrAdminPusat() && ! $user->branch_id) {
            abort(403, __('User branch not set.'));
        }

        $branches = $user->isSuperAdminOrAdminPusat()
            ? Branch::orderBy('name')->get()
            : Branch::whereKey($user->branch_id)->get();

        if ($user->isSuperAdminOrAdminPusat()) {
            // Super admin must choose branch first; products will be loaded via AJAX.
            $products = collect();
        } else {
            $branchId = (int) $user->branch_id;
            $productIds = Stock::query()
                ->where('location_type', Stock::LOCATION_BRANCH)
                ->where('location_id', $branchId)
                ->where('quantity', '>', 0)
                ->pluck('product_id')
                ->merge(
                    ProductUnit::query()
                        ->where('location_type', Stock::LOCATION_BRANCH)
                        ->where('location_id', $branchId)
                        ->where('status', ProductUnit::STATUS_IN_STOCK)
                        ->distinct()
                        ->pluck('product_id')
                )
                ->unique()
                ->values();

            $products = Product::with('category')
                ->whereIn('id', $productIds)
                ->orderBy('brand')
                ->orderBy('series')
                ->orderBy('sku')
                ->get();
        }

        $productsForJs = $products->map(function ($p) {
            return [
                'id' => $p->id,
                'sku' => $p->sku ?? '',
                'brand' => $p->brand ?? '',
                'series' => $p->series ?? '',
                'color' => $p->color ?? '',
                'price' => $p->selling_price,
                'category_id' => $p->category_id,
            ];
        })->values();

        $branchIdForData = $user->isSuperAdminOrAdminPusat() ? null : (int) $user->branch_id;
        $customers = $branchIdForData
            ? Customer::query()->where('branch_id', $branchIdForData)->where('is_active', true)->orderBy('name')->limit(500)->get(['id', 'name', 'phone'])
            : collect();
        $paymentMethods = $branchIdForData
            ? PaymentMethod::query()->where('branch_id', $branchIdForData)->where('is_active', true)->orderBy('jenis_pembayaran')->orderBy('nama_bank')->orderBy('id')->get(['id', 'jenis_pembayaran', 'nama_bank', 'atas_nama_bank', 'no_rekening'])
            : collect();

        $categories = Category::orderBy('name')->get(['id', 'name', 'code']);

        return view('sales.create', compact('branches', 'products', 'productsForJs', 'customers', 'paymentMethods', 'categories'));
    }

    public function store(SaleRequest $request): RedirectResponse
    {
        try {
            $user = $request->user();
            $branchId = $user->isSuperAdminOrAdminPusat()
                ? (int) $request->branch_id
                : (int) $user->branch_id;

            if (! $branchId) {
                abort(403, __('Branch is required.'));
            }

            $customerId = $this->resolveCustomerId($request);
            $discount = (float) ($request->input('discount_amount') ?? 0);
            $tax = (float) ($request->input('tax_amount') ?? 0);
            $description = $request->input('description');

            $status = $request->input('status');
            $payments = $request->input('payments', []);
            $tradeIns = $request->input('trade_ins', []);
            $tradeIns = is_array($tradeIns) ? array_filter($tradeIns, fn ($t) => ! empty(trim((string) ($t['sku'] ?? ''))) && ! empty(trim((string) ($t['brand'] ?? ''))) && (int) ($t['category_id'] ?? 0) > 0 && ! empty(trim((string) ($t['serial_number'] ?? ''))) && (float) ($t['trade_in_value'] ?? 0) > 0) : [];
            $allowSoldSerialReuse = $request->boolean('confirm_reuse_sold_serials');

            if ($status === Sale::STATUS_RELEASED) {
                $sale = $this->saleService->createReleasedSale(
                    $branchId,
                    $request->items,
                    $request->sale_date,
                    $payments,
                    $customerId,
                    $discount,
                    $tax,
                    $description,
                    $user->id,
                    $tradeIns,
                    $allowSoldSerialReuse
                );
            } else {
                $sale = $this->saleService->createDraftSale(
                    $branchId,
                    $request->items,
                    $request->sale_date,
                    $customerId,
                    $discount,
                    $tax,
                    $description,
                    $user->id,
                    $payments,
                    $tradeIns,
                    $allowSoldSerialReuse
                );
            }
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $msg = $sale->status === Sale::STATUS_RELEASED
            ? __('Penjualan berhasil dirilis.')
            : __('Penjualan berhasil disimpan sebagai draft.');

        return redirect()->route('sales.show', $sale)->with('success', $msg);
    }

    public function show(Sale $sale): View
    {
        $user = auth()->user();
        if (! $user->isSuperAdminOrAdminPusat() && $user->branch_id && $sale->branch_id !== $user->branch_id) {
            abort(403, __('Unauthorized.'));
        }
        $sale->load(['branch', 'user', 'customer', 'saleDetails.product', 'payments.paymentMethod', 'tradeIns.category']);
        $paymentMethods = PaymentMethod::query()
            ->where('is_active', true)
            ->orderBy('jenis_pembayaran')
            ->orderBy('nama_bank')
            ->orderBy('id')
            ->get(['id', 'jenis_pembayaran', 'nama_bank', 'atas_nama_bank', 'no_rekening']);

        return view('sales.show', compact('sale', 'paymentMethods'));
    }

    public function edit(Sale $sale): View
    {
        $user = auth()->user();
        if (! $user->isSuperAdminOrAdminPusat()) {
            abort(403, __('Unauthorized.'));
        }
        if (! $user->isSuperAdminOrAdminPusat() && $user->branch_id && $sale->branch_id !== $user->branch_id) {
            abort(403, __('Unauthorized.'));
        }
        if ($sale->status !== Sale::STATUS_OPEN) {
            abort(403, __('Penjualan tidak dapat diedit (sudah dirilis atau dibatalkan).'));
        }

        $sale->load(['saleDetails.product', 'customer', 'payments.paymentMethod']);

        $branches = $user->isSuperAdminOrAdminPusat()
            ? Branch::orderBy('name')->get()
            : Branch::whereKey($user->branch_id)->get();

        $branchId = (int) $sale->branch_id;
        $productIds = Stock::query()
            ->where('location_type', Stock::LOCATION_BRANCH)
            ->where('location_id', $branchId)
            ->where('quantity', '>', 0)
            ->pluck('product_id')
            ->merge(
                ProductUnit::query()
                    ->where('location_type', Stock::LOCATION_BRANCH)
                    ->where('location_id', $branchId)
                    ->whereIn('status', [ProductUnit::STATUS_IN_STOCK, ProductUnit::STATUS_KEEP])
                    ->distinct()
                    ->pluck('product_id')
            )
            ->merge($sale->saleDetails->pluck('product_id'))
            ->unique()
            ->values();

        $products = Product::with('category')
            ->whereIn('id', $productIds)
            ->orderBy('sku')
            ->get();

        $productsForJs = $products->map(function ($p) {
            return [
                'id' => $p->id,
                'sku' => $p->sku,
                'brand' => $p->brand,
                'series' => $p->series,
                'color' => $p->color ?? '',
                'price' => $p->selling_price,
                'category_id' => $p->category_id,
            ];
        })->values();

        $customers = Customer::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name', 'phone']);

        $paymentMethods = PaymentMethod::query()
            ->where('is_active', true)
            ->orderBy('jenis_pembayaran')
            ->orderBy('nama_bank')
            ->orderBy('id')
            ->get(['id', 'jenis_pembayaran', 'nama_bank', 'atas_nama_bank', 'no_rekening']);

        $categories = Category::orderBy('name')->get(['id', 'name', 'code']);

        return view('sales.edit', compact('sale', 'branches', 'products', 'productsForJs', 'customers', 'paymentMethods', 'categories'));
    }

    public function update(SaleRequest $request, Sale $sale): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdminOrAdminPusat()) {
            abort(403, __('Unauthorized.'));
        }
        if (! $user->isSuperAdminOrAdminPusat() && $user->branch_id && $sale->branch_id !== $user->branch_id) {
            abort(403, __('Unauthorized.'));
        }
        if ($sale->status !== Sale::STATUS_OPEN) {
            return back()->with('error', __('Sale sudah release dan tidak bisa diubah.'));
        }

        try {
            $customerId = $this->resolveCustomerId($request);
            $discount = (float) ($request->input('discount_amount') ?? 0);
            $tax = (float) ($request->input('tax_amount') ?? 0);
            $description = $request->input('description');
            $payments = $request->input('payments', []);
            $tradeIns = $request->input('trade_ins', []);
            $tradeIns = is_array($tradeIns) ? array_filter($tradeIns, fn ($t) => ! empty(trim((string) ($t['sku'] ?? ''))) && ! empty(trim((string) ($t['brand'] ?? ''))) && (int) ($t['category_id'] ?? 0) > 0 && ! empty(trim((string) ($t['serial_number'] ?? ''))) && (float) ($t['trade_in_value'] ?? 0) > 0) : [];
            $allowSoldSerialReuse = $request->boolean('confirm_reuse_sold_serials');

            // Update always keeps it OPEN; release must be done via explicit action.
            $sale = $this->saleService->updateDraftSale(
                $sale,
                $request->items,
                $request->sale_date,
                $customerId,
                $discount,
                $tax,
                $description,
                $payments,
                $tradeIns,
                $allowSoldSerialReuse
            );
            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'sale.update',
                'reference_type' => 'sale',
                'reference_id' => $sale->id,
                'description' => 'Update penjualan ' . $sale->invoice_number,
            ]);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('sales.show', $sale)->with('success', __('Draft penjualan berhasil diperbarui.'));
    }

    public function cancel(Request $request, Sale $sale): RedirectResponse
    {
        $user = auth()->user();
        if (! $user->isSuperAdminOrAdminPusat()) {
            abort(403, __('Unauthorized.'));
        }
        if (! $user->isSuperAdminOrAdminPusat() && $user->branch_id && $sale->branch_id !== $user->branch_id) {
            abort(403, __('Unauthorized.'));
        }
        if (! in_array($sale->status, [Sale::STATUS_OPEN, Sale::STATUS_RELEASED], true)) {
            return back()->with('error', __('Penjualan tidak dapat dibatalkan.'));
        }

        $validated = $request->validate([
            'cancel_reason' => ['required', 'string', 'max:255'],
            'confirm_released' => ['nullable', 'boolean'],
        ]);
        if ($sale->status === Sale::STATUS_RELEASED && empty($validated['confirm_released'])) {
            return back()->with('error', __('Konfirmasi tambahan wajib untuk membatalkan transaksi released.'));
        }

        try {
            $this->saleService->cancelSale($sale, $user->id, $validated['cancel_reason']);
            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'sale.cancel',
                'reference_type' => 'sale',
                'reference_id' => $sale->id,
                'description' => 'Cancel penjualan ' . $sale->invoice_number . '. Alasan: ' . $validated['cancel_reason'],
            ]);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('sales.show', $sale)->with('success', __('Penjualan berhasil dibatalkan. Unit kembali IN STOCK.'));
    }

    public function release(Request $request, Sale $sale): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdminOrAdminPusat() && $user->branch_id && $sale->branch_id !== $user->branch_id) {
            abort(403, __('Unauthorized.'));
        }
        if ($sale->status !== Sale::STATUS_OPEN) {
            return back()->with('error', __('Sale sudah release.'));
        }

        $validated = $request->validate([
            'sale_date' => ['required', 'date'],
            'payments' => ['nullable', 'array'],
            'payments.*.payment_method_id' => ['nullable', 'exists:payment_methods,id'],
            'payments.*.amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $payments = $validated['payments'] ?? [];
            $sale = $this->saleService->releaseSale(
                $sale,
                is_array($payments) ? $payments : [],
                $validated['sale_date'],
                $user->id
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('sales.show', $sale)->with('success', __('Penjualan berhasil dirilis.'));
    }

    public function storePayment(Request $request, Sale $sale): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdminOrAdminPusat() && $user->branch_id && $sale->branch_id !== $user->branch_id) {
            abort(403, __('Unauthorized.'));
        }

        $validated = $request->validate([
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'transaction_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->saleService->addPayment(
                $sale,
                (int) $validated['payment_method_id'],
                (float) $validated['amount'],
                $user->id,
                $validated['transaction_date'] ?? null,
                $validated['notes'] ?? null
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('sales.show', $sale)->with('success', __('Pembayaran berhasil ditambahkan.'));
    }

    public function availableSerials(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
        ]);

        $user = $request->user();
        $branchId = $user->isSuperAdminOrAdminPusat()
            ? (int) ($validated['branch_id'] ?? 0)
            : (int) $user->branch_id;

        $isSerialTracked = ProductUnit::query()
            ->where('product_id', (int) $validated['product_id'])
            ->exists();

        if (! $branchId) {
            return response()->json([
                'serial_numbers' => [],
                'units' => [],
                'is_serial_tracked' => $isSerialTracked,
            ]);
        }

        $units = ProductUnit::query()
            ->where('product_id', (int) $validated['product_id'])
            ->where('location_type', Stock::LOCATION_BRANCH)
            ->where('location_id', $branchId)
            ->whereIn('status', [ProductUnit::STATUS_IN_STOCK, ProductUnit::STATUS_KEEP])
            ->orderBy('serial_number')
            ->limit(500)
            ->get(['serial_number', 'harga_jual', 'harga_hpp']);

        $serials = $units->pluck('serial_number')->all();
        $unitsData = $units->map(fn ($u) => [
            'serial_number' => $u->serial_number,
            'harga_jual' => (float) ($u->harga_jual ?? 0),
            'harga_hpp' => (float) ($u->harga_hpp ?? 0),
        ])->values()->all();

        return response()->json([
            'serial_numbers' => $serials,
            'units' => $unitsData,
            'is_serial_tracked' => $isSerialTracked,
        ]);
    }

    public function availableProducts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
        ]);

        $user = $request->user();
        $branchId = $user->isSuperAdminOrAdminPusat()
            ? (int) $validated['branch_id']
            : (int) $user->branch_id;

        if (! $branchId) {
            return response()->json(['products' => []]);
        }

        $productIds = Stock::query()
            ->where('location_type', Stock::LOCATION_BRANCH)
            ->where('location_id', $branchId)
            ->where('quantity', '>', 0)
            ->pluck('product_id')
            ->merge(
                ProductUnit::query()
                    ->where('location_type', Stock::LOCATION_BRANCH)
                    ->where('location_id', $branchId)
                    ->where('status', ProductUnit::STATUS_IN_STOCK)
                    ->distinct()
                    ->pluck('product_id')
            )
            ->unique()
            ->values();

        $products = Product::query()
            ->where('is_active', true)
            ->whereIn('id', $productIds)
            ->orderBy('brand')
            ->orderBy('series')
            ->orderBy('sku')
            ->limit(500)
            ->get(['id', 'sku', 'brand', 'series', 'color', 'selling_price', 'category_id']);

        return response()->json([
            'products' => $products->map(function ($p) {
                return [
                    'id' => $p->id,
                    'sku' => $p->sku ?? '',
                    'brand' => $p->brand ?? '',
                    'series' => $p->series ?? '',
                    'color' => $p->color ?? '',
                    'price' => $p->selling_price,
                    'category_id' => $p->category_id,
                ];
            })->values(),
        ]);
    }

    public function checkReusableTradeInSerials(Request $request): JsonResponse
    {
        $serials = [];
        $tradeIns = $request->input('trade_ins', []);
        if (is_array($tradeIns)) {
            foreach ($tradeIns as $tradeIn) {
                $sn = trim((string) ($tradeIn['serial_number'] ?? ''));
                if ($sn !== '') {
                    $serials[] = $sn;
                }
            }
        }
        $serials = array_values(array_unique($serials));
        if (empty($serials)) {
            return response()->json([
                'has_reusable_sold_serials' => false,
                'sold_serials' => [],
                'blocked_serials' => [],
            ]);
        }

        $existingUnits = ProductUnit::whereIn('serial_number', $serials)
            ->get(['serial_number', 'status']);
        $soldSerials = [];
        $blockedSerials = [];
        foreach ($existingUnits as $unit) {
            if ($unit->status === ProductUnit::STATUS_SOLD) {
                $soldSerials[] = $unit->serial_number;
            } else {
                $blockedSerials[] = $unit->serial_number;
            }
        }

        return response()->json([
            'has_reusable_sold_serials' => ! empty($soldSerials),
            'sold_serials' => array_values(array_unique($soldSerials)),
            'blocked_serials' => array_values(array_unique($blockedSerials)),
        ]);
    }

    /**
     * Autocomplete: cari unit by nomor serial untuk mengisi form tukar tambah dari data produk terkait.
     */
    public function searchTradeInSerial(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $q = trim($validated['q']);
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);

        // Tanpa filter cabang: unit bisa berasal dari cabang/gudang lain; lokasi diperbarui saat tukar tambah disimpan.
        $units = ProductUnit::query()
            ->with(['product:id,category_id,sku,brand,series,processor,ram,storage,color,specs'])
            ->where('serial_number', 'like', '%'.$escaped.'%')
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [ProductUnit::STATUS_SOLD])
            ->limit(20)
            ->get();

        $results = $units->map(function (ProductUnit $unit) {
            $p = $unit->product;

            return [
                'serial_number' => $unit->serial_number,
                'sku' => $p?->sku ?? '',
                'brand' => $p?->brand ?? '',
                'series' => $p?->series ?? '',
                'processor' => $p?->processor ?? '',
                'ram' => $p?->ram ?? '',
                'storage' => $p?->storage ?? '',
                'color' => $p?->color ?? '',
                'specs' => $p?->specs ?? '',
                'category_id' => $p?->category_id,
                'harga_hpp' => $unit->harga_hpp !== null ? (float) $unit->harga_hpp : null,
                'status' => $unit->status,
            ];
        })->values();

        return response()->json(['results' => $results]);
    }

    public function invoice(Sale $sale): View
    {
        $user = auth()->user();
        if (! $user->isSuperAdminOrAdminPusat() && $user->branch_id && $sale->branch_id !== $user->branch_id) {
            abort(403, __('Unauthorized.'));
        }

        $sale->load(['branch', 'user', 'customer', 'saleDetails.product', 'payments.paymentMethod', 'tradeIns.category']);

        return view('sales.invoice', compact('sale'));
    }

    /**
     * @return array{query: Builder, canFilterLocation: bool, filterLocked: bool, locationLabel: string|null}
     */
    private function buildSalesListQuery(Request $request): array
    {
        $user = $request->user();
        $query = Sale::with(['branch', 'user', 'customer', 'saleDetails', 'payments.paymentMethod'])
            ->orderByDesc('sale_date')
            ->orderByDesc('id');

        $canFilterLocation = false;
        $filterLocked = false;
        $locationLabel = null;

        if (! $user->isSuperAdminOrAdminPusat()) {
            if ($user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR]) && $user->branch_id) {
                $query->where('branch_id', $user->branch_id);
                $filterLocked = true;
                $branch = Branch::find($user->branch_id);
                $locationLabel = __('Cabang') . ': ' . ($branch?->name ?? '#' . $user->branch_id);
            } elseif ($user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->warehouse_id) {
                $query->whereRaw('1 = 0');
                $filterLocked = true;
                $warehouse = Warehouse::find($user->warehouse_id);
                $locationLabel = __('Gudang') . ': ' . ($warehouse?->name ?? '#' . $user->warehouse_id);
            } elseif (! $user->branch_id && ! $user->warehouse_id) {
                abort(403, __('User branch or warehouse not set.'));
            }
        } else {
            $canFilterLocation = true;
            if ($request->filled('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }
        }
        if ($request->filled('date_from')) {
            $query->whereDate('sale_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('sale_date', '<=', $request->date_to);
        }
        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"));
            });
        }

        return compact('query', 'canFilterLocation', 'filterLocked', 'locationLabel');
    }

    /**
     * Query untuk unduhan rekap: sama seperti daftar, tanpa penjualan dibatalkan.
     */
    private function salesExportBaseQuery(Builder $filteredSalesQuery): Builder
    {
        $q = clone $filteredSalesQuery;

        return $q->where('status', '!=', Sale::STATUS_CANCEL);
    }

    /**
     * Ringkasan keuangan untuk penjualan released yang memenuhi filter (termasuk pencarian).
     *
     * @return array{
     *   totalSales: float,
     *   totalSalesCash: float,
     *   totalTradeIn: float,
     *   totalSalesCombined: float,
     *   paymentMethods: \Illuminate\Support\Collection,
     *   paymentMethodTotals: \Illuminate\Support\Collection
     * }
     */
    private function computeSalesSummary(Request $request, $user, Builder $query): array
    {
        $totalSales = (float) (clone $query)
            ->where('status', Sale::STATUS_RELEASED)
            ->sum('total');

        $releasedIds = (clone $query)->where('status', Sale::STATUS_RELEASED)->pluck('id');

        $totalSalesCash = $releasedIds->isEmpty()
            ? 0.0
            : (float) DB::table('sale_payments')->whereIn('sale_id', $releasedIds)->sum('amount');

        $totalTradeIn = $releasedIds->isEmpty()
            ? 0.0
            : (float) DB::table('sale_trade_ins')->whereIn('sale_id', $releasedIds)->sum('trade_in_value');

        $pmBranchId = $user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR]) && $user->branch_id
            ? (int) $user->branch_id
            : ($user->isSuperAdminOrAdminPusat() && $request->filled('branch_id') ? (int) $request->branch_id : null);
        $pmWarehouseId = $user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->warehouse_id ? (int) $user->warehouse_id : null;
        $paymentMethods = PaymentMethod::query()
            ->where('is_active', true)
            ->forLocation($pmBranchId, $pmWarehouseId)
            ->orderBy('jenis_pembayaran')
            ->orderBy('nama_bank')
            ->orderBy('no_rekening')
            ->get(['id', 'jenis_pembayaran', 'nama_bank', 'atas_nama_bank', 'no_rekening']);

        $paymentMethodTotals = $releasedIds->isEmpty()
            ? collect()
            : DB::table('sale_payments')
                ->whereIn('sale_id', $releasedIds)
                ->selectRaw('payment_method_id, SUM(amount) as total')
                ->groupBy('payment_method_id')
                ->pluck('total', 'payment_method_id');

        $totalSalesCombined = $totalSalesCash + $totalTradeIn;

        return compact('totalSales', 'totalSalesCash', 'totalTradeIn', 'totalSalesCombined', 'paymentMethods', 'paymentMethodTotals');
    }

    /**
     * @param  array{canFilterLocation: bool, filterLocked: bool, locationLabel: string|null}  $ctx
     * @return array{branchLine: string, dateFrom: string, dateTo: string, search: string}
     */
    private function salesExportFilterMeta(Request $request, array $ctx, $sales = null): array
    {
        $user = $request->user();
        $branchLine = __('Semua');
        if (! empty($ctx['filterLocked']) && $ctx['locationLabel']) {
            $branchLine = $ctx['locationLabel'];
        } elseif ($user->isSuperAdminOrAdminPusat() && $request->filled('branch_id')) {
            $b = Branch::find($request->branch_id);
            $branchLine = $b ? (string) $b->name : (string) $request->branch_id;
        }

        $dateFrom = $request->filled('date_from') ? (string) $request->date_from : null;
        $dateTo = $request->filled('date_to') ? (string) $request->date_to : null;
        if ((! $dateFrom || ! $dateTo) && $sales && method_exists($sales, 'isNotEmpty') && $sales->isNotEmpty()) {
            $minDate = $sales->min('sale_date');
            $maxDate = $sales->max('sale_date');
            $autoFrom = $minDate ? (string) (is_object($minDate) && method_exists($minDate, 'format') ? $minDate->format('Y-m-d') : $minDate) : null;
            $autoTo = $maxDate ? (string) (is_object($maxDate) && method_exists($maxDate, 'format') ? $maxDate->format('Y-m-d') : $maxDate) : null;
            $dateFrom = $dateFrom ?: $autoFrom;
            $dateTo = $dateTo ?: $autoTo;
        }
        $dateFrom = $dateFrom ?: '-';
        $dateTo = $dateTo ?: '-';
        $search = $request->filled('search') ? trim((string) $request->search) : '-';

        return [
            'branchLine' => $branchLine,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'search' => $search,
        ];
    }

    private function resolveCustomerId(Request $request): ?int
    {
        if ($request->filled('customer_id')) {
            return (int) $request->input('customer_id');
        }

        $name = trim((string) $request->input('customer_new_name', ''));
        if ($name === '') {
            return null;
        }

        $user = $request->user();
        $branchId = $user->isSuperAdminOrAdminPusat()
            ? (int) $request->branch_id
            : (int) $user->branch_id;

        $customer = Customer::create([
            'name' => $name,
            'phone' => $request->input('customer_new_phone'),
            'address' => $request->input('customer_new_address'),
            'is_active' => true,
            'placement_type' => $branchId ? 'cabang' : null,
            'branch_id' => $branchId ?: null,
        ]);

        return (int) $customer->id;
    }
}
