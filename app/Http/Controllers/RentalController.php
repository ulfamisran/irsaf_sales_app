<?php

namespace App\Http\Controllers;

use App\Http\Requests\RentalRequest;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Rental;
use App\Models\Stock;
use App\Models\Warehouse;
use App\Services\RentalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use InvalidArgumentException;

class RentalController extends Controller
{
    public function __construct(
        protected RentalService $rentalService
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $query = Rental::with(['branch', 'customer', 'warehouse', 'user'])
            ->orderByDesc('pickup_date')
            ->orderByDesc('id');

        if (! $user->isSuperAdmin()) {
            $isBranchUser = $user->hasAnyRole([\App\Models\Role::ADMIN_CABANG, \App\Models\Role::KASIR]);
            if ($isBranchUser) {
                if (! $user->branch_id) {
                    abort(403, __('User branch not set.'));
                }
                $query->where('branch_id', $user->branch_id);
            }
        }

        if ($request->filled('date_from')) {
            $query->whereDate('pickup_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('pickup_date', '<=', $request->date_to);
        }
        if ($request->filled('return_status')) {
            $query->where('return_status', $request->return_status);
        }

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        $totalRental = (float) (clone $query)
            ->where('status', '!=', Rental::STATUS_CANCEL)
            ->sum('total');
        $paymentMethods = PaymentMethod::query()
            ->where('is_active', true)
            ->orderBy('jenis_pembayaran')
            ->orderBy('nama_bank')
            ->orderBy('no_rekening')
            ->get(['id', 'jenis_pembayaran', 'nama_bank', 'no_rekening']);
        $paymentMethodTotals = DB::table('rental_payments')
            ->join('rentals', 'rental_payments.rental_id', '=', 'rentals.id')
            ->when(! $user->isSuperAdmin(), function ($q) use ($user) {
                $isBranchUser = $user->hasAnyRole([\App\Models\Role::ADMIN_CABANG, \App\Models\Role::KASIR]);
                if ($isBranchUser && $user->branch_id) {
                    $q->where('rentals.branch_id', $user->branch_id);
                }
            })
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('rentals.warehouse_id', $request->warehouse_id))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('rentals.pickup_date', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('rentals.pickup_date', '<=', $request->date_to))
            ->when($request->filled('return_status'), fn ($q) => $q->where('rentals.return_status', $request->return_status))
            ->where('rentals.status', '!=', Rental::STATUS_CANCEL)
            ->selectRaw('rental_payments.payment_method_id, SUM(rental_payments.amount) as total')
            ->groupBy('rental_payments.payment_method_id')
            ->pluck('total', 'rental_payments.payment_method_id');
        $rentals = $query->paginate(20)->withQueryString();
        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);

        return view('rentals.index', compact('rentals', 'warehouses', 'totalRental', 'paymentMethods', 'paymentMethodTotals'));
    }

    public function create(): View
    {
        $user = auth()->user();
        if (! $user->isSuperAdmin() && ! $user->branch_id && ! $user->hasAnyRole([\App\Models\Role::STAFF_GUDANG])) {
            abort(403, __('User branch not set.'));
        }

        $warehouses = Warehouse::orderBy('name')->get();
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

        return view('rentals.create', compact('warehouses', 'customers', 'paymentMethods'));
    }

    public function store(RentalRequest $request): RedirectResponse
    {
        try {
            $user = $request->user();
            $branchId = $user->isSuperAdmin()
                ? (int) Branch::orderBy('id')->value('id')
                : (int) $user->branch_id;
            if (! $branchId && $user->hasAnyRole([\App\Models\Role::STAFF_GUDANG])) {
                $branchId = (int) Branch::orderBy('id')->value('id');
            }

            if (! $branchId) {
                abort(403, __('Branch is required.'));
            }

            $customerId = $this->resolveCustomerId($request);

            $rental = $this->rentalService->create(
                $branchId,
                (int) $request->warehouse_id,
                $customerId,
                $request->pickup_date,
                $request->return_date,
                $request->input('items', []),
                (float) $request->input('tax_amount', 0),
                (float) $request->input('penalty_amount', 0),
                $request->input('payments', []),
                $request->description,
                $user->id
            );
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('rentals.show', $rental)->with('success', __('Penyewaan berhasil disimpan.'));
    }

    public function show(Rental $rental): View
    {
        $user = auth()->user();
        if (! $user->isSuperAdmin() && $user->branch_id && $rental->branch_id !== $user->branch_id) {
            abort(403, __('Unauthorized.'));
        }

        $rental->load(['branch', 'warehouse', 'user', 'customer', 'items.product', 'payments.paymentMethod']);
        $paymentMethods = PaymentMethod::query()
            ->where('is_active', true)
            ->orderBy('jenis_pembayaran')
            ->orderBy('nama_bank')
            ->orderBy('id')
            ->get(['id', 'jenis_pembayaran', 'nama_bank', 'atas_nama_bank', 'no_rekening']);

        return view('rentals.show', compact('rental', 'paymentMethods'));
    }

    public function addPayment(Request $request, Rental $rental): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdmin() && $user->branch_id && $rental->branch_id !== $user->branch_id) {
            abort(403, __('Unauthorized.'));
        }

        $validated = $request->validate([
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.payment_method_id' => ['required', 'exists:payment_methods,id'],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'payments.*.notes' => ['nullable', 'string'],
        ]);

        try {
            $this->rentalService->addPayment($rental, $validated['payments'], $user->id);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('rentals.show', $rental)->with('success', __('Pembayaran berhasil ditambahkan.'));
    }

    public function markReturned(Rental $rental): RedirectResponse
    {
        $user = auth()->user();
        if (! $user->isSuperAdmin() && $user->branch_id && $rental->branch_id !== $user->branch_id) {
            abort(403, __('Unauthorized.'));
        }

        $validated = request()->validate([
            'payments' => ['nullable', 'array'],
            'payments.*.payment_method_id' => ['required_with:payments', 'exists:payment_methods,id'],
            'payments.*.amount' => ['required_with:payments', 'numeric', 'min:0.01'],
            'payments.*.notes' => ['nullable', 'string'],
        ]);

        try {
            $this->rentalService->markReturned(
                $rental,
                $validated['payments'] ?? [],
                $user->id
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('rentals.show', $rental)->with('success', __('Penyewaan ditandai kembali.'));
    }

    public function invoice(Rental $rental): View
    {
        $user = auth()->user();
        if (! $user->isSuperAdmin() && $user->branch_id && $rental->branch_id !== $user->branch_id) {
            abort(403, __('Unauthorized.'));
        }

        $rental->load(['branch', 'warehouse', 'user', 'customer', 'items.product', 'payments.paymentMethod']);

        return view('rentals.invoice', compact('rental'));
    }

    public function availableProducts(Request $request): JsonResponse
    {
        $warehouseId = (int) $request->get('warehouse_id');
        if ($warehouseId <= 0) {
            return response()->json(['products' => []]);
        }

        $products = Product::query()
            ->select('products.id', 'products.sku', 'products.brand', 'products.series', 'products.laptop_type')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->where('products.laptop_type', 'bekas')
            ->where(function ($q) {
                $q->where('categories.code', 'LAP')
                    ->orWhere('categories.name', 'Laptop');
            })
            ->whereIn('products.id', function ($q) use ($warehouseId) {
                $q->select('product_id')
                    ->from('product_units')
                    ->where('location_type', Stock::LOCATION_WAREHOUSE)
                    ->where('location_id', $warehouseId)
                    ->where('status', ProductUnit::STATUS_IN_STOCK);
            })
            ->orderBy('products.sku')
            ->get();

        $products = $products->map(fn ($p) => [
            'id' => $p->id,
            'sku' => $p->sku,
            'brand' => $p->brand,
            'series' => $p->series,
        ])->values();

        return response()->json(['products' => $products]);
    }

    public function availableSerials(Request $request): JsonResponse
    {
        $warehouseId = (int) $request->get('warehouse_id');
        $productId = (int) $request->get('product_id');
        if ($warehouseId <= 0 || $productId <= 0) {
            return response()->json(['serial_numbers' => []]);
        }

        $serials = ProductUnit::query()
            ->where('product_id', $productId)
            ->where('location_type', Stock::LOCATION_WAREHOUSE)
            ->where('location_id', $warehouseId)
            ->where('status', ProductUnit::STATUS_IN_STOCK)
            ->orderBy('serial_number')
            ->pluck('serial_number')
            ->all();

        return response()->json([
            'serial_numbers' => $serials,
            'is_serial_tracked' => true,
        ]);
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
