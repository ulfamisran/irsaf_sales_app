<?php

namespace App\Http\Controllers;

use App\Http\Requests\ServiceRequest;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Role;
use App\Models\Warehouse;
use App\Models\ExpenseCategory;
use App\Models\CashFlow;
use App\Models\PaymentMethod;
use App\Models\Service;
use App\Models\ServiceMaterial;
use App\Models\AuditLog;
use App\Services\ServiceService;
use App\Services\KasBalanceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use InvalidArgumentException;

class ServiceController extends Controller
{
    public function __construct(
        protected ServiceService $serviceService
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $query = Service::with(['branch', 'user', 'customer'])
            ->orderByDesc('entry_date')
            ->orderByDesc('id');

        $canFilterLocation = false;
        $filterLocked = false;
        $locationLabel = null;

        if (! $user->isSuperAdminOrAdminPusat()) {
            if ($user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR]) && $user->branch_id) {
                $query->where('branch_id', $user->branch_id);
                $filterLocked = true;
                $branch = Branch::find($user->branch_id);
                $locationLabel = __('Cabang') . ': ' . ($branch?->name ?? '#' . $user->branch_id);
            } elseif ($user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->warehouse_id) {
                $query->whereRaw('1 = 0');
                $filterLocked = true;
                $warehouse = Warehouse::find($user->warehouse_id);
                $locationLabel = __('Gudang') . ': ' . ($warehouse?->name ?? '#' . $user->warehouse_id);
            } elseif (! $user->branch_id && ! $user->warehouse_id) {
                abort(403, __('User branch or warehouse not set.'));
            }
        } else {
            $canFilterLocation = true;
            if ($request->filled('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }
        }
        if ($request->filled('date_from')) {
            $query->whereDate('entry_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('entry_date', '<=', $request->date_to);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"));
            });
        }

        $serviceTotals = (clone $query)
            ->whereIn('status', [Service::STATUS_OPEN, Service::STATUS_COMPLETED])
            ->with('serviceMaterials')
            ->get();
        $totalService = (float) $serviceTotals->sum(fn (Service $service) => (float) $service->total_service_price);
        $totalMaterialExpense = (float) $serviceTotals->sum(fn (Service $service) => (float) $service->materials_total_price);
        $totalServiceNet = $totalService - $totalMaterialExpense;
        $pmBranchId = $user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR]) && $user->branch_id
            ? (int) $user->branch_id
            : ($user->isSuperAdminOrAdminPusat() && $request->filled('branch_id') ? (int) $request->branch_id : null);
        $pmWarehouseId = $user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->warehouse_id ? (int) $user->warehouse_id : null;
        $paymentMethods = PaymentMethod::query()
            ->where('is_active', true)
            ->forLocation($pmBranchId, $pmWarehouseId)
            ->orderBy('jenis_pembayaran')
            ->orderBy('nama_bank')
            ->orderBy('no_rekening')
            ->get(['id', 'jenis_pembayaran', 'nama_bank', 'atas_nama_bank', 'no_rekening']);
        $paymentMethodTotals = DB::table('service_payments')
            ->join('services', 'service_payments.service_id', '=', 'services.id')
            ->when($user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR]) && $user->branch_id, fn ($q) => $q->where('services.branch_id', $user->branch_id))
            ->when($user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->warehouse_id, fn ($q) => $q->whereRaw('1 = 0'))
            ->when($user->isSuperAdminOrAdminPusat() && $request->filled('branch_id'), fn ($q) => $q->where('services.branch_id', $request->branch_id))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('services.entry_date', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('services.entry_date', '<=', $request->date_to))
            ->when($request->filled('status'), fn ($q) => $q->where('services.status', $request->status))
            ->whereIn('services.status', [Service::STATUS_OPEN, Service::STATUS_COMPLETED])
            ->selectRaw('service_payments.payment_method_id, SUM(service_payments.amount) as total')
            ->groupBy('service_payments.payment_method_id')
            ->pluck('total', 'service_payments.payment_method_id');
        $services = $query->paginate(20)->withQueryString();
        $branches = $user->isSuperAdminOrAdminPusat()
            ? Branch::orderBy('name')->get(['id', 'name'])
            : ($user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR]) && $user->branch_id ? Branch::whereKey($user->branch_id)->get(['id', 'name']) : collect());

        return view('services.index', compact('services', 'branches', 'canFilterLocation', 'filterLocked', 'locationLabel', 'totalService', 'totalMaterialExpense', 'totalServiceNet', 'paymentMethods', 'paymentMethodTotals'));
    }

    public function export(Request $request): Response
    {
        $user = $request->user();
        $query = Service::with(['branch', 'user', 'customer', 'payments', 'serviceMaterials'])
            ->orderByDesc('entry_date')
            ->orderByDesc('id');
        $branchLine = __('Semua');

        if (! $user->isSuperAdminOrAdminPusat()) {
            if ($user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR]) && $user->branch_id) {
                $query->where('branch_id', $user->branch_id);
            } elseif ($user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->warehouse_id) {
                $query->whereRaw('1 = 0');
            } elseif (! $user->branch_id && ! $user->warehouse_id) {
                abort(403, __('User branch or warehouse not set.'));
            }
        } else {
            if ($request->filled('branch_id')) {
                $query->where('branch_id', $request->branch_id);
                $b = Branch::find($request->branch_id);
                $branchLine = $b ? __('Cabang') . ' ' . $b->name : __('Cabang');
            }
        }
        if ($request->filled('date_from')) {
            $query->whereDate('entry_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('entry_date', '<=', $request->date_to);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"));
            });
        }

        if (! $user->isSuperAdminOrAdminPusat() && $user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR]) && $user->branch_id) {
            $b = Branch::find($user->branch_id);
            $branchLine = $b ? __('Cabang') . ' ' . $b->name : __('Cabang');
        }

        $services = $query->where('status', '!=', Service::STATUS_CANCEL)->get();
        $autoDateFrom = $services->isNotEmpty() ? optional($services->min('entry_date'))->format('Y-m-d') : null;
        $autoDateTo = $services->isNotEmpty() ? optional($services->max('entry_date'))->format('Y-m-d') : null;
        $filterMeta = [
            'dateFrom' => $request->filled('date_from') ? (string) $request->date_from : ($autoDateFrom ?: '-'),
            'dateTo' => $request->filled('date_to') ? (string) $request->date_to : ($autoDateTo ?: '-'),
            'branchLine' => $branchLine,
        ];
        $filename = 'riwayat-service-' . now()->format('Ymd-His') . '.xls';
        $html = view('services.export', compact('services', 'filterMeta'))->render();

        return response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function exportPdf(Request $request)
    {
        $user = $request->user();
        $query = Service::with(['branch', 'user', 'customer', 'payments', 'serviceMaterials'])
            ->orderByDesc('entry_date')
            ->orderByDesc('id');
        $branchLine = __('Semua');

        if (! $user->isSuperAdminOrAdminPusat()) {
            if ($user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR]) && $user->branch_id) {
                $query->where('branch_id', $user->branch_id);
            } elseif ($user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->warehouse_id) {
                $query->whereRaw('1 = 0');
            } elseif (! $user->branch_id && ! $user->warehouse_id) {
                abort(403, __('User branch or warehouse not set.'));
            }
        } else {
            if ($request->filled('branch_id')) {
                $query->where('branch_id', $request->branch_id);
                $b = Branch::find($request->branch_id);
                $branchLine = $b ? __('Cabang') . ' ' . $b->name : __('Cabang');
            }
        }
        if ($request->filled('date_from')) {
            $query->whereDate('entry_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('entry_date', '<=', $request->date_to);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"));
            });
        }

        if (! $user->isSuperAdminOrAdminPusat() && $user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR]) && $user->branch_id) {
            $b = Branch::find($user->branch_id);
            $branchLine = $b ? __('Cabang') . ' ' . $b->name : __('Cabang');
        }

        $services = $query->where('status', '!=', Service::STATUS_CANCEL)->get();
        $autoDateFrom = $services->isNotEmpty() ? optional($services->min('entry_date'))->format('Y-m-d') : null;
        $autoDateTo = $services->isNotEmpty() ? optional($services->max('entry_date'))->format('Y-m-d') : null;
        $filterMeta = [
            'dateFrom' => $request->filled('date_from') ? (string) $request->date_from : ($autoDateFrom ?: '-'),
            'dateTo' => $request->filled('date_to') ? (string) $request->date_to : ($autoDateTo ?: '-'),
            'branchLine' => $branchLine,
        ];
        $pdf = Pdf::loadView('services.export-pdf', compact('services', 'filterMeta'))->setPaper('a4', 'landscape');

        return $pdf->download('riwayat-service-' . now()->format('Ymd-His') . '.pdf');
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

        $branchIdForData = $user->isSuperAdmin() ? null : (int) $user->branch_id;
        $customers = $branchIdForData
            ? Customer::query()->where('branch_id', $branchIdForData)->where('is_active', true)->orderBy('name')->limit(500)->get(['id', 'name', 'phone'])
            : collect();
        $paymentMethods = $branchIdForData
            ? PaymentMethod::query()->where('branch_id', $branchIdForData)->where('is_active', true)->orderBy('jenis_pembayaran')->orderBy('nama_bank')->orderBy('id')->get(['id', 'jenis_pembayaran', 'nama_bank', 'atas_nama_bank', 'no_rekening'])
            : collect();

        $branchIds = $branches->pluck('id')->toArray();
        $saldoMapBranch = (new KasBalanceService)->getSaldoPerBranchAndPm($branchIds);

        return view('services.create', compact('branches', 'customers', 'paymentMethods', 'saldoMapBranch'));
    }

    public function store(ServiceRequest $request): RedirectResponse
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
            $status = $request->input('status', 'open');
            $isRelease = $status === 'release';

            $materialsInput = $isRelease ? ($request->input('materials', []) ?? []) : [];
            if (is_array($materialsInput) && ! empty($materialsInput)) {
                $this->ensureMaterialSaldo($branchId, $materialsInput);
            }
            $materialsTotal = $isRelease ? $this->sumMaterialsTotalPrice($materialsInput) : 0.0;

            $service = $this->serviceService->create(
                $branchId,
                $customerId,
                $request->laptop_type,
                $request->laptop_detail,
                $request->damage_description,
                0.0,
                (float) ($request->service_fee ?? 0),
                $request->entry_date,
                $request->input('payments', []),
                $request->description,
                $user->id,
                $materialsTotal,
                $status
            );

            if ($isRelease && is_array($materialsInput) && ! empty($materialsInput)) {
                $this->storeMaterials($service, $materialsInput, replace: true, userId: $user->id);
                $this->refreshPaymentStatus($service);
            }
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('services.show', $service)->with('success', __('Service berhasil disimpan.'));
    }

    public function show(Service $service): View
    {
        $user = auth()->user();
        if (! $user->isSuperAdmin() && $user->branch_id && $service->branch_id !== $user->branch_id) {
            abort(403, __('Unauthorized.'));
        }

        $service->load(['branch', 'user', 'customer', 'payments.paymentMethod', 'serviceMaterials']);

        $paymentMethods = PaymentMethod::query()
            ->where('is_active', true)
            ->forLocation($service->branch_id, null)
            ->orderBy('jenis_pembayaran')
            ->orderBy('nama_bank')
            ->orderBy('id')
            ->get(['id', 'jenis_pembayaran', 'nama_bank', 'atas_nama_bank', 'no_rekening']);

        $saldoMapBranch = (new KasBalanceService)->getSaldoPerBranchAndPm([$service->branch_id]);

        return view('services.show', compact('service', 'paymentMethods', 'saldoMapBranch'));
    }

    public function edit(Service $service): View
    {
        $user = auth()->user();
        $canEdit = $user->isSuperAdminOrAdminPusat()
            || $user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR]);
        if (! $canEdit) {
            abort(403, __('Unauthorized.'));
        }
        if (! $user->isSuperAdminOrAdminPusat() && $user->branch_id && $service->branch_id !== $user->branch_id) {
            abort(403, __('Unauthorized.'));
        }
        if ($service->status !== Service::STATUS_OPEN) {
            abort(403, __('Service tidak dapat diedit (sudah selesai atau dibatalkan).'));
        }

        $branches = $user->isSuperAdminOrAdminPusat()
            ? Branch::orderBy('name')->get()
            : Branch::whereKey($user->branch_id)->get();

        $customers = Customer::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name', 'phone']);

        $paymentMethods = PaymentMethod::query()
            ->where('is_active', true)
            ->forLocation($service->branch_id, null)
            ->orderBy('jenis_pembayaran')
            ->orderBy('nama_bank')
            ->orderBy('id')
            ->get(['id', 'jenis_pembayaran', 'nama_bank', 'atas_nama_bank', 'no_rekening']);

        $service->load(['customer', 'payments.paymentMethod', 'serviceMaterials']);

        $branchIds = $branches->pluck('id')->toArray();
        $saldoMapBranch = (new KasBalanceService)->getSaldoPerBranchAndPm($branchIds);

        return view('services.edit', compact('service', 'branches', 'customers', 'paymentMethods', 'saldoMapBranch'));
    }

    public function update(ServiceRequest $request, Service $service): RedirectResponse
    {
        $user = $request->user();
        $canEdit = $user->isSuperAdminOrAdminPusat()
            || $user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR]);
        if (! $canEdit) {
            abort(403, __('Unauthorized.'));
        }
        if (! $user->isSuperAdminOrAdminPusat() && $user->branch_id && $service->branch_id !== $user->branch_id) {
            abort(403, __('Unauthorized.'));
        }

        try {
            $customerId = $this->resolveCustomerId($request);

            $materialsInput = $request->input('materials', null);
            $materialsTotal = is_array($materialsInput) ? $this->sumMaterialsTotalPrice($materialsInput) : null;
            if (is_array($materialsInput) && ! empty($materialsInput)) {
                $this->ensureMaterialSaldo((int) $service->branch_id, $materialsInput);
            }

            $markAsReleased = (bool) $request->input('mark_release');

            $this->serviceService->update(
                $service,
                $customerId,
                $request->laptop_type,
                $request->laptop_detail,
                $request->damage_description,
                0.0,
                (float) $request->service_fee,
                $request->entry_date,
                $request->input('payments', []),
                $request->description,
                $materialsTotal,
                $markAsReleased
            );

            if (is_array($materialsInput)) {
                $this->storeMaterials($service, $materialsInput, replace: true, userId: $user->id);
                $this->refreshPaymentStatus($service);
            }

            $pickupStatus = $request->boolean('mark_picked_up') ? Service::PICKUP_SUDAH : Service::PICKUP_BELUM;
            $service->update(['pickup_status' => $pickupStatus]);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'service.update',
                'reference_type' => 'service',
                'reference_id' => $service->id,
                'description' => 'Update service ' . $service->invoice_number,
            ]);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('services.show', $service)->with('success', __('Service berhasil diperbarui.'));
    }

    public function addPayment(Request $request, Service $service): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdmin() && $user->branch_id && $service->branch_id !== $user->branch_id) {
            abort(403, __('Unauthorized.'));
        }

        $validated = $request->validate([
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.payment_method_id' => ['required', 'exists:payment_methods,id'],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'payments.*.notes' => ['nullable', 'string'],
            'exit_date' => ['nullable', 'date'],
            'mark_completed' => ['nullable', 'boolean'],
            'mark_picked_up' => ['nullable', 'boolean'],
        ]);

        try {
            $this->serviceService->addPayment(
                $service,
                $validated['payments'],
                $validated['exit_date'] ?? null,
                (bool) ($validated['mark_completed'] ?? false),
                (bool) ($validated['mark_picked_up'] ?? false),
                $user->id
            );
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('services.show', $service)->with('success', __('Pembayaran berhasil ditambahkan.'));
    }

    public function addMaterials(Request $request, Service $service): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdmin() && $user->branch_id && $service->branch_id !== $user->branch_id) {
            abort(403, __('Unauthorized.'));
        }
        if ($service->status !== Service::STATUS_OPEN) {
            abort(403, __('Service tidak dapat diedit (sudah selesai atau dibatalkan).'));
        }

        $validated = $request->validate([
            'materials' => ['required', 'array', 'min:1'],
            'materials.*.name' => ['required', 'string', 'max:150'],
            'materials.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'materials.*.payment_method_id' => ['required', 'exists:payment_methods,id'],
            'materials.*.price' => ['required', 'numeric', 'min:0'],
            'materials.*.notes' => ['nullable', 'string'],
        ]);

        try {
            $this->ensureMaterialSaldo((int) $service->branch_id, $validated['materials']);

            foreach ($validated['materials'] as $mat) {
                $qty = round((float) ($mat['quantity'] ?? 0), 2);
                if ($qty <= 0) {
                    continue;
                }
                $material = ServiceMaterial::create([
                    'service_id' => $service->id,
                    'payment_method_id' => (int) ($mat['payment_method_id'] ?? 0),
                    'name' => trim((string) ($mat['name'] ?? '')),
                    'quantity' => $qty,
                    'hpp' => 0,
                    'price' => round((float) ($mat['price'] ?? 0), 2),
                    'notes' => $mat['notes'] ?? null,
                ]);
                $this->createMaterialCashOut($service, $material, (int) ($mat['payment_method_id'] ?? 0), $user->id);
            }
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $service->refresh();
        $service->update(['payment_status' => Service::PAYMENT_BELUM_LUNAS]);

        return redirect()->route('services.show', $service)->with('success', __('Material service berhasil disimpan.'));
    }

    public function deleteMaterial(Service $service, ServiceMaterial $material): RedirectResponse
    {
        $user = auth()->user();
        if (! $user->isSuperAdmin() && $user->branch_id && $service->branch_id !== $user->branch_id) {
            abort(403, __('Unauthorized.'));
        }
        if ($service->status !== Service::STATUS_OPEN) {
            abort(403, __('Service tidak dapat diedit (sudah selesai atau dibatalkan).'));
        }
        if ($material->service_id !== $service->id) {
            abort(404);
        }

        CashFlow::where('reference_type', CashFlow::REFERENCE_EXPENSE)
            ->where('reference_id', $material->id)
            ->where('expense_category_id', $this->getSparepartExpenseCategoryId())
            ->delete();
        $material->delete();

        $service->refresh();
        $service->update(['payment_status' => Service::PAYMENT_BELUM_LUNAS]);

        return redirect()->route('services.show', $service)->with('success', __('Material service berhasil dihapus.'));
    }

    public function complete(Request $request, Service $service): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdmin() && $user->branch_id && $service->branch_id !== $user->branch_id) {
            abort(403, __('Unauthorized.'));
        }

        $validated = $request->validate([
            'exit_date' => ['nullable', 'date'],
            'mark_picked_up' => ['nullable', 'boolean'],
        ]);

        try {
            $this->serviceService->complete(
                $service,
                $validated['exit_date'] ?? null,
                (bool) ($validated['mark_picked_up'] ?? false)
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('services.show', $service)->with('success', __('Service berhasil diselesaikan.'));
    }

    public function markPickedUp(Service $service): RedirectResponse
    {
        $user = auth()->user();
        if (! $user->isSuperAdmin() && $user->branch_id && $service->branch_id !== $user->branch_id) {
            abort(403, __('Unauthorized.'));
        }

        try {
            $this->serviceService->markPickedUp($service);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('services.show', $service)->with('success', __('Status pengambilan berhasil diperbarui.'));
    }

    public function cancel(Request $request, Service $service): RedirectResponse
    {
        $user = auth()->user();
        if (! $user->isSuperAdmin()) {
            abort(403, __('Unauthorized.'));
        }
        if (! $user->isSuperAdmin() && $user->branch_id && $service->branch_id !== $user->branch_id) {
            abort(403, __('Unauthorized.'));
        }
        if (! in_array($service->status, [Service::STATUS_OPEN, Service::STATUS_COMPLETED], true)) {
            return back()->with('error', __('Service tidak dapat dibatalkan.'));
        }

        $validated = $request->validate([
            'cancel_reason' => ['required', 'string', 'max:255'],
            'confirm_released' => ['nullable', 'boolean'],
        ]);
        if ($service->status === Service::STATUS_COMPLETED && empty($validated['confirm_released'])) {
            return back()->with('error', __('Konfirmasi tambahan wajib untuk membatalkan transaksi released.'));
        }

        try {
            $this->serviceService->cancel($service, $user->id, $validated['cancel_reason']);
            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'service.cancel',
                'reference_type' => 'service',
                'reference_id' => $service->id,
                'description' => 'Cancel service ' . $service->invoice_number . '. Alasan: ' . $validated['cancel_reason'],
            ]);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('services.show', $service)->with('success', __('Service berhasil dibatalkan.'));
    }

    public function invoice(Service $service): View
    {
        $user = auth()->user();
        if (! $user->isSuperAdmin() && $user->branch_id && $service->branch_id !== $user->branch_id) {
            abort(403, __('Unauthorized.'));
        }

        $service->load(['branch', 'user', 'customer', 'payments.paymentMethod', 'serviceMaterials']);

        return view('services.invoice', compact('service'));
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
        $branchId = $user->isSuperAdmin()
            ? (int) $request->branch_id
            : (int) $user->branch_id;

        $customer = Customer::create([
            'name' => $name,
            'phone' => $request->input('customer_new_phone'),
            'address' => $request->input('customer_new_address'),
            'is_active' => true,
            'placement_type' => $branchId ? 'cabang' : null,
            'branch_id' => $branchId ?: null,
        ]);

        return (int) $customer->id;
    }

    /**
     * @param  array<int, array{name?: string, quantity?: float, hpp?: float, price?: float, notes?: string|null}>  $materials
     */
    private function storeMaterials(Service $service, array $materials, bool $replace = false, ?int $userId = null): void
    {
        if ($replace) {
            $oldMaterialIds = ServiceMaterial::where('service_id', $service->id)->pluck('id')->toArray();
            if (! empty($oldMaterialIds)) {
                CashFlow::where('reference_type', CashFlow::REFERENCE_EXPENSE)
                    ->whereIn('reference_id', $oldMaterialIds)
                    ->where('expense_category_id', $this->getSparepartExpenseCategoryId())
                    ->delete();
            }
            ServiceMaterial::where('service_id', $service->id)->delete();
        }

        foreach ($materials as $mat) {
            $name = trim((string) ($mat['name'] ?? ''));
            $qty = round((float) ($mat['quantity'] ?? 0), 2);
            if ($name === '' || $qty <= 0) {
                continue;
            }
            $paymentMethodId = (int) ($mat['payment_method_id'] ?? 0);
            $material = ServiceMaterial::create([
                'service_id' => $service->id,
                'payment_method_id' => $paymentMethodId,
                'name' => $name,
                'quantity' => $qty,
                'hpp' => 0,
                'price' => round((float) ($mat['price'] ?? 0), 2),
                'notes' => $mat['notes'] ?? null,
            ]);
            $this->createMaterialCashOut($service, $material, $paymentMethodId, $userId ?? auth()->id());
        }
    }

    /**
     * @param  array<int, array{name?: string, quantity?: float, price?: float}>  $materials
     */
    private function sumMaterialsTotalPrice(array $materials): float
    {
        $sum = 0.0;
        foreach ($materials as $mat) {
            $name = trim((string) ($mat['name'] ?? ''));
            $qty = (float) ($mat['quantity'] ?? 0);
            $price = (float) ($mat['price'] ?? 0);
            if ($name !== '' && $qty > 0 && $price >= 0) {
                $sum += $qty * $price;
            }
        }

        return round($sum, 2);
    }

    private function getSparepartExpenseCategoryId(): int
    {
        $category = ExpenseCategory::firstOrCreate(
            ['code' => 'SP-SVC'],
            [
                'name' => 'Pembelian Sparepart User (SERVICE)',
                'code' => 'SP-SVC',
                'description' => 'Pengeluaran pembelian sparepart untuk service pelanggan',
                'is_active' => true,
            ]
        );

        return (int) $category->id;
    }

    /**
     * @param  array<int, array{name?: string, payment_method_id?: int, quantity?: float, price?: float}>  $materials
     */
    private function ensureMaterialSaldo(int $branchId, array $materials): void
    {
        $totals = [];
        foreach ($materials as $mat) {
            $name = trim((string) ($mat['name'] ?? ''));
            $pmId = (int) ($mat['payment_method_id'] ?? 0);
            $qty = (float) ($mat['quantity'] ?? 0);
            $price = (float) ($mat['price'] ?? 0);
            if ($name === '' || $pmId <= 0 || $qty <= 0 || $price < 0) {
                continue;
            }
            $totals[$pmId] = ($totals[$pmId] ?? 0) + ($qty * $price);
        }
        if (empty($totals)) {
            return;
        }

        $kasService = new KasBalanceService();
        foreach ($totals as $pmId => $total) {
            $saldo = $kasService->getSaldoForLocation('branch', $branchId, (int) $pmId);
            if ($saldo <= 0) {
                throw new InvalidArgumentException(__('Sumber dana tidak tersedia (saldo 0).'));
            }
            if ($total > $saldo) {
                throw new InvalidArgumentException(__('Saldo tidak mencukupi untuk pembelian material. Saldo tersedia: Rp :saldo', [
                    'saldo' => number_format($saldo, 0, ',', '.'),
                ]));
            }
        }
    }

    private function createMaterialCashOut(Service $service, ServiceMaterial $material, int $paymentMethodId, int $userId): void
    {
        $amount = round((float) $material->quantity * (float) $material->price, 2);
        if ($amount <= 0) {
            return;
        }

        CashFlow::create([
            'branch_id' => $service->branch_id,
            'warehouse_id' => null,
            'type' => CashFlow::TYPE_OUT,
            'amount' => $amount,
            'description' => 'Pembelian Sparepart User (SERVICE) - ' . $service->invoice_number . ' - ' . $material->name,
            'reference_type' => CashFlow::REFERENCE_EXPENSE,
            'reference_id' => $material->id,
            'expense_category_id' => $this->getSparepartExpenseCategoryId(),
            'payment_method_id' => $paymentMethodId,
            'transaction_date' => now()->toDateString(),
            'user_id' => $userId,
        ]);
    }

    private function refreshPaymentStatus(Service $service): void
    {
        $service->refresh();
        if ($service->status === Service::STATUS_OPEN) {
            $service->update(['payment_status' => Service::PAYMENT_BELUM_LUNAS]);
            return;
        }
        $totalPrice = (float) $service->total_service_price;
        $paid = (float) $service->total_paid;
        $service->update([
            'payment_status' => $paid >= $totalPrice - 0.02 ? Service::PAYMENT_LUNAS : Service::PAYMENT_BELUM_LUNAS,
        ]);
    }
}
