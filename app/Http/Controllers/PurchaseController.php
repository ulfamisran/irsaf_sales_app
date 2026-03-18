<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseRequest;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Distributor;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Role;
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
        if ($defaultLocationId) {
            $branchId = $isBranchUser ? $defaultLocationId : null;
            $warehouseId = $isWarehouseUser ? $defaultLocationId : null;
            $paymentMethods = PaymentMethod::query()
                ->where('is_active', true)
                ->forLocation($branchId, $warehouseId)
                ->orderBy('jenis_pembayaran')
                ->orderBy('nama_bank')
                ->get(['id', 'jenis_pembayaran', 'nama_bank', 'atas_nama_bank', 'no_rekening']);
        }

        $categories = Category::orderBy('name')->get(['id', 'name']);

        return view('purchases.create', compact(
            'warehouses',
            'branches',
            'products',
            'categories',
            'distributors',
            'paymentMethods',
            'isBranchUser',
            'isWarehouseUser',
            'defaultLocationType',
            'defaultLocationId'
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

        if (! empty($payments)) {
            $kasService = new KasBalanceService;
            foreach ($payments as $p) {
                $pmId = (int) $p['payment_method_id'];
                $amount = (float) $p['amount'];
                $saldo = $kasService->getSaldoForLocation(
                    $locationType === 'warehouse' ? 'warehouse' : 'branch',
                    $locationId,
                    $pmId
                );
                if ($amount > $saldo) {
                    return back()->withInput()->with('error', __('Saldo tidak mencukupi untuk pembayaran. Saldo tersedia: Rp :saldo', [
                        'saldo' => number_format($saldo, 0, ',', '.'),
                    ]));
                }
            }
        }

        try {
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
                $request->invoice_number
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

        $purchase->load(['distributor', 'warehouse', 'branch', 'user', 'details.product', 'payments.paymentMethod', 'payments.user']);

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
            $saldo = $kasService->getSaldoForLocation($locationType, $locationId, $pmId);
            if ($amountPerPm[$pmId] > $saldo) {
                return back()->withInput()->with('error', __('Saldo tidak mencukupi untuk sumber dana yang dipilih. Saldo tersedia: Rp :saldo', [
                    'saldo' => number_format($saldo, 0, ',', '.'),
                ]));
            }
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

        $paymentMethods = PaymentMethod::query()
            ->where('is_active', true)
            ->forLocation($branchId, $warehouseId)
            ->orderBy('jenis_pembayaran')
            ->orderBy('nama_bank')
            ->get(['id', 'jenis_pembayaran', 'nama_bank', 'atas_nama_bank', 'no_rekening'])
            ->map(fn ($pm) => ['id' => $pm->id, 'label' => $pm->display_label])
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

        return response()->json([
            'payment_methods' => $paymentMethods,
            'distributors' => $distributors,
            'products' => $products->map(fn ($p) => [
                'id' => $p->id,
                'sku' => $p->sku ?? '',
                'brand' => $p->brand ?? '',
                'series' => $p->series ?? '',
                'purchase_price' => (float) ($p->purchase_price ?? 0),
            ])->values(),
        ]);
    }

    /**
     * Get products for purchase form, filtered by location and optional category.
     */
    private function getProductsForPurchase(string $locationType, ?int $locationId, ?int $categoryId)
    {
        $locType = $locationType === 'branch' ? Product::LOCATION_BRANCH : Product::LOCATION_WAREHOUSE;

        $query = Product::with('category', 'distributor')
            ->where('is_active', true)
            ->where('location_type', $locType)
            ->orderBy('sku');

        if ($locationId) {
            $query->where('location_id', $locationId);
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        return $query->get(['id', 'sku', 'brand', 'series', 'category_id', 'distributor_id', 'purchase_price']);
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
