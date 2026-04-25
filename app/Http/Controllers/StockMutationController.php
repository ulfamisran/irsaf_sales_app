<?php

namespace App\Http\Controllers;

use App\Http\Requests\StockMutationRequest;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\CashFlow;
use App\Models\Category;
use App\Models\Distribution;
use App\Models\DistributionDetail;
use App\Models\DistributionPayment;
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

        $applyFilters = function ($query, bool $isDistribution = true) use ($user, $request, $accessConstraint) {
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
                if ($isDistribution) {
                    $query->whereHas('details', fn ($q) => $q->where('product_id', $request->product_id));
                } else {
                    $query->where('product_id', $request->product_id);
                }
            }
            if ($request->filled('search')) {
                $term = '%' . $request->search . '%';
                if ($isDistribution) {
                    $query->where(function ($q) use ($term) {
                        $q->where('invoice_number', 'like', $term)
                            ->orWhereHas('details.product', function ($q2) use ($term) {
                                $q2->where('sku', 'like', $term)
                                    ->orWhere('brand', 'like', $term)
                                    ->orWhere('series', 'like', $term);
                            })
                            ->orWhereHas('details', fn ($q2) => $q2->where('serial_numbers', 'like', $term));
                    });
                } else {
                    $query->where(function ($q) use ($term) {
                        $q->whereHas('product', function ($q2) use ($term) {
                            $q2->where('sku', 'like', $term)
                                ->orWhere('brand', 'like', $term)
                                ->orWhere('series', 'like', $term);
                        })->orWhere('serial_numbers', 'like', $term);
                    });
                }
            }
            if ($request->filled('status') && $isDistribution) {
                $status = (string) $request->status;
                if (in_array($status, [Distribution::STATUS_ACTIVE, Distribution::STATUS_CANCELLED], true)) {
                    $query->where('status', $status);
                } elseif ($status === 'paid_off') {
                    $query->where('status', '!=', Distribution::STATUS_CANCELLED)
                        ->where(function ($q) {
                            $q->where('total', '<=', 0)
                                ->orWhereRaw('total_paid >= (total - 0.02)');
                        });
                } elseif ($status === 'unpaid') {
                    $query->where('status', '!=', Distribution::STATUS_CANCELLED)
                        ->where('total', '>', 0)
                        ->whereRaw('total_paid < (total - 0.02)');
                }
            }
            $dateField = $isDistribution ? 'distribution_date' : 'mutation_date';
            if ($request->filled('date_from')) {
                $query->whereDate($dateField, '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->whereDate($dateField, '<=', $request->date_to);
            }
        };

        // === INVOICE TAB (distributions) ===
        $invoiceQuery = Distribution::with(['details.product', 'payments.paymentMethod', 'user', 'cancelUser'])
            ->orderByDesc('distribution_date')
            ->orderByDesc('id');
        $applyFilters($invoiceQuery, true);
        $distributions = $invoiceQuery->paginate(20, ['*'], 'inv_page')->withQueryString();

        // === RIWAYAT TAB (per-product from distribution_details) ===
        $riwayatQuery = DistributionDetail::with(['distribution.user', 'distribution.cancelUser', 'product'])
            ->whereHas('distribution')
            ->orderByDesc(
                Distribution::select('distribution_date')
                    ->whereColumn('distributions.id', 'distribution_details.distribution_id')
                    ->limit(1)
            )
            ->orderByDesc('id');

        if ($accessConstraint || $request->filled('location_type') || $request->filled('date_from') || $request->filled('date_to')) {
            $riwayatQuery->whereHas('distribution', function ($q) use ($applyFilters) {
                $applyFilters($q, true);
            });
        }
        if ($request->filled('product_id')) {
            $riwayatQuery->where('product_id', $request->product_id);
        }
        if ($request->filled('search')) {
            $term = '%' . $request->search . '%';
            $riwayatQuery->where(function ($q) use ($term) {
                $q->where('serial_numbers', 'like', $term)
                    ->orWhereHas('product', function ($q2) use ($term) {
                        $q2->where('sku', 'like', $term)
                            ->orWhere('brand', 'like', $term)
                            ->orWhere('series', 'like', $term);
                    });
            });
        }
        $riwayatDetails = $riwayatQuery->paginate(20, ['*'], 'page')->withQueryString();

        // === Location name lookups ===
        $allDistributions = $distributions->getCollection();
        $riwayatDistributions = $riwayatDetails->getCollection()->map(fn ($d) => $d->distribution)->filter()->unique('id');
        $allDists = $allDistributions->merge($riwayatDistributions);

        $warehouseIds = $allDists->flatMap(function ($d) {
            $ids = [];
            if ($d->from_location_type === Stock::LOCATION_WAREHOUSE) {
                $ids[] = (int) $d->from_location_id;
            }
            if ($d->to_location_type === Stock::LOCATION_WAREHOUSE) {
                $ids[] = (int) $d->to_location_id;
            }
            return $ids;
        })->filter()->unique()->values();

        $branchIds = $allDists->flatMap(function ($d) {
            $ids = [];
            if ($d->from_location_type === Stock::LOCATION_BRANCH) {
                $ids[] = (int) $d->from_location_id;
            }
            if ($d->to_location_type === Stock::LOCATION_BRANCH) {
                $ids[] = (int) $d->to_location_id;
            }
            return $ids;
        })->filter()->unique()->values();

        $warehousesById = $warehouseIds->isNotEmpty()
            ? Warehouse::whereIn('id', $warehouseIds)->pluck('name', 'id')
            : collect();
        $branchesById = $branchIds->isNotEmpty()
            ? Branch::whereIn('id', $branchIds)->pluck('name', 'id')
            : collect();

        $products = Product::withCount([
            'units as in_stock_count' => fn ($q) => $q->where('status', ProductUnit::STATUS_IN_STOCK),
        ])->having('in_stock_count', '>', 0)->orderBy('sku')->get(['id', 'sku', 'brand']);

        $branches = Branch::orderBy('name')->get(['id', 'name']);
        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);
        $canFilterLocation = $user->isSuperAdminOrAdminPusat();
        $canCancelDistribution = $user->isSuperAdminOrAdminPusat();

        return view('stock-mutations.index', compact(
            'activeTab',
            'distributions',
            'riwayatDetails',
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
            'canCancelDistribution'
        ));
    }

    public function invoice(Distribution $distribution): View
    {
        $user = auth()->user();
        $this->authorizeDistributionAccess($distribution, $user);

        return view('stock-mutations.invoice', $this->distributionInvoiceViewData($distribution));
    }

    public function showCancel(Distribution $distribution): View|RedirectResponse
    {
        $user = auth()->user();
        if (! $user->isSuperAdminOrAdminPusat()) {
            abort(403, __('Unauthorized.'));
        }

        if ($distribution->isCancelled()) {
            return redirect()->route('stock-mutations.index')
                ->with('info', __('Distribusi ini sudah dibatalkan.'));
        }

        return view('stock-mutations.cancel', $this->distributionInvoiceViewData($distribution));
    }

    public function cancel(Request $request, Distribution $distribution): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdminOrAdminPusat()) {
            abort(403, __('Unauthorized.'));
        }

        $validated = $request->validate([
            'cancel_reason' => ['required', 'string', 'max:255'],
        ]);

        try {
            $this->stockMutationService->cancelDistribution(
                $distribution,
                (int) $user->id,
                $validated['cancel_reason']
            );
            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'distribution.cancel',
                'reference_type' => 'distribution',
                'reference_id' => $distribution->id,
                'description' => 'Cancel distribusi ' . $distribution->invoice_number . '. Alasan: ' . $validated['cancel_reason'],
            ]);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('stock-mutations.index')->with('success', __('Distribusi berhasil dibatalkan.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function distributionInvoiceViewData(Distribution $distribution): array
    {
        $distribution->load(['details.product', 'payments.paymentMethod', 'user', 'cancelUser']);

        $fromLocation = $distribution->from_location_type === Stock::LOCATION_WAREHOUSE
            ? Warehouse::find($distribution->from_location_id)
            : Branch::find($distribution->from_location_id);
        $toLocation = $distribution->to_location_type === Stock::LOCATION_WAREHOUSE
            ? Warehouse::find($distribution->to_location_id)
            : Branch::find($distribution->to_location_id);

        $totalBiaya = (float) $distribution->total;
        $totalPaid = (float) $distribution->total_paid;
        $isLunas = $totalBiaya <= 0 || ($totalPaid + 0.02 >= $totalBiaya);

        return [
            'distribution' => $distribution,
            'fromLocation' => $fromLocation,
            'toLocation' => $toLocation,
            'totalBiaya' => $totalBiaya,
            'totalPaid' => $totalPaid,
            'isLunas' => $isLunas,
        ];
    }

    public function addPayment(Distribution $distribution): View|RedirectResponse
    {
        $user = auth()->user();
        $this->authorizeDistributionAccess($distribution, $user);

        if ($distribution->isCancelled()) {
            return redirect()->route('stock-mutations.index')->with('error', __('Distribusi ini sudah dibatalkan.'));
        }
        $totalBiaya = (float) $distribution->total;
        if ($totalBiaya <= 0) {
            return redirect()->route('stock-mutations.index')->with('info', __('Distribusi ini tidak memiliki biaya.'));
        }

        $totalPaid = (float) $distribution->total_paid;
        $sisa = max(0, $totalBiaya - $totalPaid);
        if ($sisa < 0.01) {
            return redirect()->route('stock-mutations.index')->with('info', __('Pembayaran sudah lunas.'));
        }

        $branchId = $distribution->from_location_type === Stock::LOCATION_BRANCH ? (int) $distribution->from_location_id : null;
        $warehouseId = $distribution->from_location_type === Stock::LOCATION_WAREHOUSE ? (int) $distribution->from_location_id : null;

        $paymentMethods = PaymentMethod::query()
            ->where('is_active', true)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->when(! $branchId && ! $warehouseId, fn ($q) => $q->whereRaw('1=0'))
            ->orderBy('jenis_pembayaran')
            ->orderBy('nama_bank')
            ->get(['id', 'jenis_pembayaran', 'nama_bank', 'atas_nama_bank', 'no_rekening']);

        $fromLocation = $distribution->from_location_type === Stock::LOCATION_WAREHOUSE
            ? Warehouse::find($distribution->from_location_id)
            : Branch::find($distribution->from_location_id);
        $toLocation = $distribution->to_location_type === Stock::LOCATION_WAREHOUSE
            ? Warehouse::find($distribution->to_location_id)
            : Branch::find($distribution->to_location_id);

        return view('stock-mutations.add-payment', compact(
            'distribution',
            'paymentMethods',
            'totalBiaya',
            'totalPaid',
            'sisa',
            'fromLocation',
            'toLocation'
        ));
    }

    public function storePayment(Request $request, Distribution $distribution): RedirectResponse
    {
        $user = auth()->user();
        $this->authorizeDistributionAccess($distribution, $user);

        if ($distribution->isCancelled()) {
            return redirect()->route('stock-mutations.index')->with('error', __('Distribusi ini sudah dibatalkan.'));
        }
        $totalBiaya = (float) $distribution->total;
        $totalPaid = (float) $distribution->total_paid;
        $sisa = max(0, $totalBiaya - $totalPaid);

        $validated = $request->validate([
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:' . ($sisa + 0.02)],
            'transaction_date' => ['nullable', 'date'],
        ]);

        $amount = round((float) $validated['amount'], 2);
        $pmId = (int) $validated['payment_method_id'];
        $transactionDate = $validated['transaction_date'] ?? $distribution->distribution_date?->toDateString() ?? now()->toDateString();

        $pm = PaymentMethod::find($pmId);
        $branchId = $distribution->from_location_type === Stock::LOCATION_BRANCH ? (int) $distribution->from_location_id : null;
        $warehouseId = $distribution->from_location_type === Stock::LOCATION_WAREHOUSE ? (int) $distribution->from_location_id : null;
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

        DB::transaction(function () use ($distribution, $pmId, $amount, $transactionDate, $branchId, $warehouseId, $incomeCategory, $pm, $user) {
            DistributionPayment::create([
                'distribution_id' => $distribution->id,
                'payment_method_id' => $pmId,
                'amount' => $amount,
            ]);

            CashFlow::create([
                'branch_id' => $branchId,
                'warehouse_id' => $warehouseId,
                'type' => CashFlow::TYPE_IN,
                'amount' => $amount,
                'description' => __('Distribusi Barang') . ' ' . $distribution->invoice_number . ' - ' . ($pm->display_label ?? __('Pembayaran')),
                'reference_type' => CashFlow::REFERENCE_DISTRIBUTION,
                'reference_id' => $distribution->id,
                'income_category_id' => $incomeCategory->id,
                'payment_method_id' => $pmId,
                'transaction_date' => $transactionDate,
                'user_id' => $user->id,
            ]);

            $distribution->update([
                'total_paid' => (float) $distribution->total_paid + $amount,
            ]);
        });

        return redirect()->route('stock-mutations.index')
            ->with('success', __('Pembayaran berhasil dicatat.'));
    }

    private function authorizeDistributionAccess(Distribution $distribution, $user): void
    {
        if ($user->isSuperAdminOrAdminPusat()) {
            return;
        }
        if ($user->branch_id) {
            $involved = ($distribution->from_location_type === Stock::LOCATION_BRANCH && (int) $distribution->from_location_id === (int) $user->branch_id)
                || ($distribution->to_location_type === Stock::LOCATION_BRANCH && (int) $distribution->to_location_id === (int) $user->branch_id);
            if (! $involved) {
                abort(403, __('Unauthorized.'));
            }
            return;
        }
        if ($user->warehouse_id) {
            $involved = ($distribution->from_location_type === Stock::LOCATION_WAREHOUSE && (int) $distribution->from_location_id === (int) $user->warehouse_id)
                || ($distribution->to_location_type === Stock::LOCATION_WAREHOUSE && (int) $distribution->to_location_id === (int) $user->warehouse_id);
            if (! $involved) {
                abort(403, __('Unauthorized.'));
            }
        }
    }

    private function normalizeNoRekening(?string $no): string
    {
        return preg_replace('/\s+/', '', trim((string) $no));
    }

    private function matchDestinationPaymentMethod(PaymentMethod $originPm, $destPaymentMethods): ?PaymentMethod
    {
        $originRek = $this->normalizeNoRekening($originPm->no_rekening ?? '');

        if ($originRek !== '') {
            return $destPaymentMethods->first(
                fn ($pm) => $this->normalizeNoRekening($pm->no_rekening ?? '') === $originRek
            );
        }

        $originJenis = strtolower(trim($originPm->jenis_pembayaran ?? ''));

        return $destPaymentMethods->first(function ($pm) use ($originJenis) {
            if ($this->normalizeNoRekening($pm->no_rekening ?? '') !== '') {
                return false;
            }

            return strtolower(trim($pm->jenis_pembayaran ?? '')) === $originJenis;
        });
    }

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

            $destPm = $this->matchDestinationPaymentMethod($originPm, $destPaymentMethods);

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
            'from_location_type' => ['required', 'in:' . Stock::LOCATION_WAREHOUSE . ',' . Stock::LOCATION_BRANCH],
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
            'from_location_type' => ['required', 'in:' . Stock::LOCATION_WAREHOUSE . ',' . Stock::LOCATION_BRANCH],
            'from_location_id' => ['required', 'integer', 'min:1'],
        ]);

        $limit = 500;
        $query = ProductUnit::query()
            ->where('product_id', (int) $validated['product_id'])
            ->where('location_type', (string) $validated['from_location_type'])
            ->where('location_id', (int) $validated['from_location_id'])
            ->where('status', ProductUnit::STATUS_IN_STOCK);

        $total = (clone $query)->count();
        $units = $query
            ->orderBy('id')
            ->limit($limit)
            ->get(['serial_number', 'harga_hpp']);

        return response()->json([
            'serial_numbers' => $units->pluck('serial_number')->all(),
            'serial_hpp' => $units->pluck('harga_hpp', 'serial_number')->all(),
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

            DB::beginTransaction();

            $totalBiaya = 0;
            $details = [];
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

                $hppPerUnit = isset($item['hpp_per_unit']) && $item['hpp_per_unit'] !== '' && $item['hpp_per_unit'] !== null
                    ? round((float) $item['hpp_per_unit'], 2)
                    : null;

                $totalBiaya += $biayaDistribusi * $quantity;
                $details[] = [
                    'product' => $product,
                    'serial_numbers' => $serialNumbers,
                    'quantity' => $quantity,
                    'biaya_distribusi' => $biayaDistribusi,
                    'hpp_per_unit' => $hppPerUnit,
                ];
            }

            $distributionPayments = collect($request->input('distribution_payments', []))
                ->filter(fn ($p) => ! empty($p['payment_method_id']) && (float) ($p['amount'] ?? 0) > 0)
                ->map(fn ($p) => [
                    'payment_method_id' => (int) $p['payment_method_id'],
                    'amount' => round((float) $p['amount'], 2),
                ])
                ->values()
                ->all();

            $totalPaid = collect($distributionPayments)->sum('amount');

            $distribution = Distribution::create([
                'invoice_number' => $invoiceNumber,
                'from_location_type' => $fromLocationType,
                'from_location_id' => $fromLocationId,
                'to_location_type' => $request->to_location_type,
                'to_location_id' => (int) $request->to_location_id,
                'total' => round($totalBiaya, 2),
                'total_paid' => round($totalPaid, 2),
                'notes' => $request->notes,
                'user_id' => $user->id,
                'distribution_date' => $request->mutation_date,
                'status' => Distribution::STATUS_ACTIVE,
            ]);

            $mutations = [];
            foreach ($details as $detail) {
                $mutation = $this->stockMutationService->mutate(
                    $detail['product'],
                    $fromLocationType,
                    $fromLocationId,
                    $request->to_location_type,
                    (int) $request->to_location_id,
                    $detail['quantity'],
                    $request->mutation_date,
                    $request->notes,
                    $user->id,
                    $detail['serial_numbers'],
                    $detail['biaya_distribusi'],
                    [],
                    $invoiceNumber,
                    $distribution->id
                );
                $mutations[] = $mutation;

                $finalHpp = $detail['hpp_per_unit'] !== null
                    ? $detail['hpp_per_unit']
                    : (float) $mutation->hpp_per_unit;

                DistributionDetail::create([
                    'distribution_id' => $distribution->id,
                    'product_id' => $detail['product']->id,
                    'quantity' => $detail['quantity'],
                    'biaya_distribusi_per_unit' => $detail['biaya_distribusi'],
                    'hpp_per_unit' => $finalHpp,
                    'serial_numbers' => ! empty($detail['serial_numbers']) ? implode("\n", $detail['serial_numbers']) : null,
                ]);
            }

            if ($totalBiaya > 0 && ! empty($distributionPayments)) {
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

                    DistributionPayment::create([
                        'distribution_id' => $distribution->id,
                        'payment_method_id' => $pmId,
                        'amount' => $amount,
                    ]);

                    CashFlow::create([
                        'branch_id' => $fromBranchId,
                        'warehouse_id' => $fromWarehouseId,
                        'type' => CashFlow::TYPE_IN,
                        'amount' => $amount,
                        'description' => __('Distribusi Barang') . ' ' . $invoiceNumber . ' - ' . ($pm?->display_label ?? __('Pembayaran')),
                        'reference_type' => CashFlow::REFERENCE_DISTRIBUTION,
                        'reference_id' => $distribution->id,
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

        return redirect()->route('stock-mutations.index')->with('success', __('Distribusi barang berhasil dibuat.'));
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
