<?php

namespace App\Http\Controllers;

use App\Http\Requests\ServiceRequest;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Service;
use App\Services\ServiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        if (! $user->isSuperAdmin()) {
            if (! $user->branch_id) {
                abort(403, __('User branch not set.'));
            }
            $query->where('branch_id', $user->branch_id);
        } elseif ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
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

        $services = $query->paginate(20)->withQueryString();
        $branches = $user->isSuperAdmin()
            ? Branch::orderBy('name')->get(['id', 'name'])
            : Branch::whereKey($user->branch_id)->get(['id', 'name']);

        return view('services.index', compact('services', 'branches'));
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

        return view('services.create', compact('branches', 'customers', 'paymentMethods'));
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

            $service = $this->serviceService->create(
                $branchId,
                $customerId,
                $request->laptop_type,
                $request->laptop_detail,
                $request->damage_description,
                (float) $request->service_cost,
                (float) $request->service_price,
                $request->entry_date,
                $request->input('payments', []),
                $request->description,
                $user->id
            );
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

        $service->load(['branch', 'user', 'customer', 'payments.paymentMethod']);

        $paymentMethods = PaymentMethod::query()
            ->where('is_active', true)
            ->orderBy('jenis_pembayaran')
            ->orderBy('nama_bank')
            ->orderBy('id')
            ->get(['id', 'jenis_pembayaran', 'nama_bank', 'atas_nama_bank', 'no_rekening']);

        return view('services.show', compact('service', 'paymentMethods'));
    }

    public function edit(Service $service): View
    {
        $user = auth()->user();
        if (! $user->isSuperAdmin() && $user->branch_id && $service->branch_id !== $user->branch_id) {
            abort(403, __('Unauthorized.'));
        }
        if ($service->status !== Service::STATUS_OPEN) {
            abort(403, __('Service tidak dapat diedit (sudah selesai atau dibatalkan).'));
        }

        $branches = $user->isSuperAdmin()
            ? Branch::orderBy('name')->get()
            : Branch::whereKey($user->branch_id)->get();

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

        $service->load(['customer', 'payments.paymentMethod']);

        return view('services.edit', compact('service', 'branches', 'customers', 'paymentMethods'));
    }

    public function update(ServiceRequest $request, Service $service): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdmin() && $user->branch_id && $service->branch_id !== $user->branch_id) {
            abort(403, __('Unauthorized.'));
        }

        try {
            $customerId = $this->resolveCustomerId($request);

            $this->serviceService->update(
                $service,
                $customerId,
                $request->laptop_type,
                $request->laptop_detail,
                $request->damage_description,
                (float) $request->service_cost,
                (float) $request->service_price,
                $request->entry_date,
                $request->input('payments', []),
                $request->description
            );
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

    public function cancel(Service $service): RedirectResponse
    {
        $user = auth()->user();
        if (! $user->isSuperAdmin() && $user->branch_id && $service->branch_id !== $user->branch_id) {
            abort(403, __('Unauthorized.'));
        }

        try {
            $this->serviceService->cancel($service);
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

        $service->load(['branch', 'user', 'customer', 'payments.paymentMethod']);

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

        $customer = Customer::create([
            'name' => $name,
            'phone' => $request->input('customer_new_phone'),
            'address' => $request->input('customer_new_address'),
            'is_active' => true,
        ]);

        return (int) $customer->id;
    }
}
