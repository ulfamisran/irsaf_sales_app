<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseRequest;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Distributor;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Purchase;
use App\Models\Role;
use App\Models\Service;
use App\Models\Stock;
use App\Models\Warehouse;
use App\Services\KasBalanceService;
use App\Services\PurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class PurchaseController extends Controller
{
    public function __construct(
        protected PurchaseService $purchaseService
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        if (! $user->isSuperAdminOrAdminPusat() && ! $user->hasAnyRole([Role::ADMIN_GUDANG, Role::ADMIN_CABANG])) {
            abort(403, __('Unauthorized.'));
        }

        $query = Purchase::with(['distributor', 'warehouse', 'branch', 'user', 'details', 'payments.paymentMethod'])
            ->orderByDesc('purchase_date')
            ->orderByDesc('id');

        $canFilterLocation = false;
        $filterLocked = false;
        $locationLabel = null;

        if (! $user->isSuperAdminOrAdminPusat()) {
            if ($user->hasAnyRole([Role::ADMIN_CABANG]) && $user->branch_id) {
                $query->where('branch_id', $user->branch_id);
                $filterLocked = true;
                $locationLabel = __('Cabang') . ': ' . (Branch::find($user->branch_id)?->name ?? '');
            } elseif ($user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->warehouse_id) {
                $query->where('warehouse_id', $user->warehouse_id);
                $filterLocked = true;
                $locationLabel = __('Gudang') . ': ' . (Warehouse::find($user->warehouse_id)?->name ?? '');
            } elseif (! $user->branch_id && ! $user->warehouse_id) {
                abort(403, __('User branch or warehouse not set.'));
            }
        } else {
            $canFilterLocation = true;
            if ($request->filled('location_type')) {
                if ($request->location_type === 'warehouse' && $request->filled('warehouse_id')) {
                    $query->where('warehouse_id', $request->warehouse_id);
                } elseif ($request->location_type === 'branch' && $request->filled('branch_id')) {
                    $query->where('branch_id', $request->branch_id);
                }
            }
        }

        if ($request->filled('date_from')) {
            $query->whereDate('purchase_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('purchase_date', '<=', $request->date_to);
        }
        if ($request->filled('distributor_id')) {
            $query->where('distributor_id', $request->distributor_id);
        }
        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('distributor', fn ($d) => $d->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"));
            });
        }

        $totalUnpaid = (float) (clone $query)->reorder()
            ->where('status', '!=', Purchase::STATUS_CANCELLED)
            ->where('total', '>', 0)
            ->whereColumn('total_paid', '<', 'total')
            ->sum(DB::raw('total - total_paid'));

        $purchases = $query->paginate(20)->withQueryString();
        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);
        $branches = Branch::orderBy('name')->get(['id', 'name']);
        $distributors = Distributor::orderBy('name')->get(['id', 'name']);

        return view('purchases.index', compact('purchases', 'warehouses', 'branches', 'distributors', 'canFilterLocation', 'filterLocked', 'locationLabel', 'totalUnpaid'));
    }

    public function create(Request $request): View
    {
        $user = auth()->user();
        if (! $user->isSuperAdminOrAdminPusat() && ! $user->hasAnyRole([Role::ADMIN_GUDANG, Role::ADMIN_CABANG])) {
            abort(403, __('Unauthorized.'));
        }

        $isBranchUser = ! $user->isSuperAdminOrAdminPusat() && $user->hasAnyRole([Role::ADMIN_CABANG]);
        $isWarehouseUser = ! $user->isSuperAdminOrAdminPusat() && $user->hasAnyRole([Role::ADMIN_GUDANG]);

        $warehouses = Warehouse::orderBy('name')->get();
        $branches = Branch::orderBy('name')->get();

        $defaultLocationType = $isBranchUser ? 'branch' : ($isWarehouseUser ? 'warehouse' : 'warehouse');
        $defaultLocationId = $isBranchUser ? (int) $user->branch_id : ($isWarehouseUser ? (int) $user->warehouse_id : null);
        if (! $defaultLocationId && $user->isSuperAdminOrAdminPusat()) {
            $firstWarehouse = $warehouses->first();
            $firstBranch = $branches->first();
            if ($firstWarehouse) {
                $defaultLocationId = (int) $firstWarehouse->id;
                $defaultLocationType = 'warehouse';
            } elseif ($firstBranch) {
                $defaultLocationId = (int) $firstBranch->id;
                $defaultLocationType = 'branch';
            }
        }

        $products = $defaultLocationId
            ? $this->getProductsForPurchase($defaultLocationType, $defaultLocationId, null)
            : collect();
        $distributors = Distributor::orderBy('name')->get(['id', 'name']);
        if ($defaultLocationId) {
            if ($defaultLocationType === 'branch') {
                $distributors = Distributor::where('branch_id', $defaultLocationId)
                    ->orWhere(function ($q) {
                        $q->whereNull('branch_id')->whereNull('warehouse_id');
                    })
                    ->orderBy('name')
                    ->get(['id', 'name']);
            } else {
                $distributors = Distributor::where('warehouse_id', $defaultLocationId)
                    ->orWhere(function ($q) {
                        $q->whereNull('branch_id')->whereNull('warehouse_id');
                    })
                    ->orderBy('name')
                    ->get(['id', 'name']);
            }
        }

        $paymentMethods = collect();
        $saldoByPaymentMethod = [];
        if ($defaultLocationId) {
            $branchId = $defaultLocationType === 'branch' ? $defaultLocationId : null;
            $warehouseId = $defaultLocationType === 'warehouse' ? $defaultLocationId : null;
            $paymentMethods = PaymentMethod::query()
                ->where('is_active', true)
                ->forLocation($branchId, $warehouseId)
                ->orderBy('jenis_pembayaran')
                ->orderBy('nama_bank')
                ->get(['id', 'jenis_pembayaran', 'nama_bank', 'atas_nama_bank', 'no_rekening']);
            $saldoByPaymentMethod = (new KasBalanceService)->getSaldoPerPaymentMethodForLocation($defaultLocationType, $defaultLocationId);
        }

        $categories = Category::orderBy('name')->get(['id', 'name']);

        $openServicesInitial = collect();
        if ($defaultLocationType === 'branch' && $defaultLocationId) {
            $openServicesInitial = Service::query()
                ->with(['customer:id,name'])
                ->where('branch_id', $defaultLocationId)
                ->where('status', Service::STATUS_OPEN)
                ->orderByDesc('entry_date')
                ->orderByDesc('id')
                ->limit(200)
                ->get(['id', 'invoice_number', 'laptop_type', 'customer_id']);
        }

        return view('purchases.create', compact(
            'warehouses',
            'branches',
            'products',
            'categories',
            'distributors',
            'paymentMethods',
            'saldoByPaymentMethod',
            'isBranchUser',
            'isWarehouseUser',
            'defaultLocationType',
            'defaultLocationId',
            'openServicesInitial'
        ));
    }

    public function edit(Request $request, Purchase $purchase): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdminOrAdminPusat() && ! $user->hasAnyRole([Role::ADMIN_GUDANG, Role::ADMIN_CABANG])) {
            abort(403, __('Unauthorized.'));
        }
        if (! $user->isSuperAdminOrAdminPusat()) {
            if ($user->hasAnyRole([Role::ADMIN_CABANG]) && $purchase->branch_id !== $user->branch_id) {
                abort(403, __('Unauthorized.'));
            }
            if ($user->hasAnyRole([Role::ADMIN_GUDANG]) && $purchase->warehouse_id !== $user->warehouse_id) {
                abort(403, __('Unauthorized.'));
            }
        }

        if (! $purchase->canBeEdited()) {
            return redirect()->route('purchases.show', $purchase)
                ->with('error', __('Pembelian ini tidak dapat diubah (sudah ada pembayaran, distribusi, atau status tidak mengizinkan).'));
        }

        $purchase->load(['details.product', 'service.customer', 'warehouse', 'branch']);

        $isBranchUser = ! $user->isSuperAdminOrAdminPusat() && $user->hasAnyRole([Role::ADMIN_CABANG]);
        $isWarehouseUser = ! $user->isSuperAdminOrAdminPusat() && $user->hasAnyRole([Role::ADMIN_GUDANG]);

        $warehouses = Warehouse::orderBy('name')->get();
        $branches = Branch::orderBy('name')->get();

        $defaultLocationType = $purchase->warehouse_id ? 'warehouse' : 'branch';
        $defaultLocationId = (int) ($purchase->warehouse_id ?? $purchase->branch_id);

        $products = $defaultLocationId
            ? $this->getProductsForPurchase($defaultLocationType, $defaultLocationId, null)
            : collect();

        $distributors = Distributor::orderBy('name')->get(['id', 'name']);
        if ($defaultLocationId) {
            if ($defaultLocationType === 'branch') {
                $distributors = Distributor::where('branch_id', $defaultLocationId)
                    ->orWhere(function ($q) {
                        $q->whereNull('branch_id')->whereNull('warehouse_id');
                    })
                    ->orderBy('name')
                    ->get(['id', 'name']);
            } else {
                $distributors = Distributor::where('warehouse_id', $defaultLocationId)
                    ->orWhere(function ($q) {
                        $q->whereNull('branch_id')->whereNull('warehouse_id');
                    })
                    ->orderBy('name')
                    ->get(['id', 'name']);
            }
        }

        $paymentMethods = collect();
        $saldoByPaymentMethod = [];
        if ($defaultLocationId) {
            $branchId = $defaultLocationType === 'branch' ? $defaultLocationId : null;
            $warehouseId = $defaultLocationType === 'warehouse' ? $defaultLocationId : null;
            $paymentMethods = PaymentMethod::query()
                ->where('is_active', true)
                ->forLocation($branchId, $warehouseId)
                ->orderBy('jenis_pembayaran')
                ->orderBy('nama_bank')
                ->get(['id', 'jenis_pembayaran', 'nama_bank', 'atas_nama_bank', 'no_rekening']);
            $saldoByPaymentMethod = (new KasBalanceService)->getSaldoPerPaymentMethodForLocation($defaultLocationType, $defaultLocationId);
        }

        $categories = Category::orderBy('name')->get(['id', 'name']);

        $openServicesInitial = collect();
        if ($defaultLocationType === 'branch' && $defaultLocationId) {
            $openServicesInitial = Service::query()
                ->with(['customer:id,name'])
                ->where('branch_id', $defaultLocationId)
                ->where(function ($q) use ($purchase) {
                    $q->where('status', Service::STATUS_OPEN)
                        ->when($purchase->service_id, fn ($q2) => $q2->orWhere('id', $purchase->service_id));
                })
                ->orderByDesc('entry_date')
                ->orderByDesc('id')
                ->limit(200)
                ->get(['id', 'invoice_number', 'laptop_type', 'customer_id', 'status']);
        }

        $isEdit = true;
        $editPurchase = $purchase;

        return view('purchases.create', compact(
            'warehouses',
            'branches',
            'products',
            'categories',
            'distributors',
            'paymentMethods',
            'saldoByPaymentMethod',
            'isBranchUser',
            'isWarehouseUser',
            'defaultLocationType',
            'defaultLocationId',
            'openServicesInitial',
            'isEdit',
            'editPurchase'
        ));
    }

    public function store(PurchaseRequest $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdminOrAdminPusat() && ! $user->hasAnyRole([Role::ADMIN_GUDANG, Role::ADMIN_CABANG])) {
            abort(403, __('Unauthorized.'));
        }

        $locationType = $request->location_type;
        $locationId = (int) (($locationType === 'warehouse') ? $request->warehouse_id : $request->branch_id);

        if (! $locationId) {
            return back()->withInput()->with('error', __('Pilih gudang atau cabang terlebih dahulu.'));
        }

        $payments = array_filter($request->input('payments', []), fn ($p) => (int) ($p['payment_method_id'] ?? 0) > 0 && (float) ($p['amount'] ?? 0) > 0);

        // Catatan: validasi saldo sumber dana sengaja dihilangkan.
        // Sistem tetap mencatat transaksi, meski saldo sumber kas bisa menjadi kurang.

        try {
            $jenisPembelian = (string) $request->input('jenis_pembelian', Purchase::JENIS_PEMBELIAN_UNIT);
            $serviceId = $request->filled('service_id') ? (int) $request->service_id : null;

            $purchase = $this->purchaseService->createPurchase(
                (int) $request->distributor_id,
                $locationType,
                $locationId,
                $request->items,
                $request->purchase_date,
                $request->description,
                $request->termin,
                $request->due_date,
                $user->id,
                $payments,
                $request->invoice_number,
                $request->boolean('confirm_reuse_sold_serials'),
                $jenisPembelian,
                $serviceId
            );

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'purchase.create',
                'reference_type' => 'purchase',
                'reference_id' => $purchase->id,
                'description' => 'Pembelian ' . $purchase->invoice_number,
            ]);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('purchases.show', $purchase)->with('success', __('Pembelian berhasil dicatat.'));
    }

    public function update(PurchaseRequest $request, Purchase $purchase): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdminOrAdminPusat() && ! $user->hasAnyRole([Role::ADMIN_GUDANG, Role::ADMIN_CABANG])) {
            abort(403, __('Unauthorized.'));
        }
        if (! $user->isSuperAdminOrAdminPusat()) {
            if ($user->hasAnyRole([Role::ADMIN_CABANG]) && $purchase->branch_id !== $user->branch_id) {
                abort(403, __('Unauthorized.'));
            }
            if ($user->hasAnyRole([Role::ADMIN_GUDANG]) && $purchase->warehouse_id !== $user->warehouse_id) {
                abort(403, __('Unauthorized.'));
            }
        }

        if (! $purchase->canBeEdited()) {
            return redirect()->route('purchases.show', $purchase)
                ->with('error', __('Pembelian ini tidak dapat diubah.'));
        }

        try {
            $updated = $this->purchaseService->updatePurchase(
                $purchase,
                (int) $request->distributor_id,
                $request->items,
                $request->purchase_date,
                $request->description,
                $request->termin,
                $request->due_date,
                $user->id,
                $request->invoice_number,
                $request->boolean('confirm_reuse_sold_serials'),
            );

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'purchase.update',
                'reference_type' => 'purchase',
                'reference_id' => $updated->id,
                'description' => 'Ubah pembelian '.$updated->invoice_number,
            ]);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('purchases.show', $purchase)->with('success', __('Pembelian berhasil diperbarui.'));
    }

    public function show(Purchase $purchase): View
    {
        $user = auth()->user();
        if (! $user->isSuperAdminOrAdminPusat()) {
            if ($user->hasAnyRole([Role::ADMIN_CABANG]) && $purchase->branch_id !== $user->branch_id) {
                abort(403, __('Unauthorized.'));
            }
            if ($user->hasAnyRole([Role::ADMIN_GUDANG]) && $purchase->warehouse_id !== $user->warehouse_id) {
                abort(403, __('Unauthorized.'));
            }
        }

        $purchase->load(['distributor', 'warehouse', 'branch', 'user', 'details.product', 'payments.paymentMethod', 'payments.user', 'service']);

        return view('purchases.show', compact('purchase'));
    }

    public function addPayment(Request $request, Purchase $purchase): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdminOrAdminPusat()) {
            if ($user->hasAnyRole([Role::ADMIN_CABANG]) && $purchase->branch_id !== $user->branch_id) {
                abort(403, __('Unauthorized.'));
            }
            if ($user->hasAnyRole([Role::ADMIN_GUDANG]) && $purchase->warehouse_id !== $user->warehouse_id) {
                abort(403, __('Unauthorized.'));
            }
        }

        if ($purchase->isCancelled()) {
            return back()->with('error', __('Pembelian ini sudah dibatalkan.'));
        }
        if ($purchase->isPaidOff()) {
            return back()->with('error', __('Pembelian ini sudah lunas.'));
        }

        $paymentsInput = $request->input('payments', []);
        if (! is_array($paymentsInput)) {
            $paymentsInput = [];
        }
        $payments = [];
        foreach ($paymentsInput as $p) {
            $amount = $this->parseAmountFromInput($p['amount'] ?? 0);
            if ((int) ($p['payment_method_id'] ?? 0) > 0 && $amount > 0) {
                $p['amount'] = $amount;
                $payments[] = $p;
            }
        }
        if (empty($payments)) {
            return back()->withInput()->with('error', __('Minimal satu pembayaran harus diisi dengan nominal lebih dari 0.'));
        }

        $locationType = $purchase->warehouse_id ? 'warehouse' : 'branch';
        $locationId = (int) ($purchase->warehouse_id ?? $purchase->branch_id);
        $kasService = new KasBalanceService;
        $remaining = max(0, (float) $purchase->total - (float) ($purchase->total_paid ?? 0));
        $totalPayment = 0.0;
        $amountPerPm = [];

        foreach ($payments as $p) {
            $pmId = (int) $p['payment_method_id'];
            $amount = (float) $p['amount'];
            $totalPayment += $amount;
            $amountPerPm[$pmId] = ($amountPerPm[$pmId] ?? 0) + $amount;
        }

        if ($totalPayment > $remaining + 0.02) {
            return back()->withInput()->with('error', __('Total pembayaran tidak boleh melebihi sisa hutang: Rp :sisa', [
                'sisa' => number_format($remaining, 0, ',', '.'),
            ]));
        }

        try {
            foreach ($payments as $p) {
                $this->purchaseService->addPayment(
                    $purchase,
                    (int) $p['payment_method_id'],
                    (float) $p['amount'],
                    $p['payment_date'] ?? now()->toDateString(),
                    $user->id,
                    $p['notes'] ?? null
                );
                $purchase->refresh();
            }
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('purchases.show', $purchase)->with('success', __('Pembayaran berhasil dicatat.'));
    }

    public function cancel(Request $request, Purchase $purchase): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdminOrAdminPusat()) {
            if ($user->hasAnyRole([Role::ADMIN_CABANG]) && $purchase->branch_id !== $user->branch_id) {
                abort(403, __('Unauthorized.'));
            }
            if ($user->hasAnyRole([Role::ADMIN_GUDANG]) && $purchase->warehouse_id !== $user->warehouse_id) {
                abort(403, __('Unauthorized.'));
            }
        }

        try {
            $this->purchaseService->cancelPurchase($purchase, $user->id);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('purchases.show', $purchase)->with('success', __('Pembelian berhasil dibatalkan.'));
    }

    public function distributors(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'location_type' => ['required', 'in:branch,warehouse'],
            'location_id' => ['required', 'integer', 'min:1'],
        ]);

        $locationType = $validated['location_type'];
        $locationId = (int) $validated['location_id'];

        $query = Distributor::orderBy('name');
        if ($locationType === 'branch') {
            $query->where(function ($q) use ($locationId) {
                $q->where('branch_id', $locationId)
                    ->orWhere(function ($q2) {
                        $q2->whereNull('branch_id')->whereNull('warehouse_id');
                    });
            });
        } else {
            $query->where(function ($q) use ($locationId) {
                $q->where('warehouse_id', $locationId)
                    ->orWhere(function ($q2) {
                        $q2->whereNull('branch_id')->whereNull('warehouse_id');
                    });
            });
        }

        $distributors = $query->get(['id', 'name']);

        return response()->json([
            'distributors' => $distributors->map(fn ($d) => ['id' => $d->id, 'name' => $d->name])->values(),
        ]);
    }

    public function formData(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'location_type' => ['required', 'in:branch,warehouse'],
            'location_id' => ['required', 'integer', 'min:1'],
        ]);

        $locationType = $validated['location_type'];
        $locationId = (int) $validated['location_id'];
        $branchId = $locationType === 'branch' ? $locationId : null;
        $warehouseId = $locationType === 'warehouse' ? $locationId : null;

        $saldoByPaymentMethod = (new KasBalanceService)->getSaldoPerPaymentMethodForLocation($locationType, $locationId);

        $paymentMethods = PaymentMethod::query()
            ->where('is_active', true)
            ->forLocation($branchId, $warehouseId)
            ->orderBy('jenis_pembayaran')
            ->orderBy('nama_bank')
            ->get(['id', 'jenis_pembayaran', 'nama_bank', 'atas_nama_bank', 'no_rekening'])
            ->map(fn ($pm) => [
                'id' => $pm->id,
                'label' => $pm->display_label,
                'saldo' => (float) ($saldoByPaymentMethod[$pm->id] ?? 0),
            ])
            ->values();

        $query = Distributor::orderBy('name');
        if ($locationType === 'branch') {
            $query->where(function ($q) use ($locationId) {
                $q->where('branch_id', $locationId)
                    ->orWhere(function ($q2) {
                        $q2->whereNull('branch_id')->whereNull('warehouse_id');
                    });
            });
        } else {
            $query->where(function ($q) use ($locationId) {
                $q->where('warehouse_id', $locationId)
                    ->orWhere(function ($q2) {
                        $q2->whereNull('branch_id')->whereNull('warehouse_id');
                    });
            });
        }
        $distributors = $query->get(['id', 'name'])->map(fn ($d) => ['id' => $d->id, 'name' => $d->name])->values();

        $categoryId = $request->filled('category_id') ? (int) $request->category_id : null;
        $products = $this->getProductsForPurchase($locationType, $locationId, $categoryId);

        $openServices = collect();
        if ($locationType === 'branch') {
            $openServices = Service::query()
                ->with(['customer:id,name'])
                ->where('branch_id', $locationId)
                ->where('status', Service::STATUS_OPEN)
                ->orderByDesc('entry_date')
                ->orderByDesc('id')
                ->limit(200)
                ->get(['id', 'invoice_number', 'laptop_type', 'customer_id'])
                ->map(fn (Service $s) => [
                    'id' => $s->id,
                    'invoice_number' => $s->invoice_number,
                    'label' => $s->invoice_number
                        .' — '.($s->laptop_type ?? '')
                        .($s->customer ? ' ('.$s->customer->name.')' : ''),
                ])
                ->values();
        }

        return response()->json([
            'payment_methods' => $paymentMethods,
            'distributors' => $distributors,
            'products' => $products->map(fn ($p) => [
                'id' => $p->id,
                'sku' => $p->sku ?? '',
                'brand' => $p->brand ?? '',
                'series' => $p->series ?? '',
                'purchase_price' => (float) ($p->purchase_price ?? 0),
                'selling_price' => (float) ($p->selling_price ?? 0),
            ])->values(),
            'open_services' => $openServices,
        ]);
    }

    public function checkReusableSerials(Request $request): JsonResponse
    {
        $serials = [];
        $items = $request->input('items', []);
        if (is_array($items)) {
            foreach ($items as $item) {
                $itemSerials = [];
                if (! empty($item['serial_numbers']) && is_array($item['serial_numbers'])) {
                    $itemSerials = $item['serial_numbers'];
                } elseif (! empty($item['serial_numbers_text'])) {
                    $itemSerials = preg_split('/[\r\n,]+/', (string) $item['serial_numbers_text']) ?: [];
                }
                foreach ($itemSerials as $sn) {
                    $sn = trim((string) $sn);
                    if ($sn !== '') {
                        $serials[] = $sn;
                    }
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
            } elseif (in_array($unit->status, [
                ProductUnit::STATUS_NOT_IN_STOCK,
                ProductUnit::STATUS_CANCEL,
            ], true)) {
                continue;
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
     * Autocomplete nomor serial untuk pembelian (sumber data seluruh lokasi; lokasi unit mengikuti lokasi pembelian saat disimpan).
     */
    public function searchUnitBySerial(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $q = trim($validated['q']);
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);

        $reuseStatuses = [
            ProductUnit::STATUS_SOLD,
            ProductUnit::STATUS_NOT_IN_STOCK,
            ProductUnit::STATUS_CANCEL,
        ];

        $units = ProductUnit::query()
            ->with([
                'product:id,category_id,sku,brand,series,processor,ram,storage,color,specs,purchase_price',
                'warehouse:id,name',
                'branch:id,name',
            ])
            ->where('serial_number', 'like', '%'.$escaped.'%')
            ->whereIn('status', $reuseStatuses)
            ->orderByRaw(
                'CASE WHEN status = ? THEN 0 WHEN status IN (?, ?) THEN 1 ELSE 2 END',
                [
                    ProductUnit::STATUS_SOLD,
                    ProductUnit::STATUS_NOT_IN_STOCK,
                    ProductUnit::STATUS_CANCEL,
                ]
            )
            ->limit(20)
            ->get();

        $results = $units->map(function (ProductUnit $unit) {
            $p = $unit->product;
            $locationLabel = $unit->location_type === Stock::LOCATION_WAREHOUSE
                ? __('Gudang') . ': ' . ($unit->warehouse?->name ?? '#'.$unit->location_id)
                : __('Cabang') . ': ' . ($unit->branch?->name ?? '#'.$unit->location_id);

            return [
                'product_id' => (int) $unit->product_id,
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
                'purchase_price' => $p ? (float) ($p->purchase_price ?? 0) : 0.0,
                'harga_hpp' => $unit->harga_hpp !== null ? (float) $unit->harga_hpp : null,
                'status' => $unit->status,
                'location_type' => $unit->location_type,
                'location_id' => (int) $unit->location_id,
                'location_label' => $locationLabel,
            ];
        })->values();

        return response()->json(['results' => $results]);
    }

    /**
     * Get products for purchase form, filtered by location and optional category.
     */
    private function getProductsForPurchase(string $locationType, ?int $locationId, ?int $categoryId)
    {
        $locType = $locationType === 'branch' ? Product::LOCATION_BRANCH : Product::LOCATION_WAREHOUSE;

        $query = Product::with('category', 'distributor')
            ->where('location_type', $locType)
            ->orderBy('sku');

        if ($locationId) {
            $query->where('location_id', $locationId);
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        return $query->get(['id', 'sku', 'brand', 'series', 'category_id', 'distributor_id', 'purchase_price', 'selling_price']);
    }

    /**
     * Parse amount from input - supports "57199996.08" and "57.199.996,08" (format Indonesia).
     */
    private function parseAmountFromInput(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        $str = (string) $value;
        $str = str_replace('.', '', $str);
        $str = str_replace(',', '.', $str);

        return (float) $str;
    }
}
