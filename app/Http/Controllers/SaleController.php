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
use App\Models\Sale;
use App\Models\SalePayment;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $query = Sale::with(['branch', 'user', 'customer', 'saleDetails'])
            ->orderByDesc('sale_date')
            ->orderByDesc('id');

        if (! $user->isSuperAdmin()) {
            if (! $user->branch_id) {
                abort(403, __('User branch not set.'));
            }
            $query->where('branch_id', $user->branch_id);
        } elseif ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('sale_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('sale_date', '<=', $request->date_to);
        }

        $sales = $query->paginate(20)->withQueryString();
        $branches = $user->isSuperAdmin()
            ? Branch::orderBy('name')->get(['id', 'name'])
            : Branch::whereKey($user->branch_id)->get(['id', 'name']);

        return view('sales.index', compact('sales', 'branches'));
    }

    public function create(): View
    {
        $user = auth()->user();
        if (! $user->isSuperAdmin() && ! $user->branch_id) {
            abort(403, __('User branch not set.'));
        }

        $branches = $user->isSuperAdmin()
            ? Branch::orderBy('name')->get()
            : Branch::whereKey($user->branch_id)->get();

        if ($user->isSuperAdmin()) {
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
                ->orderBy('sku')
                ->get();
        }

        $productsForJs = $products->map(function ($p) {
            return [
                'id' => $p->id,
                'sku' => $p->sku,
                'brand' => $p->brand,
                'series' => $p->series,
                'price' => $p->selling_price,
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

        $categories = Category::orderBy('name')->get(['id', 'name']);

        return view('sales.create', compact('branches', 'products', 'productsForJs', 'customers', 'paymentMethods', 'categories'));
    }

    public function store(SaleRequest $request): RedirectResponse
    {
        try {
            $user = $request->user();
            $branchId = $user->isSuperAdmin()
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
                    $tradeIns
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
                    $tradeIns
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
        if (! $user->isSuperAdmin() && $user->branch_id && $sale->branch_id !== $user->branch_id) {
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
        if (! $user->isSuperAdmin() && $user->branch_id && $sale->branch_id !== $user->branch_id) {
            abort(403, __('Unauthorized.'));
        }
        if ($sale->status !== Sale::STATUS_OPEN) {
            abort(403, __('Penjualan tidak dapat diedit (sudah dirilis atau dibatalkan).'));
        }

        $sale->load(['saleDetails.product', 'customer', 'payments.paymentMethod']);

        $branches = $user->isSuperAdmin()
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
                'price' => $p->selling_price,
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

        $categories = Category::orderBy('name')->get(['id', 'name']);

        return view('sales.edit', compact('sale', 'branches', 'products', 'productsForJs', 'customers', 'paymentMethods', 'categories'));
    }

    public function update(SaleRequest $request, Sale $sale): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdmin() && $user->branch_id && $sale->branch_id !== $user->branch_id) {
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
                $tradeIns
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('sales.show', $sale)->with('success', __('Draft penjualan berhasil diperbarui.'));
    }

    public function cancel(Sale $sale): RedirectResponse
    {
        $user = auth()->user();
        if (! $user->isSuperAdmin() && $user->branch_id && $sale->branch_id !== $user->branch_id) {
            abort(403, __('Unauthorized.'));
        }

        try {
            $this->saleService->cancelSale($sale);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('sales.show', $sale)->with('success', __('Penjualan berhasil dibatalkan. Unit kembali IN STOCK.'));
    }

    public function release(Request $request, Sale $sale): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdmin() && $user->branch_id && $sale->branch_id !== $user->branch_id) {
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
            $sale = $this->saleService->releaseSale(
                $sale,
                $validated['payments'],
                $validated['sale_date'],
                $user->id
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('sales.show', $sale)->with('success', __('Penjualan berhasil dirilis.'));
    }

    public function availableSerials(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
        ]);

        $user = $request->user();
        $branchId = $user->isSuperAdmin()
            ? (int) ($validated['branch_id'] ?? 0)
            : (int) $user->branch_id;

        $isSerialTracked = ProductUnit::query()
            ->where('product_id', (int) $validated['product_id'])
            ->exists();

        if (! $branchId) {
            return response()->json([
                'serial_numbers' => [],
                'is_serial_tracked' => $isSerialTracked,
            ]);
        }

        $serials = ProductUnit::query()
            ->where('product_id', (int) $validated['product_id'])
            ->where('location_type', Stock::LOCATION_BRANCH)
            ->where('location_id', $branchId)
            ->whereIn('status', [ProductUnit::STATUS_IN_STOCK, ProductUnit::STATUS_KEEP])
            ->orderBy('serial_number')
            ->limit(500)
            ->pluck('serial_number')
            ->all();

        return response()->json([
            'serial_numbers' => $serials,
            'is_serial_tracked' => $isSerialTracked,
        ]);
    }

    public function availableProducts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
        ]);

        $user = $request->user();
        $branchId = $user->isSuperAdmin()
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
            ->whereIn('id', $productIds)
            ->orderBy('sku')
            ->limit(500)
            ->get(['id', 'sku', 'brand', 'series', 'selling_price']);

        return response()->json([
            'products' => $products->map(function ($p) {
                return [
                    'id' => $p->id,
                    'sku' => $p->sku,
                    'brand' => $p->brand,
                    'series' => $p->series,
                    'price' => $p->selling_price,
                ];
            })->values(),
        ]);
    }

    public function invoice(Sale $sale): View
    {
        $user = auth()->user();
        if (! $user->isSuperAdmin() && $user->branch_id && $sale->branch_id !== $user->branch_id) {
            abort(403, __('Unauthorized.'));
        }

        $sale->load(['branch', 'user', 'customer', 'saleDetails.product', 'payments.paymentMethod', 'tradeIns.category']);

        return view('sales.invoice', compact('sale'));
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

        $customer = Customer::create([
            'name' => $name,
            'phone' => $request->input('customer_new_phone'),
            'address' => $request->input('customer_new_address'),
            'is_active' => true,
        ]);

        return (int) $customer->id;
    }
}
