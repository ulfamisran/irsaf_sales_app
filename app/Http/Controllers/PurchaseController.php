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
use App\Models\Service;
use App\Models\User;
use App\Models\Stock;
use App\Models\Warehouse;
use App\Services\KasBalanceService;
use App\Services\PurchaseReceivingService;
use App\Services\PurchaseService;
use App\Services\TransactionProofService;
use App\Support\CatatRange;
use App\Support\ExcelExporter;
use App\Support\UserLocationScope;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchaseController extends Controller
{
    use Concerns\RedirectsFailedRelease;

    public function __construct(
        protected PurchaseService $purchaseService,
        protected PurchaseReceivingService $receivingService,
        protected TransactionProofService $proofs
    ) {}

    public function index(Request $request): View
    {
        $ctx = $this->buildPurchasesListQuery($request);
        $tab = $request->get('tab', 'daftar');
        if (! in_array($tab, ['daftar', 'nilai', 'utang', 'penangguhan'], true)) {
            $tab = 'daftar';
        }

        $totalUnpaid = 0.0;
        $totalRecognized = 0.0;
        $purchases = (clone $ctx['query'])->paginate(20)->withQueryString();

        if ($tab === 'nilai') {
            $nilaiQuery = (clone $ctx['query'])->with([
                'distributor', 'warehouse', 'branch', 'details.product.category', 'details.units', 'payments',
            ]);
            $allRows = $nilaiQuery->get()->map(function (Purchase $p) {
                return (object) [
                    'purchase' => $p,
                    'summary' => $this->receivingService->summarize($p),
                ];
            });

            if ($request->filled('check_status')) {
                $checkStatus = (string) $request->check_status;
                $allRows = $allRows->filter(fn ($r) => ($r->summary['check_status'] ?? '') === $checkStatus)->values();
            }
            if ($request->filled('payment_status')) {
                $payStatus = (string) $request->payment_status;
                $allRows = $allRows->filter(fn ($r) => ($r->summary['payment_status'] ?? '') === $payStatus)->values();
            }

            $totalRecognized = (float) $allRows->sum(fn ($r) => (float) $r->summary['recognized_debt']);
            $totalUnpaid = (float) $allRows->sum(fn ($r) => (float) $r->summary['remaining_recognized']);

            $page = max(1, (int) $request->get('page', 1));
            $perPage = 20;
            $slice = $allRows->slice(($page - 1) * $perPage, $perPage)->values();
            $purchases = new \Illuminate\Pagination\LengthAwarePaginator(
                $slice,
                $allRows->count(),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        } elseif ($tab === 'utang') {
            $utangQuery = (clone $ctx['query'])
                ->where('status', '!=', Purchase::STATUS_CANCELLED)
                ->with(['distributor', 'warehouse', 'branch', 'details.product.category', 'details.units', 'payments']);

            $utangRows = $utangQuery->get()->map(function (Purchase $p) {
                $summary = $this->receivingService->summarize($p);

                return (object) [
                    'purchase' => $p,
                    'summary' => $summary,
                    'recognized' => (float) $summary['recognized_debt'],
                    'paid' => (float) $summary['paid'],
                    'remaining' => (float) $summary['remaining_recognized'],
                    'units' => $this->receivingService->listRecognizedItems($p),
                ];
            })->filter(fn ($r) => (float) $r->remaining > 0.02)->values();

            $totalUnpaid = (float) $utangRows->sum('remaining');
            $totalRecognized = (float) $utangRows->sum('recognized');

            $page = max(1, (int) $request->get('page', 1));
            $perPage = 20;
            $slice = $utangRows->slice(($page - 1) * $perPage, $perPage)->values();
            $purchases = new \Illuminate\Pagination\LengthAwarePaginator(
                $slice,
                $utangRows->count(),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        } elseif ($tab === 'penangguhan') {
            $pendingQuery = (clone $ctx['query'])
                ->where('status', '!=', Purchase::STATUS_CANCELLED)
                ->with(['distributor', 'warehouse', 'branch', 'details.product.category', 'details.units', 'payments']);

            $pendingRows = $pendingQuery->get()->map(function (Purchase $p) {
                $deferredItems = $this->receivingService->listDeferredItems($p);
                $summary = $this->receivingService->summarize($p);

                return (object) [
                    'purchase' => $p,
                    'summary' => $summary,
                    'deferred_items' => $deferredItems,
                    'deferred_value' => (float) $summary['deferred_value'],
                ];
            })->filter(fn ($r) => count($r->deferred_items) > 0)->values();

            $totalUnpaid = (float) $pendingRows->sum('deferred_value');
            $totalRecognized = 0.0;

            $page = max(1, (int) $request->get('page', 1));
            $perPage = 20;
            $slice = $pendingRows->slice(($page - 1) * $perPage, $perPage)->values();
            $purchases = new \Illuminate\Pagination\LengthAwarePaginator(
                $slice,
                $pendingRows->count(),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        } else {
            $daftarQuery = (clone $ctx['query'])->with([
                'distributor', 'warehouse', 'branch', 'user', 'details.product.category', 'details.units', 'payments',
            ]);
            $purchases = $daftarQuery->paginate(20)->withQueryString();
            $mapped = $purchases->getCollection()->map(function (Purchase $p) {
                $summary = $this->receivingService->summarize($p);

                return (object) [
                    'purchase' => $p,
                    'summary' => $summary,
                ];
            });
            $purchases->setCollection($mapped);
            $totalRecognized = (float) $mapped->sum(fn ($r) => (float) $r->summary['recognized_debt']);
            $totalUnpaid = (float) $mapped->sum(fn ($r) => (float) $r->summary['remaining_recognized']);
        }

        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);
        $branches = Branch::orderBy('name')->get(['id', 'name']);
        $distributors = Distributor::orderBy('name')->get(['id', 'name']);

        return view('purchases.index', [
            'purchases' => $purchases,
            'warehouses' => $warehouses,
            'branches' => $branches,
            'distributors' => $distributors,
            'canFilterLocation' => $ctx['canFilterLocation'],
            'filterLocked' => $ctx['filterLocked'],
            'locationLabel' => $ctx['locationLabel'],
            'totalUnpaid' => $totalUnpaid,
            'totalRecognized' => $totalRecognized,
            'tab' => $tab,
        ]);
    }

    public function resolveDeferred(Request $request, Purchase $purchase): RedirectResponse
    {
        $user = $request->user();
        $this->assertCanAccessPurchase($user, $purchase);

        $validated = $request->validate([
            'action' => ['required', 'in:diterima,diretur'],
            'unit_id' => ['nullable', 'integer', 'exists:product_units,id'],
            'purchase_detail_id' => ['nullable', 'integer', 'exists:purchase_details,id'],
            'qty' => ['nullable', 'integer', 'min:1'],
        ]);

        if (empty($validated['unit_id']) && empty($validated['purchase_detail_id'])) {
            return back()->with('error', __('Pilih unit atau detail penangguhan.'));
        }

        try {
            $this->receivingService->resolveDeferred($purchase, $validated, (int) $user->id);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $msg = $validated['action'] === 'diterima'
            ? __('Unit diterima (masuk stok).')
            : __('Unit diretur.');

        return redirect()
            ->route('purchases.index', ['tab' => 'penangguhan'])
            ->with('success', $msg);
    }

    public function export(Request $request): StreamedResponse
    {
        $ctx = $this->buildPurchasesListQuery($request);
        $purchases = (clone $ctx['query'])->with(['distributor', 'warehouse', 'branch', 'user', 'payments'])->get();
        $summary = $this->computePurchasesExportSummary($purchases);
        $filterMeta = $this->purchasesExportFilterMeta($request, $ctx);

        $filename = 'rekap-pembelian-'.now()->format('Ymd-His').'.xlsx';
        $html = view('purchases.export', array_merge($summary, compact('purchases', 'filterMeta')))->render();

        return ExcelExporter::downloadFromHtml($html, $filename, 'generic');
    }

    public function exportPdf(Request $request)
    {
        $ctx = $this->buildPurchasesListQuery($request);
        $purchases = (clone $ctx['query'])->with(['distributor', 'warehouse', 'branch', 'user', 'payments'])->get();
        $summary = $this->computePurchasesExportSummary($purchases);
        $filterMeta = $this->purchasesExportFilterMeta($request, $ctx);

        $pdf = Pdf::loadView('purchases.export-pdf', array_merge($summary, compact('purchases', 'filterMeta')))
            ->setPaper('a4', 'landscape');

        return $pdf->download('rekap-pembelian-'.now()->format('Ymd-His').'.pdf');
    }

    /**
     * @return array{query: Builder, canFilterLocation: bool, filterLocked: bool, locationLabel: ?string}
     */
    private function buildPurchasesListQuery(Request $request): array
    {
        $user = $request->user();
        if (! $user->hasUnrestrictedLocationAccess()
            && ! $user->isAdminPusat()
            && ! $user->branch_id
            && ! $user->warehouse_id) {
            abort(403, __('User branch or warehouse not set.'));
        }
        if ($user->isAdminPusat()
            && $user->accessibleBranchIds() === []
            && $user->accessibleWarehouseIds() === []) {
            abort(403, __('Admin Pusat belum didaftarkan ke cabang/gudang mana pun.'));
        }

        $query = Purchase::with(['distributor', 'warehouse', 'branch', 'user', 'details', 'payments.paymentMethod'])
            ->orderByDesc('purchase_date')
            ->orderByDesc('id');

        UserLocationScope::constrainBranchWarehouse($query, $user);

        $canFilterLocation = $user->hasUnrestrictedLocationAccess()
            || $user->isAdminPusat()
            || (count($user->accessibleBranchIds()) + count($user->accessibleWarehouseIds()) > 1);
        $filterLocked = ! $canFilterLocation;
        $locationLabel = null;

        if ($filterLocked) {
            if ($user->warehouse_id) {
                $locationLabel = __('Gudang').': '.(Warehouse::find($user->warehouse_id)?->name ?? '');
            } elseif ($user->branch_id) {
                $locationLabel = __('Cabang').': '.(Branch::find($user->branch_id)?->name ?? '');
            }
        } elseif ($request->filled('location_type')) {
            $locationId = (int) $request->input('location_id', 0);
            if ($request->location_type === 'warehouse') {
                if ($locationId > 0 && $user->canAccessWarehouse($locationId)) {
                    $query->where('warehouse_id', $locationId);
                    $locationLabel = __('Gudang').': '.(Warehouse::find($locationId)?->name ?? '');
                } elseif ($request->filled('warehouse_id') && $user->canAccessWarehouse((int) $request->warehouse_id)) {
                    $query->where('warehouse_id', $request->warehouse_id);
                }
            } elseif ($request->location_type === 'branch') {
                if ($locationId > 0 && $user->canAccessBranch($locationId)) {
                    $query->where('branch_id', $locationId);
                    $locationLabel = __('Cabang').': '.(Branch::find($locationId)?->name ?? '');
                } elseif ($request->filled('branch_id') && $user->canAccessBranch((int) $request->branch_id)) {
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
        CatatRange::apply($query, $request, 'purchases.created_at');
        CatatRange::applyBackdate($query, $request, 'purchases.purchase_date', 'purchases.created_at');
        if ($request->filled('distributor_id')) {
            $query->where('distributor_id', $request->distributor_id);
        }
        if ($request->filled('status')) {
            $status = (string) $request->status;
            if (in_array($status, [Purchase::STATUS_OPEN, Purchase::STATUS_ACTIVE, Purchase::STATUS_CANCELLED], true)) {
                $query->where('status', $status);
            } elseif ($status === 'paid_off') {
                $query->where('status', '!=', Purchase::STATUS_CANCELLED)
                    ->where(function ($q) {
                        $q->where('total', '<=', 0)
                            ->orWhereRaw('total_paid >= (total - 0.02)');
                    });
            } elseif ($status === 'unpaid') {
                $query->where('status', '!=', Purchase::STATUS_CANCELLED)
                    ->where('total', '>', 0)
                    ->whereRaw('total_paid < (total - 0.02)');
            }
        }
        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like) {
                $q->where('invoice_number', 'like', $like)
                    ->orWhereHas('distributor', fn ($d) => $d->where('name', 'like', $like))
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $like))
                    ->orWhereHas('details.product', fn ($product) => $product->where('sku', 'like', $like))
                    ->orWhereHas('details', fn ($detail) => $detail->where('serial_numbers', 'like', $like))
                    ->orWhereHas('units', fn ($unit) => $unit->where('serial_number', 'like', $like))
                    ->orWhereHas('details.units', fn ($unit) => $unit->where('serial_number', 'like', $like));
            });
        }

        return [
            'query' => $query,
            'canFilterLocation' => $canFilterLocation,
            'filterLocked' => $filterLocked,
            'locationLabel' => $locationLabel,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Purchase>  $purchases
     * @return array{totalAmount: float, totalPaid: float, totalUnpaid: float, count: int}
     */
    private function computePurchasesExportSummary($purchases): array
    {
        $active = $purchases->filter(fn (Purchase $p) => ! $p->isCancelled());
        $totalAmount = (float) $active->sum('total');
        $totalPaid = (float) $active->sum('total_paid');
        $totalUnpaid = (float) $active
            ->filter(fn (Purchase $p) => (float) $p->total > 0 && ! $p->isPaidOff())
            ->sum(fn (Purchase $p) => max(0, (float) $p->total - (float) $p->total_paid));

        return [
            'totalAmount' => $totalAmount,
            'totalPaid' => $totalPaid,
            'totalUnpaid' => $totalUnpaid,
            'count' => $purchases->count(),
        ];
    }

    /**
     * @param  array{canFilterLocation: bool, filterLocked: bool, locationLabel: ?string}  $ctx
     * @return array{dateFrom: string, dateTo: string, locationLine: string, distributorLine: string, statusLine: string, searchLine: string}
     */
    private function purchasesExportFilterMeta(Request $request, array $ctx): array
    {
        $locationLine = $ctx['locationLabel'] ?? __('Semua');
        if ($ctx['canFilterLocation'] ?? false) {
            $lt = $request->get('location_type');
            $lid = $request->get('location_id') ?: ($lt === 'branch' ? $request->get('branch_id') : $request->get('warehouse_id'));
            if ($lt === 'warehouse' && $lid) {
                $locationLine = __('Gudang').': '.(Warehouse::find($lid)?->name ?? '#'.$lid);
            } elseif ($lt === 'branch' && $lid) {
                $locationLine = __('Cabang').': '.(Branch::find($lid)?->name ?? '#'.$lid);
            } elseif ($lt === 'warehouse') {
                $locationLine = __('Semua Gudang');
            } elseif ($lt === 'branch') {
                $locationLine = __('Semua Cabang');
            } else {
                $locationLine = __('Semua');
            }
        }

        $distributorLine = __('Semua');
        if ($request->filled('distributor_id')) {
            $distributorLine = Distributor::find($request->distributor_id)?->name ?? '#'.$request->distributor_id;
        }

        $statusMap = [
            'active' => __('Release'),
            'cancelled' => __('Dibatalkan'),
            'paid_off' => __('Lunas'),
            'unpaid' => __('Belum Lunas'),
        ];
        $statusLine = $statusMap[(string) $request->get('status', '')] ?? __('Semua');

        return [
            'dateFrom' => $request->get('date_from') ?: '-',
            'dateTo' => $request->get('date_to') ?: '-',
            'locationLine' => $locationLine,
            'distributorLine' => $distributorLine,
            'statusLine' => $statusLine,
            'searchLine' => $request->filled('search') ? (string) $request->search : '-',
        ];
    }

    public function create(Request $request): View
    {
        $user = auth()->user();

        $locationOptions = \App\Services\LocationService::getLocationOptionsForUser($user);
        $warehouses = $locationOptions['warehouses'];
        $branches = $locationOptions['branches'];
        $canPickBranch = $branches->isNotEmpty();
        $canPickWarehouse = $warehouses->isNotEmpty();

        // User cabang/gudang biasa: selalu terkunci ke 1 lokasi (berdasarkan penempatan, bukan nama role).
        $isBranchUser = ! $user->hasUnrestrictedLocationAccess()
            && ! $user->isAdminPusat()
            && (int) $user->branch_id > 0
            && ! (int) $user->warehouse_id;
        $isWarehouseUser = ! $user->hasUnrestrictedLocationAccess()
            && ! $user->isAdminPusat()
            && (int) $user->warehouse_id > 0;

        // Admin Pusat: boleh pilih di antara lokasi terdaftar.
        // Jika hanya 1 lokasi total → dikunci seperti user cabang/gudang.
        if ($user->isAdminPusat() && ! $user->hasUnrestrictedLocationAccess()) {
            if ($canPickBranch && ! $canPickWarehouse && $branches->count() === 1) {
                $isBranchUser = true;
            } elseif ($canPickWarehouse && ! $canPickBranch && $warehouses->count() === 1) {
                $isWarehouseUser = true;
            }
        }

        $defaultLocationType = $isBranchUser ? 'branch' : ($isWarehouseUser ? 'warehouse' : ($locationOptions['defaultPlacementType'] === 'cabang' ? 'branch' : 'warehouse'));
        $defaultLocationId = $isBranchUser
            ? (int) ($user->isAdminPusat() ? $branches->first()->id : $user->branch_id)
            : ($isWarehouseUser ? (int) ($user->isAdminPusat() ? $warehouses->first()->id : $user->warehouse_id) : null);
        if (! $defaultLocationId) {
            if ($defaultLocationType === 'branch' && $locationOptions['defaultBranchId']) {
                $defaultLocationId = (int) $locationOptions['defaultBranchId'];
            } elseif ($canPickWarehouse && $locationOptions['defaultWarehouseId']) {
                $defaultLocationType = 'warehouse';
                $defaultLocationId = (int) $locationOptions['defaultWarehouseId'];
            } elseif ($canPickBranch && $locationOptions['defaultBranchId']) {
                $defaultLocationType = 'branch';
                $defaultLocationId = (int) $locationOptions['defaultBranchId'];
            } else {
                $firstWarehouse = $warehouses->first();
                $firstBranch = $branches->first();
                if ($firstWarehouse && ($defaultLocationType === 'warehouse' || ! $firstBranch)) {
                    $defaultLocationId = (int) $firstWarehouse->id;
                    $defaultLocationType = 'warehouse';
                } elseif ($firstBranch) {
                    $defaultLocationId = (int) $firstBranch->id;
                    $defaultLocationType = 'branch';
                } elseif ($firstWarehouse) {
                    $defaultLocationId = (int) $firstWarehouse->id;
                    $defaultLocationType = 'warehouse';
                }
            }
        }
        if (! $canPickWarehouse && $canPickBranch) {
            $defaultLocationType = 'branch';
            $defaultLocationId = $defaultLocationId && $branches->contains('id', $defaultLocationId)
                ? $defaultLocationId
                : (int) ($branches->first()?->id ?? 0);
        } elseif (! $canPickBranch && $canPickWarehouse) {
            $defaultLocationType = 'warehouse';
            $defaultLocationId = $defaultLocationId && $warehouses->contains('id', $defaultLocationId)
                ? $defaultLocationId
                : (int) ($warehouses->first()?->id ?? 0);
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

        $openServicesInitial = $this->openServicesForPurchaseLocation($defaultLocationType, (int) $defaultLocationId);

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
            'canPickBranch',
            'canPickWarehouse',
            'defaultLocationType',
            'defaultLocationId',
            'openServicesInitial'
        ));
    }

    public function edit(Request $request, Purchase $purchase): View|RedirectResponse
    {
        $user = $request->user();
        $this->assertCanAccessPurchase($user, $purchase);

        if (! $purchase->canBeEdited()) {
            return redirect()->route('purchases.show', $purchase)
                ->with('error', __('Pembelian ini tidak dapat diubah (sudah ada pembayaran, distribusi, atau status tidak mengizinkan).'));
        }

        $purchase->load(['details.product', 'service.customer', 'warehouse', 'branch', 'proofs']);

        $locationOptions = \App\Services\LocationService::getLocationOptionsForUser($user);
        $warehouses = $locationOptions['warehouses'];
        $branches = $locationOptions['branches'];
        $canPickBranch = $branches->isNotEmpty();
        $canPickWarehouse = $warehouses->isNotEmpty();

        $isBranchUser = ! $user->hasUnrestrictedLocationAccess()
            && ! $user->isAdminPusat()
            && (int) $user->branch_id > 0
            && ! (int) $user->warehouse_id;
        $isWarehouseUser = ! $user->hasUnrestrictedLocationAccess()
            && ! $user->isAdminPusat()
            && (int) $user->warehouse_id > 0;

        if ($user->isAdminPusat() && ! $user->hasUnrestrictedLocationAccess()) {
            if ($canPickBranch && ! $canPickWarehouse && $branches->count() === 1) {
                $isBranchUser = true;
            } elseif ($canPickWarehouse && ! $canPickBranch && $warehouses->count() === 1) {
                $isWarehouseUser = true;
            }
        }

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

        $openServicesInitial = $this->openServicesForPurchaseLocation(
            $defaultLocationType,
            (int) $defaultLocationId,
            $purchase->service_id ? (int) $purchase->service_id : null
        );

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
            'canPickBranch',
            'canPickWarehouse',
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

        $locationType = $request->location_type;
        $locationId = (int) (($locationType === 'warehouse') ? $request->warehouse_id : $request->branch_id);

        if (! $locationId) {
            return back()->withInput()->with('error', __('Pilih gudang atau cabang terlebih dahulu.'));
        }

        UserLocationScope::assertCanAccessLocation(
            $user,
            $locationType === 'branch' ? $locationId : null,
            $locationType === 'warehouse' ? $locationId : null
        );

        $payments = array_filter($request->input('payments', []), fn ($p) => (int) ($p['payment_method_id'] ?? 0) > 0 && (float) ($p['amount'] ?? 0) > 0);

        // Catatan: validasi saldo sumber dana sengaja dihilangkan.
        // Sistem tetap mencatat transaksi, meski saldo sumber kas bisa menjadi kurang.

        $this->proofs->validateUploads($request, null, false, true);

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
                $serviceId,
                app(\App\Services\AuthorizationService::class)->enabled('purchases')
            );

            $this->proofs->storeUploaded($request, $purchase, $user->id);

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
        $this->assertCanAccessPurchase($user, $purchase);

        if (! $purchase->canBeEdited()) {
            return redirect()->route('purchases.show', $purchase)
                ->with('error', __('Pembelian ini tidak dapat diubah.'));
        }

        $this->proofs->validateUploads($request, $purchase, false, true);

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

            $this->proofs->storeUploaded($request, $updated, $user->id);

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

    public function report(Purchase $purchase): View
    {
        $user = auth()->user();
        $this->assertCanAccessPurchase($user, $purchase);

        $purchase->load(['distributor', 'warehouse', 'branch', 'user', 'details.product.category', 'details.units', 'payments', 'proofs']);

        return view('purchases.report', compact('purchase'));
    }

    public function show(Purchase $purchase): View
    {
        $user = auth()->user();
        $this->assertCanAccessPurchase($user, $purchase);

        $purchase->load([
            'distributor',
            'warehouse',
            'branch',
            'user',
            'details.product.category',
            'details.units',
            'payments.paymentMethod',
            'payments.user',
            'service',
            'proofs',
        ]);
        $receivingSummary = $this->receivingService->summarize($purchase);

        return view('purchases.show', compact('purchase', 'receivingSummary'));
    }

    public function addPayment(Request $request, Purchase $purchase): RedirectResponse
    {
        $user = $request->user();
        $this->assertCanAccessPurchase($user, $purchase);

        if ($purchase->isCancelled()) {
            return back()->with('error', __('Pembelian ini sudah dibatalkan.'));
        }
        if ($purchase->status !== \App\Models\Purchase::STATUS_ACTIVE) {
            return back()->with('error', __('Pembayaran tambahan hanya untuk pembelian yang sudah di-release.'));
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
        // Batas pembayaran = sisa nilai invoice (uang muka boleh sebelum ada utang terakui).
        $invoiceRemaining = max(0, (float) $purchase->total - (float) ($purchase->total_paid ?? 0));
        if ($invoiceRemaining <= 0.02) {
            return back()->with('error', __('Pembayaran sudah mencapai nilai invoice.'));
        }
        $totalPayment = 0.0;
        $amountPerPm = [];

        foreach ($payments as $p) {
            $pmId = (int) $p['payment_method_id'];
            $amount = (float) $p['amount'];
            $totalPayment += $amount;
            $amountPerPm[$pmId] = ($amountPerPm[$pmId] ?? 0) + $amount;
        }

        if ($totalPayment > $invoiceRemaining + 0.02) {
            return back()->withInput()->with('error', __('Total pembayaran tidak boleh melebihi sisa nilai invoice: Rp :sisa', [
                'sisa' => number_format($invoiceRemaining, 0, ',', '.'),
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

        $showParams = ['purchase' => $purchase];
        if ($request->input('from') === 'debts') {
            $showParams['from'] = 'debts';
            $showParams['view'] = 'detail';
        }

        return redirect()
            ->route('purchases.show', $showParams)
            ->with('success', __('Pembayaran berhasil dicatat.'));
    }

    public function release(Request $request, Purchase $purchase): RedirectResponse
    {
        $user = $request->user();
        $this->assertCanAccessPurchase($user, $purchase);

        try {
            $auth = app(\App\Services\AuthorizationService::class);
            $auth->assertCanRelease($purchase);
            $auth->assertReadyForRelease($purchase);
            $this->purchaseService->releasePurchase($purchase, (int) $user->id);
        } catch (\InvalidArgumentException $e) {
            return $this->redirectReleaseFailure($request, $purchase, $e->getMessage());
        }

        return redirect()->route('purchases.show', $purchase)->with('success', __('Pembelian berhasil di-release.'));
    }

    public function cancel(Request $request, Purchase $purchase): RedirectResponse
    {
        $user = $request->user();
        $this->assertCanAccessPurchase($user, $purchase);

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

        $openServices = $this->openServicesForPurchaseLocation($locationType, $locationId)
            ->map(fn (Service $s) => [
                'id' => $s->id,
                'invoice_number' => $s->invoice_number,
                'label' => $s->invoice_number
                    .' — '.($s->laptop_type ?? '')
                    .($s->customer ? ' ('.$s->customer->name.')' : ''),
            ])
            ->values();

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
                'requires_serial' => $p->category ? (bool) $p->category->requires_serial : true,
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
     * Invoice service berstatus open di lokasi pembelian (gudang atau cabang).
     */
    private function openServicesForPurchaseLocation(?string $locationType, int $locationId, ?int $includeServiceId = null)
    {
        if ($locationId <= 0 || ! in_array($locationType, ['warehouse', 'branch'], true)) {
            return collect();
        }

        return Service::query()
            ->with(['customer:id,name'])
            ->when(
                $locationType === 'warehouse',
                fn ($q) => $q->where('warehouse_id', $locationId),
                fn ($q) => $q->where('branch_id', $locationId)
            )
            ->where(function ($q) use ($includeServiceId) {
                $q->where('status', Service::STATUS_OPEN);
                if ($includeServiceId) {
                    $q->orWhere('id', $includeServiceId);
                }
            })
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->limit(200)
            ->get(['id', 'invoice_number', 'laptop_type', 'customer_id', 'status']);
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

    private function assertCanAccessPurchase(User $user, Purchase $purchase): void
    {
        UserLocationScope::assertCanAccessLocation(
            $user,
            $purchase->branch_id ? (int) $purchase->branch_id : null,
            $purchase->warehouse_id ? (int) $purchase->warehouse_id : null
        );
    }
}
