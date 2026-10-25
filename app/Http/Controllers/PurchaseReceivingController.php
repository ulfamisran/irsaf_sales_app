<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\ProductUnit;
use App\Models\Purchase;
use App\Models\PurchaseReceivingLog;
use App\Models\Role;
use App\Models\Warehouse;
use App\Services\PurchaseReceivingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class PurchaseReceivingController extends Controller
{
    public function __construct(
        protected PurchaseReceivingService $receivingService
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        if (! $user->isSuperAdminOrAdminPusat() && ! $user->hasAnyRole([Role::ADMIN_GUDANG, Role::ADMIN_CABANG])) {
            abort(403, __('Unauthorized.'));
        }

        $tab = $request->get('tab', 'pengecekan');
        if (! in_array($tab, ['pengecekan', 'riwayat'], true)) {
            $tab = 'pengecekan';
        }

        $loc = $this->locationContext($request);
        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);
        $branches = Branch::orderBy('name')->get(['id', 'name']);

        if ($tab === 'riwayat') {
            return $this->indexHistory($request, $warehouses, $branches, $loc);
        }

        $query = Purchase::with(['distributor', 'warehouse', 'branch', 'details.product.category', 'details.units'])
            ->where('status', '!=', Purchase::STATUS_CANCELLED)
            ->where(function ($q) {
                $q->where('jenis_pembelian', Purchase::JENIS_PEMBELIAN_UNIT)
                    ->orWhereNull('jenis_pembelian')
                    ->orWhere('jenis_pembelian', '');
            })
            ->orderByDesc('purchase_date')
            ->orderByDesc('id');

        $this->applyLocationFilter($request, $query);

        $all = $query->get();
        $needingCheck = $all->filter(fn (Purchase $p) => $this->receivingService->purchaseNeedsCheck($p))->values();

        $page = max(1, (int) $request->get('page', 1));
        $perPage = 20;
        $slice = $needingCheck->slice(($page - 1) * $perPage, $perPage)->values();
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $slice,
            $needingCheck->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $rows = $paginator->getCollection()->map(function (Purchase $p) {
            return (object) [
                'purchase' => $p,
                'summary' => $this->receivingService->summarize($p),
            ];
        });
        $paginator->setCollection($rows);

        return view('purchase-receiving.index', [
            'tab' => 'pengecekan',
            'purchases' => $paginator,
            'historyPurchases' => null,
            'warehouses' => $warehouses,
            'branches' => $branches,
            'canFilterLocation' => $loc['canFilterLocation'],
            'filterLocked' => $loc['filterLocked'],
            'locationLabel' => $loc['locationLabel'],
        ]);
    }

    private function indexHistory(Request $request, $warehouses, $branches, array $loc): View
    {
        $query = Purchase::with(['distributor', 'warehouse', 'branch'])
            ->withCount('receivingLogs')
            ->withMax('receivingLogs as last_checked_at', 'checked_at')
            ->whereHas('receivingLogs')
            ->orderByDesc('last_checked_at')
            ->orderByDesc('id');

        $this->applyLocationFilter($request, $query);

        if ($request->filled('date_from') || $request->filled('date_to')) {
            $query->whereHas('receivingLogs', function ($q) use ($request) {
                if ($request->filled('date_from')) {
                    $q->whereDate('checked_at', '>=', $request->date_from);
                }
                if ($request->filled('date_to')) {
                    $q->whereDate('checked_at', '<=', $request->date_to);
                }
            });
        }

        if ($request->filled('search')) {
            $term = '%'.trim((string) $request->search).'%';
            $query->where(function ($q) use ($term) {
                $q->where('invoice_number', 'like', $term)
                    ->orWhereHas('distributor', fn ($d) => $d->where('name', 'like', $term))
                    ->orWhereHas('receivingLogs.productUnit', fn ($u) => $u->where('serial_number', 'like', $term));
            });
        }

        $historyPurchases = $query->paginate(20)->withQueryString();

        return view('purchase-receiving.index', [
            'tab' => 'riwayat',
            'purchases' => null,
            'historyPurchases' => $historyPurchases,
            'warehouses' => $warehouses,
            'branches' => $branches,
            'canFilterLocation' => $loc['canFilterLocation'],
            'filterLocked' => $loc['filterLocked'],
            'locationLabel' => $loc['locationLabel'],
        ]);
    }

    public function history(Request $request, Purchase $purchase): View
    {
        $this->authorizePurchaseAccess($request, $purchase);
        $purchase->load(['distributor', 'warehouse', 'branch']);

        $logs = PurchaseReceivingLog::with(['checker', 'productUnit', 'purchaseDetail.product'])
            ->where('purchase_id', $purchase->id)
            ->orderByDesc('checked_at')
            ->orderByDesc('id')
            ->get();

        return view('purchase-receiving.history', compact('purchase', 'logs'));
    }

    public function show(Request $request, Purchase $purchase): View
    {
        $this->authorizePurchaseAccess($request, $purchase);
        $purchase->load(['distributor', 'warehouse', 'branch', 'details.product.category', 'details.units', 'payments']);

        $summary = $this->receivingService->summarize($purchase);
        $lines = [];
        foreach ($purchase->details as $detail) {
            $requiresSerial = $detail->product?->category?->requires_serial ?? true;
            $uncheckedUnits = $detail->units
                ->where('check_status', ProductUnit::CHECK_UNCHECKED)
                ->values();
            $lines[] = [
                'detail' => $detail,
                'requires_serial' => $requiresSerial,
                'unchecked_units' => $uncheckedUnits,
                'unchecked_qty' => $detail->uncheckedQty(),
            ];
        }

        $logs = PurchaseReceivingLog::with(['checker', 'productUnit', 'purchaseDetail.product'])
            ->where('purchase_id', $purchase->id)
            ->orderByDesc('checked_at')
            ->orderByDesc('id')
            ->get();

        return view('purchase-receiving.show', compact('purchase', 'summary', 'lines', 'logs'));
    }

    public function store(Request $request, Purchase $purchase): RedirectResponse
    {
        $this->authorizePurchaseAccess($request, $purchase);

        $validated = $request->validate([
            'units' => ['nullable', 'array'],
            'units.*.unit_id' => ['nullable', 'integer', 'exists:product_units,id'],
            'units.*.result' => ['nullable', 'in:baik,reture,pending'],
            'qtys' => ['nullable', 'array'],
            'qtys.*.purchase_detail_id' => ['nullable', 'integer', 'exists:purchase_details,id'],
            'qtys.*.qty_baik' => ['nullable', 'integer', 'min:0'],
            'qtys.*.qty_reture' => ['nullable', 'integer', 'min:0'],
            'qtys.*.qty_pending' => ['nullable', 'integer', 'min:0'],
        ]);

        $unitResults = array_values(array_filter($validated['units'] ?? [], fn ($r) => ! empty($r['result']) && ! empty($r['unit_id'])));
        $qtyResults = array_values(array_filter($validated['qtys'] ?? [], function ($r) {
            return ((int) ($r['qty_baik'] ?? 0)) + ((int) ($r['qty_reture'] ?? 0)) + ((int) ($r['qty_pending'] ?? 0)) > 0;
        }));

        if ($unitResults === [] && $qtyResults === []) {
            return back()->with('error', __('Pilih minimal satu hasil cek.'));
        }

        try {
            $this->receivingService->applyCheck($purchase, $unitResults, $qtyResults, (int) $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('purchase-receiving.show', $purchase)
            ->with('success', __('Hasil cek berhasil disimpan.'));
    }

    public function editLog(Request $request, PurchaseReceivingLog $log): View
    {
        $log->load(['purchase', 'purchaseDetail.product', 'productUnit', 'checker']);
        $this->authorizePurchaseAccess($request, $log->purchase);

        return view('purchase-receiving.edit-log', compact('log'));
    }

    public function updateLog(Request $request, PurchaseReceivingLog $log): RedirectResponse
    {
        $log->load('purchase');
        $this->authorizePurchaseAccess($request, $log->purchase);

        $validated = $request->validate([
            'result' => ['required', 'in:baik,reture,pending'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->receivingService->updateCheck(
                $log,
                (string) $validated['result'],
                (int) $request->user()->id,
                $validated['notes'] ?? null
            );
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('purchase-receiving.history', $log->purchase)
            ->with('success', __('Hasil cek berhasil diperbarui.'));
    }

    /**
     * @return array{canFilterLocation: bool, filterLocked: bool, locationLabel: ?string}
     */
    private function locationContext(Request $request): array
    {
        $user = $request->user();
        $canFilterLocation = false;
        $filterLocked = false;
        $locationLabel = null;

        if (! $user->isSuperAdminOrAdminPusat()) {
            if ($user->hasAnyRole([Role::ADMIN_CABANG]) && $user->branch_id) {
                $filterLocked = true;
                $locationLabel = __('Cabang').': '.(Branch::find($user->branch_id)?->name ?? '');
            } elseif ($user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->warehouse_id) {
                $filterLocked = true;
                $locationLabel = __('Gudang').': '.(Warehouse::find($user->warehouse_id)?->name ?? '');
            } elseif (! $user->branch_id && ! $user->warehouse_id) {
                abort(403, __('User branch or warehouse not set.'));
            }
        } else {
            $canFilterLocation = true;
        }

        return [
            'canFilterLocation' => $canFilterLocation,
            'filterLocked' => $filterLocked,
            'locationLabel' => $locationLabel,
        ];
    }

    private function applyLocationFilter(Request $request, Builder $query): void
    {
        $user = $request->user();
        if (! $user->isSuperAdminOrAdminPusat()) {
            if ($user->hasAnyRole([Role::ADMIN_CABANG]) && $user->branch_id) {
                $query->where('branch_id', $user->branch_id);
            } elseif ($user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->warehouse_id) {
                $query->where('warehouse_id', $user->warehouse_id);
            } else {
                abort(403, __('User branch or warehouse not set.'));
            }

            return;
        }

        if ($request->filled('location_type')) {
            $locationId = (int) $request->input('location_id', 0);
            if ($request->location_type === 'warehouse') {
                if ($locationId > 0) {
                    $query->where('warehouse_id', $locationId);
                } elseif ($request->filled('warehouse_id')) {
                    $query->where('warehouse_id', $request->warehouse_id);
                }
            } elseif ($request->location_type === 'branch') {
                if ($locationId > 0) {
                    $query->where('branch_id', $locationId);
                } elseif ($request->filled('branch_id')) {
                    $query->where('branch_id', $request->branch_id);
                }
            }
        } elseif ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        } elseif ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }
    }

    private function authorizePurchaseAccess(Request $request, ?Purchase $purchase): void
    {
        if (! $purchase) {
            abort(404);
        }
        $user = $request->user();
        if (! $user->isSuperAdminOrAdminPusat() && ! $user->hasAnyRole([Role::ADMIN_GUDANG, Role::ADMIN_CABANG])) {
            abort(403, __('Unauthorized.'));
        }
        if (! $user->isSuperAdminOrAdminPusat()) {
            if ($user->hasAnyRole([Role::ADMIN_CABANG]) && (int) $purchase->branch_id !== (int) $user->branch_id) {
                abort(403);
            }
            if ($user->hasAnyRole([Role::ADMIN_GUDANG]) && (int) $purchase->warehouse_id !== (int) $user->warehouse_id) {
                abort(403);
            }
        }
    }
}
