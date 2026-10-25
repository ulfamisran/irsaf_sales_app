<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Distributor;
use App\Models\Purchase;
use App\Models\Role;
use App\Models\Warehouse;
use App\Services\PurchaseReceivingService;
use App\Support\ExcelExporter;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DebtController extends Controller
{
    public function __construct(
        protected PurchaseReceivingService $receivingService
    ) {}

    public function index(Request $request): View
    {
        $data = $this->buildReport($request, paginate: true);

        return view('debts.index', $data);
    }

    public function export(Request $request): StreamedResponse
    {
        $data = $this->buildReport($request, paginate: false);
        $html = view('debts.export', $data)->render();

        return ExcelExporter::downloadFromHtml($html, 'data-utang-'.now()->format('Ymd-His').'.xlsx', 'generic');
    }

    public function exportPdf(Request $request)
    {
        $data = $this->buildReport($request, paginate: false);
        $pdf = Pdf::loadView('debts.export-pdf', $data)->setPaper('a4', 'landscape');

        return $pdf->download('data-utang-'.now()->format('Ymd-His').'.pdf');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildReport(Request $request, bool $paginate): array
    {
        $user = $request->user();
        if (! $user->isSuperAdminOrAdminPusat() && ! $user->hasAnyRole([Role::ADMIN_GUDANG, Role::ADMIN_CABANG])) {
            abort(403, __('Unauthorized.'));
        }

        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', 'in:outstanding,lunas'],
            'lingkup' => ['nullable', 'in:internal,eksternal'],
            'warehouse_id' => ['nullable', 'integer'],
            'branch_id' => ['nullable', 'integer'],
            'location_type' => ['nullable', 'in:warehouse,branch'],
            'q' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $search = trim((string) ($validated['q'] ?? $validated['search'] ?? $request->input('q', $request->input('search', ''))));
        $dateFrom = $validated['date_from'] ?? null;
        $dateTo = $validated['date_to'] ?? null;
        $statusFilter = $validated['status'] ?? $request->get('status', 'outstanding');
        $lingkupFilter = $validated['lingkup'] ?? $request->get('lingkup', '');
        if (! in_array($lingkupFilter, [Distributor::LINGKUP_INTERNAL, Distributor::LINGKUP_EKSTERNAL], true)) {
            $lingkupFilter = '';
        }

        $query = Purchase::with(['distributor', 'warehouse', 'branch', 'user', 'details.product.category', 'details.units', 'payments'])
            ->where('status', '!=', Purchase::STATUS_CANCELLED)
            ->orderBy('due_date')
            ->orderByDesc('purchase_date')
            ->orderByDesc('id');

        $canFilterLocation = false;
        $filterLocked = false;
        $locationLabel = null;
        $lockedBranchId = null;
        $lockedWarehouseId = null;

        if (! $user->isSuperAdminOrAdminPusat()) {
            if ($user->hasAnyRole([Role::ADMIN_CABANG]) && $user->branch_id) {
                $query->where('branch_id', $user->branch_id);
                $filterLocked = true;
                $lockedBranchId = (int) $user->branch_id;
                $locationLabel = __('Cabang').': '.(Branch::find($user->branch_id)?->name ?? '');
            } elseif ($user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->warehouse_id) {
                $query->where('warehouse_id', $user->warehouse_id);
                $filterLocked = true;
                $lockedWarehouseId = (int) $user->warehouse_id;
                $locationLabel = __('Gudang').': '.(Warehouse::find($user->warehouse_id)?->name ?? '');
            } elseif (! $user->branch_id && ! $user->warehouse_id) {
                abort(403, __('User branch or warehouse not set.'));
            }
        } else {
            $canFilterLocation = true;
            if ($request->filled('warehouse_id')) {
                $query->where('warehouse_id', $request->warehouse_id);
            } elseif ($request->filled('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }
        }

        if ($dateFrom) {
            $query->whereDate('purchase_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('purchase_date', '<=', $dateTo);
        }

        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(function ($q) use ($term) {
                $q->where('invoice_number', 'like', $term)
                    ->orWhereHas('distributor', fn ($d) => $d->where('name', 'like', $term));
            });
        }

        $all = $query->get()->map(function (Purchase $p) {
            $summary = $this->receivingService->summarize($p);

            return (object) [
                'purchase' => $p,
                'summary' => $summary,
                'remaining' => (float) $summary['remaining_recognized'],
                'recognized' => (float) $summary['recognized_debt'],
            ];
        })->filter(fn ($r) => (float) $r->summary['invoice_total'] > 0.02 || (float) $r->recognized > 0.02);

        if ($statusFilter === 'lunas') {
            $filtered = $all->filter(fn ($r) => (float) $r->recognized > 0.02 && (float) $r->remaining <= 0.02)->values();
        } else {
            $filtered = $all->filter(fn ($r) => (float) $r->remaining > 0.02)->values();
        }

        $isInternalVendor = function ($r): bool {
            $lingkup = $r->purchase->distributor?->lingkup_vendor ?? Distributor::LINGKUP_EKSTERNAL;

            return $lingkup === Distributor::LINGKUP_INTERNAL;
        };

        if ($lingkupFilter === Distributor::LINGKUP_INTERNAL) {
            $filtered = $filtered->filter($isInternalVendor)->values();
        } elseif ($lingkupFilter === Distributor::LINGKUP_EKSTERNAL) {
            $filtered = $filtered->reject($isInternalVendor)->values();
        }

        $totalUnpaid = $statusFilter === 'outstanding'
            ? (float) $filtered->sum('remaining')
            : 0.0;
        $totalUnpaidInternal = $statusFilter === 'outstanding'
            ? (float) $filtered->filter($isInternalVendor)->sum('remaining')
            : 0.0;
        $totalUnpaidEksternal = $statusFilter === 'outstanding'
            ? (float) $filtered->reject($isInternalVendor)->sum('remaining')
            : 0.0;

        $today = Carbon::today();
        $dueWarningDays = 3;
        $mapRow = function ($r) use ($today, $dueWarningDays, $isInternalVendor) {
            $p = $r->purchase;
            $dueDate = $p->due_date;
            $daysUntilDue = $dueDate ? $today->diffInDays($dueDate, false) : null;
            $isDueSoon = $dueDate && $daysUntilDue !== null && $daysUntilDue >= 0 && $daysUntilDue < $dueWarningDays;
            $lingkup = $p->distributor?->lingkup_vendor ?? Distributor::LINGKUP_EKSTERNAL;

            return (object) [
                'purchase' => $p,
                'remaining' => $r->remaining,
                'recognized' => $r->recognized,
                'invoice_total' => (float) $r->summary['invoice_total'],
                'summary' => $r->summary,
                'due_date' => $dueDate,
                'is_due_soon' => $isDueSoon,
                'days_until_due' => $daysUntilDue,
                'lingkup_vendor' => $lingkup,
                'is_internal' => $isInternalVendor($r),
            ];
        };

        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);
        $branches = Branch::orderBy('name')->get(['id', 'name']);

        $filterMeta = $this->buildFilterMeta(
            $dateFrom,
            $dateTo,
            $statusFilter,
            $lingkupFilter,
            $search,
            $locationLabel,
            $canFilterLocation,
            $request
        );

        if ($paginate) {
            $page = max(1, (int) $request->get('page', 1));
            $perPage = 20;
            $slice = $filtered->slice(($page - 1) * $perPage, $perPage)->values();
            $purchases = new \Illuminate\Pagination\LengthAwarePaginator(
                $slice,
                $filtered->count(),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
            $rows = $purchases->getCollection()->map($mapRow);
            $purchases->setCollection($rows);

            return compact(
                'purchases',
                'rows',
                'totalUnpaid',
                'totalUnpaidInternal',
                'totalUnpaidEksternal',
                'warehouses',
                'branches',
                'canFilterLocation',
                'filterLocked',
                'locationLabel',
                'lockedBranchId',
                'lockedWarehouseId',
                'statusFilter',
                'lingkupFilter',
                'dateFrom',
                'dateTo',
                'search',
                'filterMeta'
            );
        }

        /** @var Collection<int, object> $rows */
        $rows = $filtered->map($mapRow)->values();

        return [
            'rows' => $rows,
            'totalUnpaid' => $totalUnpaid,
            'totalUnpaidInternal' => $totalUnpaidInternal,
            'totalUnpaidEksternal' => $totalUnpaidEksternal,
            'totalRecognized' => (float) $rows->sum('recognized'),
            'totalInvoice' => (float) $rows->sum('invoice_total'),
            'totalPaid' => (float) $rows->sum(fn ($r) => (float) ($r->purchase->total_paid ?? 0)),
            'count' => $rows->count(),
            'statusFilter' => $statusFilter,
            'lingkupFilter' => $lingkupFilter,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'search' => $search,
            'filterMeta' => $filterMeta,
            'warehouses' => $warehouses,
            'branches' => $branches,
            'canFilterLocation' => $canFilterLocation,
            'filterLocked' => $filterLocked,
            'locationLabel' => $locationLabel,
            'lockedBranchId' => $lockedBranchId,
            'lockedWarehouseId' => $lockedWarehouseId,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function buildFilterMeta(
        ?string $dateFrom,
        ?string $dateTo,
        string $statusFilter,
        string $lingkupFilter,
        string $search,
        ?string $locationLabel,
        bool $canFilterLocation,
        Request $request
    ): array {
        $locationLine = $locationLabel;
        if (! $locationLine && $canFilterLocation) {
            if ($request->filled('warehouse_id')) {
                $locationLine = __('Gudang').': '.(Warehouse::find($request->warehouse_id)?->name ?? '#'.$request->warehouse_id);
            } elseif ($request->filled('branch_id')) {
                $locationLine = __('Cabang').': '.(Branch::find($request->branch_id)?->name ?? '#'.$request->branch_id);
            } else {
                $locationLine = __('Semua Lokasi');
            }
        }
        $locationLine = $locationLine ?: __('Semua Lokasi');

        $period = ($dateFrom || $dateTo)
            ? (($dateFrom ? Carbon::parse($dateFrom)->format('d/m/Y') : '…').' — '.($dateTo ? Carbon::parse($dateTo)->format('d/m/Y') : '…'))
            : __('Semua Periode');

        $lingkupLine = match ($lingkupFilter) {
            Distributor::LINGKUP_INTERNAL => __('Utang Internal'),
            Distributor::LINGKUP_EKSTERNAL => __('Utang Eksternal'),
            default => __('Semua Lingkup'),
        };

        return [
            'locationLine' => $locationLine,
            'periodLine' => $period,
            'statusLine' => $statusFilter === 'lunas' ? __('Lunas') : __('Outstanding (Belum Lunas)'),
            'lingkupLine' => $lingkupLine,
            'searchLine' => $search !== '' ? $search : '-',
        ];
    }

    public function paymentHistory(Purchase $purchase): View
    {
        $user = request()->user();
        if (! $user->isSuperAdminOrAdminPusat()) {
            if ($user->hasAnyRole([Role::ADMIN_CABANG]) && $purchase->branch_id !== $user->branch_id) {
                abort(403);
            }
            if ($user->hasAnyRole([Role::ADMIN_GUDANG]) && $purchase->warehouse_id !== $user->warehouse_id) {
                abort(403);
            }
        }
        $purchase->load(['distributor', 'payments.paymentMethod', 'payments.user']);

        return view('debts.payment-history', compact('purchase'));
    }
}
