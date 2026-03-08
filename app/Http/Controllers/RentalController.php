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
use App\Models\AuditLog;
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

        $canFilterLocation = false;
        $filterLocked = false;
        $locationLabel = null;

        if (! $user->isSuperAdminOrAdminPusat()) {
            if ($user->hasAnyRole([\App\Models\Role::ADMIN_CABANG, \App\Models\Role::KASIR]) && $user->branch_id) {
                $query->where('branch_id', $user->branch_id);
                $filterLocked = true;
                $branch = Branch::find($user->branch_id);
                $locationLabel = __('Cabang') . ': ' . ($branch?->name ?? '#' . $user->branch_id);
            } elseif ($user->hasAnyRole([\App\Models\Role::ADMIN_GUDANG]) && $user->warehouse_id) {
                $query->where('warehouse_id', $user->warehouse_id);
                $filterLocked = true;
                $warehouse = Warehouse::find($user->warehouse_id);
                $locationLabel = __('Gudang') . ': ' . ($warehouse?->name ?? '#' . $user->warehouse_id);
            } elseif (! $user->branch_id && ! $user->warehouse_id) {
                abort(403, __('User branch or warehouse not set.'));
            }
        } else {
            $canFilterLocation = true;
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

        if ($user->isSuperAdminOrAdminPusat()) {
            if ($request->filled('warehouse_id')) {
                $query->where('warehouse_id', $request->warehouse_id);
            }
            if ($request->filled('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }
        }

        $totalRental = (float) (clone $query)
            ->whereIn('status', [Rental::STATUS_OPEN, Rental::STATUS_RELEASED])
            ->sum('total');
        $pmBranchId = $user->hasAnyRole([\App\Models\Role::ADMIN_CABANG, \App\Models\Role::KASIR]) && $user->branch_id
            ? (int) $user->branch_id
            : ($user->isSuperAdminOrAdminPusat() && $request->filled('branch_id') ? (int) $request->branch_id : null);
        $pmWarehouseId = $user->hasAnyRole([\App\Models\Role::ADMIN_GUDANG]) && $user->warehouse_id
            ? (int) $user->warehouse_id
            : ($user->isSuperAdminOrAdminPusat() && $request->filled('warehouse_id') ? (int) $request->warehouse_id : null);
        $paymentMethods = PaymentMethod::query()
            ->where('is_active', true)
            ->forLocation($pmBranchId, $pmWarehouseId)
            ->orderBy('jenis_pembayaran')
            ->orderBy('nama_bank')
            ->orderBy('no_rekening')
            ->get(['id', 'jenis_pembayaran', 'nama_bank', 'no_rekening']);
        $paymentMethodTotals = DB::table('rental_payments')
            ->join('rentals', 'rental_payments.rental_id', '=', 'rentals.id')
            ->when($user->hasAnyRole([\App\Models\Role::ADMIN_CABANG, \App\Models\Role::KASIR]) && $user->branch_id, fn ($q) => $q->where('rentals.branch_id', $user->branch_id))
            ->when($user->hasAnyRole([\App\Models\Role::ADMIN_GUDANG]) && $user->warehouse_id, fn ($q) => $q->where('rentals.warehouse_id', $user->warehouse_id))
            ->when($user->isSuperAdminOrAdminPusat() && $request->filled('warehouse_id'), fn ($q) => $q->where('rentals.warehouse_id', $request->warehouse_id))
            ->when($user->isSuperAdminOrAdminPusat() && $request->filled('branch_id'), fn ($q) => $q->where('rentals.branch_id', $request->branch_id))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('rentals.pickup_date', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('rentals.pickup_date', '<=', $request->date_to))
            ->when($request->filled('return_status'), fn ($q) => $q->where('rentals.return_status', $request->return_status))
            ->whereIn('rentals.status', [Rental::STATUS_OPEN, Rental::STATUS_RELEASED])
            ->selectRaw('rental_payments.payment_method_id, SUM(rental_payments.amount) as total')
            ->groupBy('rental_payments.payment_method_id')
            ->pluck('total', 'rental_payments.payment_method_id');
        $rentals = $query->paginate(20)->withQueryString();
        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);
        $branches = Branch::orderBy('name')->get(['id', 'name']);

        return view('rentals.index', compact('rentals', 'warehouses', 'branches', 'canFilterLocation', 'filterLocked', 'locationLabel', 'totalRental', 'paymentMethods', 'paymentMethodTotals'));
    }

    public function create(): View
    {
        $user = auth()->user();
        if (! $user->isSuperAdminOrAdminPusat() && ! $user->branch_id && ! $user->warehouse_id) {
            abort(403, __('User branch or warehouse not set.'));
        }

        $warehouses = Warehouse::orderBy('name')->get();
        $branches = Branch::orderBy('name')->get();
        if (! $user->isSuperAdminOrAdminPusat() && $user->hasAnyRole([\App\Models\Role::ADMIN_GUDANG]) && $user->warehouse_id) {
            $warehouses = $warehouses->where('id', $user->warehouse_id)->values();
        }
        if (! $user->isSuperAdminOrAdminPusat() && $user->hasAnyRole([\App\Models\Role::ADMIN_CABANG, \App\Models\Role::KASIR]) && $user->branch_id) {
            $branches = $branches->where('id', $user->branch_id)->values();
        }

        $defaultLocationType = null;
        $defaultLocationId = null;
        if (! $user->isSuperAdminOrAdminPusat() && $user->warehouse_id) {
            $defaultLocationType = 'warehouse';
            $defaultLocationId = (int) $user->warehouse_id;
        } elseif (! $user->isSuperAdminOrAdminPusat() && $user->branch_id) {
            $defaultLocationType = 'branch';
            $defaultLocationId = (int) $user->branch_id;
        }

        $customers = collect();
        $paymentMethods = collect();
        if ($defaultLocationId) {
            $paymentMethods = PaymentMethod::query()
                ->where('is_active', true)
                ->forLocation(
                    $defaultLocationType === 'branch' ? $defaultLocationId : null,
                    $defaultLocationType === 'warehouse' ? $defaultLocationId : null
                )
                ->orderBy('jenis_pembayaran')
                ->orderBy('nama_bank')
                ->orderBy('id')
                ->get(['id', 'jenis_pembayaran', 'nama_bank', 'atas_nama_bank', 'no_rekening']);
            $customers = Customer::query()
                ->where('is_active', true)
                ->when($defaultLocationType === 'branch', fn ($q) => $q->where('branch_id', $defaultLocationId))
                ->when($defaultLocationType === 'warehouse', fn ($q) => $q->where('warehouse_id', $defaultLocationId))
                ->orderBy('name')
                ->limit(500)
                ->get(['id', 'name', 'phone']);
        }

        return view('rentals.create', compact('warehouses', 'branches', 'customers', 'paymentMethods', 'defaultLocationType', 'defaultLocationId'));
    }

    public function store(RentalRequest $request): RedirectResponse
    {
        try {
            $customerId = $this->resolveCustomerId($request);

            $rental = $this->rentalService->create(
                $request->location_type,
                (int) $request->location_id,
                $customerId,
                $request->pickup_date,
                $request->return_date,
                $request->input('items', []),
                (float) $request->input('tax_amount', 0),
                (float) $request->input('penalty_amount', 0),
                $request->input('payments', []),
                $request->description,
                $request->user()->id
            );
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('rentals.show', $rental)->with('success', __('Penyewaan berhasil disimpan.'));
    }

    public function show(Rental $rental): View
    {
        $user = auth()->user();
        if (! $user->isSuperAdminOrAdminPusat() && $user->branch_id && $rental->branch_id !== $user->branch_id) {
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

    public function edit(Rental $rental): View
    {
        $user = auth()->user();
        if (! $user->isSuperAdminOrAdminPusat()) {
            abort(403, __('Unauthorized.'));
        }
        if ($rental->status !== Rental::STATUS_OPEN) {
            abort(403, __('Penyewaan tidak dapat diedit (sudah dirilis atau dibatalkan).'));
        }

        $rental->load(['items.product', 'customer', 'payments.paymentMethod']);
        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);
        $branches = Branch::orderBy('name')->get(['id', 'name']);
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

        return view('rentals.edit', compact('rental', 'warehouses', 'branches', 'customers', 'paymentMethods'));
    }

    public function update(RentalRequest $request, Rental $rental): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdminOrAdminPusat()) {
            abort(403, __('Unauthorized.'));
        }
        if ($rental->status !== Rental::STATUS_OPEN) {
            return back()->with('error', __('Penyewaan tidak dapat diedit (sudah dirilis atau dibatalkan).'));
        }

        try {
            $customerId = $this->resolveCustomerId($request);
            $rental = $this->rentalService->update(
                $rental,
                $request->location_type,
                (int) $request->location_id,
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
            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'rental.update',
                'reference_type' => 'rental',
                'reference_id' => $rental->id,
                'description' => 'Update penyewaan ' . $rental->invoice_number,
            ]);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('rentals.show', $rental)->with('success', __('Penyewaan berhasil diperbarui.'));
    }

    public function addPayment(Request $request, Rental $rental): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdminOrAdminPusat() && $user->branch_id && $rental->branch_id !== $user->branch_id) {
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
        if (! $user->isSuperAdminOrAdminPusat() && $user->branch_id && $rental->branch_id !== $user->branch_id) {
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

    public function cancel(Request $request, Rental $rental): RedirectResponse
    {
        $user = auth()->user();
        if (! $user->isSuperAdminOrAdminPusat()) {
            abort(403, __('Unauthorized.'));
        }
        if (! in_array($rental->status, [Rental::STATUS_OPEN, Rental::STATUS_RELEASED], true)) {
            return back()->with('error', __('Penyewaan tidak dapat dibatalkan.'));
        }

        try {
            $validated = $request->validate([
                'cancel_reason' => ['required', 'string', 'max:255'],
                'confirm_released' => ['nullable', 'boolean'],
            ]);
            if ($rental->status === Rental::STATUS_RELEASED && empty($validated['confirm_released'])) {
                return back()->with('error', __('Konfirmasi tambahan wajib untuk membatalkan transaksi released.'));
            }
            $this->rentalService->cancel($rental, $user->id, $validated['cancel_reason']);
            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'rental.cancel',
                'reference_type' => 'rental',
                'reference_id' => $rental->id,
                'description' => 'Cancel penyewaan ' . $rental->invoice_number . '. Alasan: ' . $validated['cancel_reason'],
            ]);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('rentals.show', $rental)->with('success', __('Penyewaan berhasil dibatalkan.'));
    }

    public function invoice(Rental $rental): View
    {
        $user = auth()->user();
        if (! $user->isSuperAdminOrAdminPusat() && $user->branch_id && $rental->branch_id !== $user->branch_id) {
            abort(403, __('Unauthorized.'));
        }

        $rental->load(['branch', 'warehouse', 'user', 'customer', 'items.product', 'payments.paymentMethod']);

        return view('rentals.invoice', compact('rental'));
    }

    public function availableProducts(Request $request): JsonResponse
    {
        $locationType = $request->get('location_type', 'warehouse');
        $locationId = (int) $request->get('location_id');
        $rentalId = (int) $request->get('rental_id');
        $brand = $request->get('brand');
        $series = $request->get('series');
        if ($locationId <= 0 || ! in_array($locationType, [Stock::LOCATION_WAREHOUSE, Stock::LOCATION_BRANCH], true)) {
            return response()->json(['products' => []]);
        }

        $productIds = ProductUnit::query()
            ->where('location_type', $locationType)
            ->where('location_id', $locationId)
            ->where('status', ProductUnit::STATUS_IN_STOCK)
            ->pluck('product_id')
            ->all();
        if ($rentalId > 0) {
            $rentalProductIds = \App\Models\RentalItem::where('rental_id', $rentalId)
                ->pluck('product_id')
                ->all();
            $productIds = array_values(array_unique(array_merge($productIds, $rentalProductIds)));
        }

        $query = Product::query()
            ->select('products.id', 'products.sku', 'products.brand', 'products.series', 'products.laptop_type', 'products.color')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->where('products.is_active', true)
            ->where('products.laptop_type', 'bekas')
            ->where(function ($q) {
                $q->where('categories.code', 'LAP')
                    ->orWhere('categories.name', 'like', '%Laptop%');
            })
            ->whereIn('products.id', $productIds)
            ->withCount([
                'units as in_stock_count' => fn ($q) => $q
                    ->where('location_type', $locationType)
                    ->where('location_id', $locationId)
                    ->where('status', ProductUnit::STATUS_IN_STOCK),
            ])
            ->orderBy('products.brand')
            ->orderBy('products.series')
            ->orderBy('products.sku');

        if ($brand) {
            $query->where('products.brand', $brand);
        }
        if ($series) {
            $query->where('products.series', $series);
        }

        $products = $query->limit(500)->get();

        $products = $products->map(fn ($p) => [
            'id' => $p->id,
            'sku' => $p->sku,
            'brand' => $p->brand,
            'series' => $p->series,
            'color' => $p->color,
            'in_stock_count' => $p->in_stock_count ?? 0,
        ])->values();

        return response()->json(['products' => $products]);
    }

    public function availableSerials(Request $request): JsonResponse
    {
        $locationType = $request->get('location_type', 'warehouse');
        $locationId = (int) $request->get('location_id');
        $productId = (int) $request->get('product_id');
        $rentalId = (int) $request->get('rental_id');
        if ($locationId <= 0 || $productId <= 0 || ! in_array($locationType, [Stock::LOCATION_WAREHOUSE, Stock::LOCATION_BRANCH], true)) {
            return response()->json(['serial_numbers' => []]);
        }

        $serials = ProductUnit::query()
            ->where('product_id', $productId)
            ->where('location_type', $locationType)
            ->where('location_id', $locationId)
            ->where('status', ProductUnit::STATUS_IN_STOCK)
            ->orderBy('serial_number')
            ->pluck('serial_number')
            ->all();
        if ($rentalId > 0) {
            $currentSerials = \App\Models\RentalItem::where('rental_id', $rentalId)
                ->where('product_id', $productId)
                ->pluck('serial_number')
                ->all();
            $serials = array_values(array_unique(array_merge($serials, $currentSerials)));
        }

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

        $user = $request->user();
        $locationType = $request->input('location_type');
        $locationId = (int) $request->input('location_id');
        $branchId = $locationType === 'branch' ? $locationId : null;
        $warehouseId = $locationType === 'warehouse' ? $locationId : null;

        $customer = Customer::create([
            'name' => $name,
            'phone' => $request->input('customer_new_phone'),
            'address' => $request->input('customer_new_address'),
            'is_active' => true,
            'placement_type' => $branchId ? Customer::PLACEMENT_CABANG : Customer::PLACEMENT_GUDANG,
            'branch_id' => $branchId,
            'warehouse_id' => $warehouseId,
        ]);

        return (int) $customer->id;
    }
}
