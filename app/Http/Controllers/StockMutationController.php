<?php

namespace App\Http\Controllers;

use App\Http\Requests\StockMutationRequest;
use App\Models\Branch;
use App\Models\CashFlow;
use App\Models\Category;
use App\Models\IncomeCategory;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Role;
use App\Models\Stock;
use App\Models\StockMutation;
use App\Models\Warehouse;
use App\Services\PurchaseService;
use App\Services\StockMutationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use InvalidArgumentException;

class StockMutationController extends Controller
{
    public function __construct(
        protected StockMutationService $stockMutationService,
        protected PurchaseService $purchaseService
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $activeTab = $request->get('tab', 'invoices');

        $filterLocked = false;
        $locationType = null;
        $locationId = null;
        $locationLabel = null;

        $accessConstraint = null;
        if (! $user->isSuperAdminOrAdminPusat()) {
            if ($user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR]) && $user->branch_id) {
                $branchId = (int) $user->branch_id;
                $accessConstraint = function ($q) use ($branchId) {
                    $q->where(function ($q2) use ($branchId) {
                        $q2->where(function ($q3) use ($branchId) {
                            $q3->where('from_location_type', Stock::LOCATION_BRANCH)->where('from_location_id', $branchId);
                        })->orWhere(function ($q3) use ($branchId) {
                            $q3->where('to_location_type', Stock::LOCATION_BRANCH)->where('to_location_id', $branchId);
                        });
                    });
                };
                $filterLocked = true;
                $branch = Branch::find($branchId);
                $locationType = 'branch';
                $locationId = $branchId;
                $locationLabel = __('Cabang') . ': ' . ($branch?->name ?? '#' . $branchId);
            } elseif ($user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->warehouse_id) {
                $warehouseId = (int) $user->warehouse_id;
                $accessConstraint = function ($q) use ($warehouseId) {
                    $q->where(function ($q2) use ($warehouseId) {
                        $q2->where(function ($q3) use ($warehouseId) {
                            $q3->where('from_location_type', Stock::LOCATION_WAREHOUSE)->where('from_location_id', $warehouseId);
                        })->orWhere(function ($q3) use ($warehouseId) {
                            $q3->where('to_location_type', Stock::LOCATION_WAREHOUSE)->where('to_location_id', $warehouseId);
                        });
                    });
                };
                $filterLocked = true;
                $warehouse = Warehouse::find($warehouseId);
                $locationType = 'warehouse';
                $locationId = $warehouseId;
                $locationLabel = __('Gudang') . ': ' . ($warehouse?->name ?? '#' . $warehouseId);
            } elseif (! $user->branch_id && ! $user->warehouse_id) {
                abort(403, __('User branch or warehouse not set.'));
            }
        }

        $applyFilters = function ($query) use ($user, $request, $accessConstraint) {
            if ($accessConstraint) {
                $accessConstraint($query);
            }
            if ($user->isSuperAdminOrAdminPusat() && $request->filled('location_type') && $request->filled('location_id')) {
                $locType = (string) $request->location_type;
                $locId = (int) $request->location_id;
                if (in_array($locType, [Stock::LOCATION_WAREHOUSE, Stock::LOCATION_BRANCH]) && $locId > 0) {
                    $query->where(function ($q) use ($locType, $locId) {
                        $q->where(function ($q2) use ($locType, $locId) {
                            $q2->where('from_location_type', $locType)->where('from_location_id', $locId);
                        })->orWhere(function ($q2) use ($locType, $locId) {
                            $q2->where('to_location_type', $locType)->where('to_location_id', $locId);
                        });
                    });
                }
            }
            if ($request->filled('product_id')) {
                $query->where('product_id', $request->product_id);
            }
            if ($request->filled('search')) {
                $term = '%' . $request->search . '%';
                $query->where(function ($q) use ($term) {
                    $q->whereHas('product', function ($q2) use ($term) {
                        $q2->where('sku', 'like', $term)
                            ->orWhere('brand', 'like', $term)
                            ->orWhere('series', 'like', $term);
                    })->orWhere('serial_numbers', 'like', $term);
                });
            }
            if ($request->filled('date_from')) {
                $query->whereDate('mutation_date', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->whereDate('mutation_date', '<=', $request->date_to);
            }
        };

        // === RIWAYAT TAB (per-product mutations) ===
        $riwayatQuery = StockMutation::with(['product', 'user', 'purchase'])
            ->orderByDesc('mutation_date')
            ->orderByDesc('id');
        $applyFilters($riwayatQuery);
        $mutations = $riwayatQuery->paginate(20, ['*'], 'page')->withQueryString();

        // === INVOICE TAB (grouped by invoice_number) ===
        $invoiceQuery = StockMutation::query()
            ->whereNotNull('invoice_number')
            ->where('invoice_number', '!=', '')
            ->select(
                'invoice_number',
                DB::raw('MIN(id) as first_id'),
                DB::raw('MAX(mutation_date) as inv_date'),
                DB::raw('COUNT(*) as product_count')
            )
            ->groupBy('invoice_number')
            ->orderByDesc(DB::raw('MAX(mutation_date)'))
            ->orderByDesc(DB::raw('MAX(id)'));
        $applyFilters($invoiceQuery);
        $invoices = $invoiceQuery->paginate(20, ['*'], 'inv_page')->withQueryString();

        $invNumbers = $invoices->pluck('invoice_number')->filter()->unique()->values()->all();
        $invMutations = collect();
        $invBiaya = collect();
        $invPaid = collect();

        if (! empty($invNumbers)) {
            $invMutations = StockMutation::with(['product'])
                ->whereIn('invoice_number', $invNumbers)
                ->orderBy('id')
                ->get()
                ->groupBy('invoice_number');

            $invBiaya = $invMutations->map(
                fn ($g) => $g->sum(fn ($m) => (float) ($m->biaya_distribusi_per_unit ?? 0) * (int) $m->quantity)
            );

            $allInvIds = $invMutations->flatten()->pluck('id')->all();
            $invRefToInvoice = $invMutations->flatten()->pluck('invoice_number', 'id');
            $cfByRef = ! empty($allInvIds)
                ? CashFlow::where('reference_type', CashFlow::REFERENCE_DISTRIBUTION)
                    ->whereIn('reference_id', $allInvIds)
                    ->selectRaw('reference_id, SUM(amount) as total_paid')
                    ->groupBy('reference_id')
                    ->pluck('total_paid', 'reference_id')
                : collect();

            foreach ($cfByRef as $refId => $paid) {
                $invNo = $invRefToInvoice[$refId] ?? null;
                if ($invNo) {
                    $invPaid[$invNo] = ($invPaid[$invNo] ?? 0) + (float) $paid;
                }
            }
        }

        // === RIWAYAT payment status ===
        $riwayatItems = $mutations->getCollection();
        $riwayatInvNumbers = $riwayatItems->pluck('invoice_number')->filter()->unique()->values()->all();
        $invoiceBiaya = collect();
        $invoicePaid = collect();

        if (! empty($riwayatInvNumbers)) {
            $groupMutations = StockMutation::whereIn('invoice_number', $riwayatInvNumbers)
                ->get(['id', 'invoice_number', 'quantity', 'biaya_distribusi_per_unit']);

            $invoiceBiaya = $groupMutations->groupBy('invoice_number')->map(
                fn ($g) => $g->sum(fn ($m) => (float) ($m->biaya_distribusi_per_unit ?? 0) * (int) $m->quantity)
            );

            $allGroupIds = $groupMutations->pluck('id')->all();
            $refToInvoice = $groupMutations->pluck('invoice_number', 'id');
            $cfByRef2 = ! empty($allGroupIds)
                ? CashFlow::where('reference_type', CashFlow::REFERENCE_DISTRIBUTION)
                    ->whereIn('reference_id', $allGroupIds)
                    ->selectRaw('reference_id, SUM(amount) as total_paid')
                    ->groupBy('reference_id')
                    ->pluck('total_paid', 'reference_id')
                : collect();

            foreach ($cfByRef2 as $refId => $paid) {
                $invNo = $refToInvoice[$refId] ?? null;
                if ($invNo) {
                    $invoicePaid[$invNo] = ($invoicePaid[$invNo] ?? 0) + (float) $paid;
                }
            }
        }

        // === Location name lookups (for both tabs) ===
        $allItems = $riwayatItems->merge($invMutations->flatten());
        $warehouseIds = $allItems->flatMap(function ($m) {
            $ids = [];
            if ($m->from_location_type === Stock::LOCATION_WAREHOUSE) {
                $ids[] = (int) $m->from_location_id;
            }
            if ($m->to_location_type === Stock::LOCATION_WAREHOUSE) {
                $ids[] = (int) $m->to_location_id;
            }

            return $ids;
        })->filter()->unique()->values();

        $branchIds = $allItems->flatMap(function ($m) {
            $ids = [];
            if ($m->from_location_type === Stock::LOCATION_BRANCH) {
                $ids[] = (int) $m->from_location_id;
            }
            if ($m->to_location_type === Stock::LOCATION_BRANCH) {
                $ids[] = (int) $m->to_location_id;
            }

            return $ids;
        })->filter()->unique()->values();

        $warehousesById = $warehouseIds->isNotEmpty()
            ? Warehouse::query()->whereIn('id', $warehouseIds)->pluck('name', 'id')
            : collect();
        $branchesById = $branchIds->isNotEmpty()
            ? Branch::query()->whereIn('id', $branchIds)->pluck('name', 'id')
            : collect();

        $products = Product::withCount([
            'units as in_stock_count' => fn ($q) => $q->where('status', ProductUnit::STATUS_IN_STOCK),
        ])->having('in_stock_count', '>', 0)->orderBy('sku')->get(['id', 'sku', 'brand']);

        $branches = Branch::orderBy('name')->get(['id', 'name']);
        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);
        $canFilterLocation = $user->isSuperAdminOrAdminPusat();

        return view('stock-mutations.index', compact(
            'activeTab',
            'mutations',
            'invoices',
            'invMutations',
            'invBiaya',
            'invPaid',
            'products',
            'warehousesById',
            'branchesById',
            'branches',
            'warehouses',
            'canFilterLocation',
            'filterLocked',
            'locationType',
            'locationId',
            'locationLabel',
            'invoiceBiaya',
            'invoicePaid'
        ));
    }

    public function invoice(StockMutation $stockMutation): View
    {
        $user = auth()->user();
        if (! $user->isSuperAdminOrAdminPusat()) {
            if ($user->branch_id) {
                $involved = ($stockMutation->from_location_type === Stock::LOCATION_BRANCH && (int) $stockMutation->from_location_id === (int) $user->branch_id)
                    || ($stockMutation->to_location_type === Stock::LOCATION_BRANCH && (int) $stockMutation->to_location_id === (int) $user->branch_id);
                if (! $involved) {
                    abort(403, __('Unauthorized.'));
                }
            }
            if ($user->warehouse_id) {
                $involved = ($stockMutation->from_location_type === Stock::LOCATION_WAREHOUSE && (int) $stockMutation->from_location_id === (int) $user->warehouse_id)
                    || ($stockMutation->to_location_type === Stock::LOCATION_WAREHOUSE && (int) $stockMutation->to_location_id === (int) $user->warehouse_id);
                if (! $involved) {
                    abort(403, __('Unauthorized.'));
                }
            }
        }

        $allMutations = StockMutation::where('invoice_number', $stockMutation->invoice_number)
            ->with(['product', 'user'])
            ->orderBy('id')
            ->get();

        $mutationIds = $allMutations->pluck('id')->all();
        $cashFlows = CashFlow::where('reference_type', CashFlow::REFERENCE_DISTRIBUTION)
            ->whereIn('reference_id', $mutationIds)
            ->with('paymentMethod')
            ->orderBy('id')
            ->get();

        $fromLocation = $stockMutation->from_location_type === Stock::LOCATION_WAREHOUSE
            ? Warehouse::find($stockMutation->from_location_id)
            : Branch::find($stockMutation->from_location_id);
        $toLocation = $stockMutation->to_location_type === Stock::LOCATION_WAREHOUSE
            ? Warehouse::find($stockMutation->to_location_id)
            : Branch::find($stockMutation->to_location_id);

        return view('stock-mutations.invoice', compact(
            'stockMutation',
            'allMutations',
            'cashFlows',
            'fromLocation',
            'toLocation'
        ));
    }

    public function addPayment(StockMutation $stockMutation): View|RedirectResponse
    {
        $user = auth()->user();
        $this->authorizeDistributionAccess($stockMutation, $user);

        $groupMutations = StockMutation::where('invoice_number', $stockMutation->invoice_number)->get();
        $totalBiaya = $groupMutations->sum(fn ($m) => (float) ($m->biaya_distribusi_per_unit ?? 0) * (int) $m->quantity);
        if ($totalBiaya <= 0) {
            return redirect()->route('stock-mutations.index')->with('info', __('Distribusi ini tidak memiliki biaya.'));
        }

        $groupIds = $groupMutations->pluck('id')->all();
        $totalPaid = (float) CashFlow::where('reference_type', CashFlow::REFERENCE_DISTRIBUTION)
            ->whereIn('reference_id', $groupIds)
            ->sum('amount');
        $sisa = max(0, $totalBiaya - $totalPaid);
        if ($sisa < 0.01) {
            return redirect()->route('stock-mutations.index')->with('info', __('Pembayaran sudah lunas.'));
        }

        $branchId = $stockMutation->from_location_type === Stock::LOCATION_BRANCH ? (int) $stockMutation->from_location_id : null;
        $warehouseId = $stockMutation->from_location_type === Stock::LOCATION_WAREHOUSE ? (int) $stockMutation->from_location_id : null;

        $paymentMethods = PaymentMethod::query()
            ->where('is_active', true)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->when(! $branchId && ! $warehouseId, fn ($q) => $q->whereRaw('1=0'))
            ->orderBy('jenis_pembayaran')
            ->orderBy('nama_bank')
            ->get(['id', 'jenis_pembayaran', 'nama_bank', 'atas_nama_bank', 'no_rekening']);

        $stockMutation->load(['product', 'user']);
        $fromLocation = $stockMutation->from_location_type === Stock::LOCATION_WAREHOUSE
            ? Warehouse::find($stockMutation->from_location_id)
            : Branch::find($stockMutation->from_location_id);
        $toLocation = $stockMutation->to_location_type === Stock::LOCATION_WAREHOUSE
            ? Warehouse::find($stockMutation->to_location_id)
            : Branch::find($stockMutation->to_location_id);

        return view('stock-mutations.add-payment', compact(
            'stockMutation',
            'paymentMethods',
            'totalBiaya',
            'totalPaid',
            'sisa',
            'fromLocation',
            'toLocation'
        ));
    }

    public function storePayment(Request $request, StockMutation $stockMutation): RedirectResponse
    {
        $user = auth()->user();
        $this->authorizeDistributionAccess($stockMutation, $user);

        $groupMutations = StockMutation::where('invoice_number', $stockMutation->invoice_number)->get();
        $totalBiaya = $groupMutations->sum(fn ($m) => (float) ($m->biaya_distribusi_per_unit ?? 0) * (int) $m->quantity);
        $groupIds = $groupMutations->pluck('id')->all();
        $totalPaid = (float) CashFlow::where('reference_type', CashFlow::REFERENCE_DISTRIBUTION)
            ->whereIn('reference_id', $groupIds)
            ->sum('amount');
        $sisa = max(0, $totalBiaya - $totalPaid);

        $validated = $request->validate([
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:' . ($sisa + 0.02)],
            'transaction_date' => ['nullable', 'date'],
        ]);

        $amount = round((float) $validated['amount'], 2);
        $pmId = (int) $validated['payment_method_id'];
        $transactionDate = $validated['transaction_date'] ?? $stockMutation->mutation_date?->toDateString() ?? now()->toDateString();

        $pm = PaymentMethod::find($pmId);
        $branchId = $stockMutation->from_location_type === Stock::LOCATION_BRANCH ? (int) $stockMutation->from_location_id : null;
        $warehouseId = $stockMutation->from_location_type === Stock::LOCATION_WAREHOUSE ? (int) $stockMutation->from_location_id : null;
        $validPm = ($branchId && (int) ($pm->branch_id ?? 0) === $branchId)
            || ($warehouseId && (int) ($pm->warehouse_id ?? 0) === $warehouseId);
        if (! $validPm) {
            return back()->withInput()->with('error', __('Metode pembayaran harus dari lokasi asal.'));
        }

        $incomeCategory = IncomeCategory::firstOrCreate(
            ['code' => 'DIST-BRG'],
            [
                'name' => 'Distribusi Barang',
                'description' => 'Pemasukan dari biaya distribusi barang antar lokasi',
                'is_active' => true,
            ]
        );

        CashFlow::create([
            'branch_id' => $branchId,
            'warehouse_id' => $warehouseId,
            'type' => CashFlow::TYPE_IN,
            'amount' => $amount,
            'description' => __('Distribusi Barang') . ' #' . $stockMutation->id . ' - ' . ($pm->display_label ?? __('Pembayaran')),
            'reference_type' => CashFlow::REFERENCE_DISTRIBUTION,
            'reference_id' => $stockMutation->id,
            'income_category_id' => $incomeCategory->id,
            'payment_method_id' => $pmId,
            'transaction_date' => $transactionDate,
            'user_id' => $user->id,
        ]);

        return redirect()->route('stock-mutations.index')
            ->with('success', __('Pembayaran berhasil dicatat.'));
    }

    private function authorizeDistributionAccess(StockMutation $stockMutation, $user): void
    {
        if ($user->isSuperAdminOrAdminPusat()) {
            return;
        }
        if ($user->branch_id) {
            $involved = ($stockMutation->from_location_type === Stock::LOCATION_BRANCH && (int) $stockMutation->from_location_id === (int) $user->branch_id)
                || ($stockMutation->to_location_type === Stock::LOCATION_BRANCH && (int) $stockMutation->to_location_id === (int) $user->branch_id);
            if (! $involved) {
                abort(403, __('Unauthorized.'));
            }
            return;
        }
        if ($user->warehouse_id) {
            $involved = ($stockMutation->from_location_type === Stock::LOCATION_WAREHOUSE && (int) $stockMutation->from_location_id === (int) $user->warehouse_id)
                || ($stockMutation->to_location_type === Stock::LOCATION_WAREHOUSE && (int) $stockMutation->to_location_id === (int) $user->warehouse_id);
            if (! $involved) {
                abort(403, __('Unauthorized.'));
            }
        }
    }

    /**
     * Auto-fill PurchasePayments on destination Purchases by matching payment methods.
     */
    private function autoFillDestinationPurchasePayments(
        array $mutations,
        array $distributionPayments,
        string $toLocationType,
        int $toLocationId,
        string $mutationDate,
        int $userId,
        string $invoiceNumber
    ): void {
        $toBranchId = $toLocationType === Stock::LOCATION_BRANCH ? $toLocationId : null;
        $toWarehouseId = $toLocationType === Stock::LOCATION_WAREHOUSE ? $toLocationId : null;

        $destPaymentMethods = PaymentMethod::where('is_active', true)
            ->when($toBranchId, fn ($q) => $q->where('branch_id', $toBranchId))
            ->when($toWarehouseId, fn ($q) => $q->where('warehouse_id', $toWarehouseId))
            ->when(! $toBranchId && ! $toWarehouseId, fn ($q) => $q->whereRaw('1=0'))
            ->get();

        if ($destPaymentMethods->isEmpty()) {
            return;
        }

        $matchedPayments = [];
        foreach ($distributionPayments as $dp) {
            $originPm = PaymentMethod::find($dp['payment_method_id']);
            if (! $originPm) {
                continue;
            }

            $destPm = $destPaymentMethods->first(fn ($pm) => strtolower(trim($pm->jenis_pembayaran ?? '')) === strtolower(trim($originPm->jenis_pembayaran ?? ''))
                && strtolower(trim($pm->nama_bank ?? '')) === strtolower(trim($originPm->nama_bank ?? '')));

            if (! $destPm) {
                $destPm = $destPaymentMethods->first(fn ($pm) => strtolower(trim($pm->jenis_pembayaran ?? '')) === strtolower(trim($originPm->jenis_pembayaran ?? '')));
            }

            if ($destPm) {
                $matchedPayments[] = [
                    'payment_method_id' => $destPm->id,
                    'amount' => $dp['amount'],
                ];
            }
        }

        if (empty($matchedPayments)) {
            return;
        }

        $purchases = [];
        foreach ($mutations as $m) {
            $m->load('purchase');
            if ($m->purchase && (float) $m->purchase->total > 0) {
                $purchases[] = $m->purchase;
            }
        }

        if (empty($purchases)) {
            return;
        }

        foreach ($matchedPayments as $mp) {
            $remaining = round($mp['amount'], 2);

            foreach ($purchases as $purchase) {
                if ($remaining <= 0) {
                    break;
                }

                $purchaseRemaining = round((float) $purchase->total - (float) $purchase->total_paid, 2);
                if ($purchaseRemaining <= 0) {
                    continue;
                }

                $payAmount = round(min($remaining, $purchaseRemaining), 2);
                $this->purchaseService->addPayment(
                    $purchase,
                    $mp['payment_method_id'],
                    $payAmount,
                    $mutationDate,
                    $userId,
                    __('Auto dari distribusi') . ' ' . $invoiceNumber
                );
                $purchase->refresh();
                $remaining = round($remaining - $payAmount, 2);
            }
        }
    }

    public function create(): View
    {
        $user = auth()->user();
        if (! $user->isSuperAdminOrAdminPusat() && ! $user->hasAnyRole([Role::ADMIN_GUDANG, Role::ADMIN_CABANG])) {
            abort(403, __('Unauthorized.'));
        }

        $categories = Category::orderBy('name')->get(['id', 'name']);
        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);
        $branches = Branch::orderBy('name')->get(['id', 'name']);

        $lockFromLocation = false;
        $defaultFromLocationType = old('from_location_type');
        $defaultFromLocationId = old('from_location_id');
        $fromLocationLabel = null;

        if (! $user->isSuperAdminOrAdminPusat() && $user->hasAnyRole([Role::ADMIN_GUDANG, Role::ADMIN_CABANG])) {
            $lockFromLocation = true;
            if ($user->warehouse_id) {
                $defaultFromLocationType = Stock::LOCATION_WAREHOUSE;
                $defaultFromLocationId = (int) $user->warehouse_id;
                $wh = Warehouse::find($defaultFromLocationId);
                $fromLocationLabel = __('Gudang') . ': ' . ($wh?->name ?? '#' . $defaultFromLocationId);
            } elseif ($user->branch_id) {
                $defaultFromLocationType = Stock::LOCATION_BRANCH;
                $defaultFromLocationId = (int) $user->branch_id;
                $br = Branch::find($defaultFromLocationId);
                $fromLocationLabel = __('Cabang') . ': ' . ($br?->name ?? '#' . $defaultFromLocationId);
            }
        }

        return view('stock-mutations.create', compact(
            'categories',
            'warehouses',
            'branches',
            'lockFromLocation',
            'defaultFromLocationType',
            'defaultFromLocationId',
            'fromLocationLabel'
        ));
    }

    public function availableProducts(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdminOrAdminPusat() && ! $user->hasAnyRole([Role::ADMIN_GUDANG, Role::ADMIN_CABANG])) {
            abort(403, __('Unauthorized.'));
        }

        $validated = $request->validate([
            'from_location_type' => ['required', 'in:'.Stock::LOCATION_WAREHOUSE.','.Stock::LOCATION_BRANCH],
            'from_location_id' => ['required', 'integer', 'min:1'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        $fromType = (string) $validated['from_location_type'];
        $fromId = (int) $validated['from_location_id'];
        $categoryId = $validated['category_id'] ?? null;

        $productIds = ProductUnit::query()
            ->where('location_type', $fromType)
            ->where('location_id', $fromId)
            ->where('status', ProductUnit::STATUS_IN_STOCK)
            ->select('product_id')
            ->distinct()
            ->pluck('product_id');

        $query = Product::query()
            ->whereIn('id', $productIds)
            ->where('is_active', true)
            ->withCount([
                'units as in_stock_count' => fn ($q) => $q
                    ->where('location_type', $fromType)
                    ->where('location_id', $fromId)
                    ->where('status', ProductUnit::STATUS_IN_STOCK),
            ])
            ->orderBy('brand')
            ->orderBy('series')
            ->orderBy('sku');

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $products = $query->limit(500)->get(['id', 'sku', 'brand', 'series', 'color', 'selling_price', 'category_id']);

        return response()->json([
            'products' => $products->map(fn ($p) => [
                'id' => $p->id,
                'sku' => $p->sku ?? '',
                'brand' => $p->brand ?? '',
                'series' => $p->series ?? '',
                'color' => $p->color ?? '',
                'selling_price' => $p->selling_price,
                'in_stock_count' => $p->in_stock_count ?? 0,
            ])->values(),
        ]);
    }

    public function availableSerials(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdminOrAdminPusat() && ! $user->hasAnyRole([Role::ADMIN_GUDANG, Role::ADMIN_CABANG])) {
            abort(403, __('Unauthorized.'));
        }

        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'from_location_type' => ['required', 'in:'.Stock::LOCATION_WAREHOUSE.','.Stock::LOCATION_BRANCH],
            'from_location_id' => ['required', 'integer', 'min:1'],
        ]);

        $limit = 500;
        $query = ProductUnit::query()
            ->where('product_id', (int) $validated['product_id'])
            ->where('location_type', (string) $validated['from_location_type'])
            ->where('location_id', (int) $validated['from_location_id'])
            ->where('status', ProductUnit::STATUS_IN_STOCK);

        $total = (clone $query)->count();
        $serials = $query
            ->orderBy('id')
            ->limit($limit)
            ->pluck('serial_number')
            ->all();

        return response()->json([
            'serial_numbers' => $serials,
            'total_available' => $total,
            'truncated' => $total > $limit,
        ]);
    }

    public function store(StockMutationRequest $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdminOrAdminPusat() && ! $user->hasAnyRole([Role::ADMIN_GUDANG, Role::ADMIN_CABANG])) {
            abort(403, __('Unauthorized.'));
        }

        try {
            $fromLocationType = $request->from_location_type;
            $fromLocationId = (int) $request->from_location_id;

            if (! $user->isSuperAdminOrAdminPusat() && $user->hasAnyRole([Role::ADMIN_GUDANG, Role::ADMIN_CABANG])) {
                if ($user->warehouse_id) {
                    $fromLocationType = Stock::LOCATION_WAREHOUSE;
                    $fromLocationId = (int) $user->warehouse_id;
                } elseif ($user->branch_id) {
                    $fromLocationType = Stock::LOCATION_BRANCH;
                    $fromLocationId = (int) $user->branch_id;
                }
            }

            $items = collect($request->input('items', []))->values()->all();
            if (empty($items)) {
                return back()->withInput()->with('error', __('Minimal 1 produk harus ditambahkan.'));
            }

            $invoiceNumber = $this->stockMutationService->generateDistributionInvoiceNumber();
            $mutations = [];

            DB::beginTransaction();

            foreach ($items as $item) {
                $product = Product::findOrFail($item['product_id']);
                $serialNumbers = $this->normalizeSerialNumbersInput($item['serial_numbers'] ?? null);
                $quantity = ! empty($serialNumbers) ? count($serialNumbers) : (int) ($item['quantity'] ?? 0);
                $biayaDistribusi = (float) ($item['biaya_distribusi_per_unit'] ?? 0);

                if ($biayaDistribusi > 0 && empty($serialNumbers)) {
                    throw new \InvalidArgumentException(
                        __('Distribusi dengan biaya wajib menggunakan nomor serial untuk produk: ') . ($product->sku ?? $product->id)
                    );
                }

                $mutation = $this->stockMutationService->mutate(
                    $product,
                    $fromLocationType,
                    $fromLocationId,
                    $request->to_location_type,
                    (int) $request->to_location_id,
                    $quantity,
                    $request->mutation_date,
                    $request->notes,
                    $user->id,
                    $serialNumbers,
                    $biayaDistribusi,
                    [],
                    $invoiceNumber
                );
                $mutations[] = $mutation;
            }

            $distributionPayments = collect($request->input('distribution_payments', []))
                ->filter(fn ($p) => ! empty($p['payment_method_id']) && (float) ($p['amount'] ?? 0) > 0)
                ->map(fn ($p) => [
                    'payment_method_id' => (int) $p['payment_method_id'],
                    'amount' => round((float) $p['amount'], 2),
                ])
                ->values()
                ->all();

            $totalBiaya = collect($mutations)->sum(fn ($m) => (float) $m->biaya_distribusi_per_unit * (int) $m->quantity);

            if ($totalBiaya > 0 && ! empty($distributionPayments)) {
                $firstMutation = $mutations[0];
                $incomeCategory = IncomeCategory::firstOrCreate(
                    ['code' => 'DIST-BRG'],
                    [
                        'name' => 'Distribusi Barang',
                        'description' => 'Pemasukan dari biaya distribusi barang antar lokasi',
                        'is_active' => true,
                    ]
                );

                $fromBranchId = $fromLocationType === Stock::LOCATION_BRANCH ? $fromLocationId : null;
                $fromWarehouseId = $fromLocationType === Stock::LOCATION_WAREHOUSE ? $fromLocationId : null;

                foreach ($distributionPayments as $payment) {
                    $pmId = (int) ($payment['payment_method_id'] ?? 0);
                    $amount = round((float) ($payment['amount'] ?? 0), 2);
                    if ($pmId <= 0 || $amount <= 0) {
                        continue;
                    }

                    $pm = PaymentMethod::find($pmId);
                    if ($pm) {
                        $pmBranch = (int) ($pm->branch_id ?? 0);
                        $pmWarehouse = (int) ($pm->warehouse_id ?? 0);
                        $validPm = ($fromLocationType === Stock::LOCATION_BRANCH && $pmBranch === $fromLocationId)
                            || ($fromLocationType === Stock::LOCATION_WAREHOUSE && $pmWarehouse === $fromLocationId);
                        if (! $validPm) {
                            throw new \InvalidArgumentException(__('Metode pembayaran harus dari lokasi asal.'));
                        }
                    }

                    CashFlow::create([
                        'branch_id' => $fromBranchId,
                        'warehouse_id' => $fromWarehouseId,
                        'type' => CashFlow::TYPE_IN,
                        'amount' => $amount,
                        'description' => __('Distribusi Barang') . ' ' . $invoiceNumber . ' - ' . ($pm?->display_label ?? __('Pembayaran')),
                        'reference_type' => CashFlow::REFERENCE_DISTRIBUTION,
                        'reference_id' => $firstMutation->id,
                        'income_category_id' => $incomeCategory->id,
                        'payment_method_id' => $pmId,
                        'transaction_date' => $request->mutation_date,
                        'user_id' => $user->id,
                    ]);
                }

                $this->autoFillDestinationPurchasePayments(
                    $mutations,
                    $distributionPayments,
                    $request->to_location_type,
                    (int) $request->to_location_id,
                    $request->mutation_date,
                    $user->id,
                    $invoiceNumber
                );
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('stock-mutations.index')->with('success', __('Distribusi stok berhasil dibuat.'));
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
     * @param  mixed  $input
     * @return array<int, string>
     */
    private function normalizeSerialNumbersInput(mixed $input): array
    {
        if (is_array($input)) {
            $out = [];
            foreach ($input as $sn) {
                $sn = trim((string) $sn);
                if ($sn === '') {
                    continue;
                }
                $out[] = $sn;
            }
            return $this->ensureUniqueSerials($out);
        }

        if (is_string($input)) {
            // Backward compatibility (old textarea input)
            return $this->parseSerialNumbers($input);
        }

        return [];
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
