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
use App\Services\TransactionProofService;
use App\Support\CatatRange;
use App\Support\DefaultDateRange;
use App\Support\DocumentSearch;
use App\Support\ExcelExporter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RentalController extends Controller
{
    use Concerns\RedirectsFailedRelease;

    public function __construct(
        protected RentalService $rentalService,
        protected TransactionProofService $proofs
    ) {}

    public function index(Request $request): View
    {
        DefaultDateRange::applyMonthToDate($request);
        $user = $request->user();

        $query = Rental::with(['branch', 'customer', 'warehouse', 'user'])
            ->orderByDesc('pickup_date')
            ->orderByDesc('id');

        $canFilterLocation = false;
        $filterLocked = false;
        $locationLabel = null;

        if (! $user->isSuperAdminOrAdminPusat()) {
            if ($user->warehouse_id) {
                $query->where('warehouse_id', $user->warehouse_id);
                $filterLocked = true;
                $warehouse = Warehouse::find($user->warehouse_id);
                $locationLabel = __('Gudang') . ': ' . ($warehouse?->name ?? '#' . $user->warehouse_id);
            } elseif ($user->branch_id) {
                $query->where('branch_id', $user->branch_id);
                $filterLocked = true;
                $branch = Branch::find($user->branch_id);
                $locationLabel = __('Cabang') . ': ' . ($branch?->name ?? '#' . $user->branch_id);
            } else {
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
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('return_status')) {
            $query->where('return_status', $request->return_status);
        }
        DocumentSearch::applyRental($query, $request->input('search'));
        CatatRange::apply($query, $request, 'rentals.created_at');
        CatatRange::applyBackdate($query, $request, 'rentals.pickup_date', 'rentals.created_at');

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
        $pmBranchId = $user->branch_id && ! $user->warehouse_id
            ? (int) $user->branch_id
            : ($user->isSuperAdminOrAdminPusat() && $request->filled('branch_id') ? (int) $request->branch_id : null);
        $pmWarehouseId = $user->warehouse_id
            ? (int) $user->warehouse_id
            : ($user->isSuperAdminOrAdminPusat() && $request->filled('warehouse_id') ? (int) $request->warehouse_id : null);
        $paymentMethods = PaymentMethod::query()
            ->where('is_active', true)
            ->forLocation($pmBranchId, $pmWarehouseId)
            ->orderBy('jenis_pembayaran')
            ->orderBy('nama_bank')
            ->orderBy('no_rekening')
            ->get(['id', 'jenis_pembayaran', 'nama_bank', 'atas_nama_bank', 'no_rekening']);
        $paymentMethodTotals = DB::table('rental_payments')
            ->join('rentals', 'rental_payments.rental_id', '=', 'rentals.id')
            ->when($user->branch_id && ! $user->warehouse_id, fn ($q) => $q->where('rentals.branch_id', $user->branch_id))
            ->when($user->warehouse_id, fn ($q) => $q->where('rentals.warehouse_id', $user->warehouse_id))
            ->when($user->isSuperAdminOrAdminPusat() && $request->filled('warehouse_id'), fn ($q) => $q->where('rentals.warehouse_id', $request->warehouse_id))
            ->when($user->isSuperAdminOrAdminPusat() && $request->filled('branch_id'), fn ($q) => $q->where('rentals.branch_id', $request->branch_id))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('rentals.pickup_date', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('rentals.pickup_date', '<=', $request->date_to))
            ->when($request->filled('return_status'), fn ($q) => $q->where('rentals.return_status', $request->return_status))
            ->whereIn('rentals.status', [Rental::STATUS_OPEN, Rental::STATUS_RELEASED])
            ->when($request->filled('search'), function ($q) use ($request) {
                $matched = Rental::query()->select('id');
                DocumentSearch::applyRental($matched, $request->input('search'));
                $q->whereIn('rentals.id', $matched);
            })
            ->when(true, function ($q) use ($request) {
                CatatRange::apply($q, $request, 'rentals.created_at');
                CatatRange::applyBackdate($q, $request, 'rentals.pickup_date', 'rentals.created_at');
            })
            ->selectRaw('rental_payments.payment_method_id, SUM(rental_payments.amount) as total')
            ->groupBy('rental_payments.payment_method_id')
            ->pluck('total', 'rental_payments.payment_method_id');
        $rentals = $query->paginate(20)->withQueryString();
        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);
        $branches = Branch::orderBy('name')->get(['id', 'name']);

        return view('rentals.index', compact('rentals', 'warehouses', 'branches', 'canFilterLocation', 'filterLocked', 'locationLabel', 'totalRental', 'paymentMethods', 'paymentMethodTotals'));
    }

    public function export(Request $request): StreamedResponse
    {
        DefaultDateRange::applyMonthToDate($request);
        $user = $request->user();
        $query = Rental::with(['branch', 'customer', 'warehouse', 'user', 'payments'])
            ->orderByDesc('pickup_date')
            ->orderByDesc('id');
        $branchLine = __('Semua');

        if (! $user->isSuperAdminOrAdminPusat()) {
            if ($user->branch_id && ! $user->warehouse_id) {
                $query->where('branch_id', $user->branch_id);
            } elseif ($user->warehouse_id) {
                $query->where('warehouse_id', $user->warehouse_id);
            } elseif (! $user->branch_id && ! $user->warehouse_id) {
                abort(403, __('User branch or warehouse not set.'));
            }
        }

        if ($request->filled('date_from')) {
            $query->whereDate('pickup_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('pickup_date', '<=', $request->date_to);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('return_status')) {
            $query->where('return_status', $request->return_status);
        }
        DocumentSearch::applyRental($query, $request->input('search'));
        CatatRange::apply($query, $request, 'rentals.created_at');
        CatatRange::applyBackdate($query, $request, 'rentals.pickup_date', 'rentals.created_at');
        if ($user->isSuperAdminOrAdminPusat()) {
            if ($request->filled('warehouse_id')) {
                $query->where('warehouse_id', $request->warehouse_id);
                $w = Warehouse::find($request->warehouse_id);
                $branchLine = $w ? __('Gudang') . ' ' . $w->name : __('Gudang');
            }
            if ($request->filled('branch_id')) {
                $query->where('branch_id', $request->branch_id);
                $b = Branch::find($request->branch_id);
                $branchLine = $b ? __('Cabang') . ' ' . $b->name : __('Cabang');
            }
        } elseif ($user->branch_id && ! $user->warehouse_id) {
            $b = Branch::find($user->branch_id);
            $branchLine = $b ? __('Cabang') . ' ' . $b->name : __('Cabang');
        } elseif ($user->warehouse_id) {
            $w = Warehouse::find($user->warehouse_id);
            $branchLine = $w ? __('Gudang') . ' ' . $w->name : __('Gudang');
        }

        $rentals = $query->where('status', '!=', Rental::STATUS_CANCEL)->get();
        $autoDateFrom = $rentals->isNotEmpty() ? optional($rentals->min('pickup_date'))->format('Y-m-d') : null;
        $autoDateTo = $rentals->isNotEmpty() ? optional($rentals->max('pickup_date'))->format('Y-m-d') : null;
        $filterMeta = [
            'dateFrom' => $request->filled('date_from') ? (string) $request->date_from : ($autoDateFrom ?: '-'),
            'dateTo' => $request->filled('date_to') ? (string) $request->date_to : ($autoDateTo ?: '-'),
            'branchLine' => $branchLine,
        ];
        $filename = 'riwayat-penyewaan-' . now()->format('Ymd-His') . '.xlsx';
        $html = view('rentals.export', compact('rentals', 'filterMeta'))->render();

        return ExcelExporter::downloadFromHtml($html, $filename, 'rentals');
    }

    public function exportPdf(Request $request)
    {
        DefaultDateRange::applyMonthToDate($request);
        $user = $request->user();
        $query = Rental::with(['branch', 'customer', 'warehouse', 'user', 'payments'])
            ->orderByDesc('pickup_date')
            ->orderByDesc('id');
        $branchLine = __('Semua');

        if (! $user->isSuperAdminOrAdminPusat()) {
            if ($user->branch_id && ! $user->warehouse_id) {
                $query->where('branch_id', $user->branch_id);
            } elseif ($user->warehouse_id) {
                $query->where('warehouse_id', $user->warehouse_id);
            } elseif (! $user->branch_id && ! $user->warehouse_id) {
                abort(403, __('User branch or warehouse not set.'));
            }
        }

        if ($request->filled('date_from')) {
            $query->whereDate('pickup_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('pickup_date', '<=', $request->date_to);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('return_status')) {
            $query->where('return_status', $request->return_status);
        }
        DocumentSearch::applyRental($query, $request->input('search'));
        CatatRange::apply($query, $request, 'rentals.created_at');
        CatatRange::applyBackdate($query, $request, 'rentals.pickup_date', 'rentals.created_at');
        if ($user->isSuperAdminOrAdminPusat()) {
            if ($request->filled('warehouse_id')) {
                $query->where('warehouse_id', $request->warehouse_id);
                $w = Warehouse::find($request->warehouse_id);
                $branchLine = $w ? __('Gudang') . ' ' . $w->name : __('Gudang');
            }
            if ($request->filled('branch_id')) {
                $query->where('branch_id', $request->branch_id);
                $b = Branch::find($request->branch_id);
                $branchLine = $b ? __('Cabang') . ' ' . $b->name : __('Cabang');
            }
        } elseif ($user->branch_id && ! $user->warehouse_id) {
            $b = Branch::find($user->branch_id);
            $branchLine = $b ? __('Cabang') . ' ' . $b->name : __('Cabang');
        } elseif ($user->warehouse_id) {
            $w = Warehouse::find($user->warehouse_id);
            $branchLine = $w ? __('Gudang') . ' ' . $w->name : __('Gudang');
        }

        $rentals = $query->where('status', '!=', Rental::STATUS_CANCEL)->get();
        $autoDateFrom = $rentals->isNotEmpty() ? optional($rentals->min('pickup_date'))->format('Y-m-d') : null;
        $autoDateTo = $rentals->isNotEmpty() ? optional($rentals->max('pickup_date'))->format('Y-m-d') : null;
        $filterMeta = [
            'dateFrom' => $request->filled('date_from') ? (string) $request->date_from : ($autoDateFrom ?: '-'),
            'dateTo' => $request->filled('date_to') ? (string) $request->date_to : ($autoDateTo ?: '-'),
            'branchLine' => $branchLine,
        ];
        $pdf = Pdf::loadView('rentals.export-pdf', compact('rentals', 'filterMeta'))->setPaper('a4', 'landscape');

        return $pdf->download('riwayat-penyewaan-' . now()->format('Ymd-His') . '.pdf');
    }

    public function create(): View
    {
        $user = auth()->user();
        if (! $user->isSuperAdminOrAdminPusat() && ! $user->branch_id && ! $user->warehouse_id) {
            abort(403, __('User branch or warehouse not set.'));
        }

        $locationOptions = \App\Services\LocationService::getLocationOptionsForUser($user);
        $warehouses = $locationOptions['warehouses'];
        $branches = $locationOptions['branches'];

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
        $this->proofs->validateUploads($request, null, false);

        try {
            $customerId = $this->resolveCustomerId($request);
            $user = $request->user();
            if (! $user->isSuperAdminOrAdminPusat()) {
                $request->merge([
                    'pickup_date' => \Illuminate\Support\Carbon::now('Asia/Makassar')->toDateString(),
                ]);
            }

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
                $request->user()->id,
                app(\App\Services\AuthorizationService::class)->enabled('rentals')
            );
            $this->proofs->storeUploaded($request, $rental, $user->id);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('rentals.show', $rental)->with('success', __('Penyewaan berhasil disimpan.'));
    }

    public function show(Rental $rental): View
    {
        $user = auth()->user();
        \App\Support\UserLocationScope::assertCanAccessLocation(
            $user,
            $rental->branch_id ? (int) $rental->branch_id : null,
            $rental->warehouse_id ? (int) $rental->warehouse_id : null
        );

        $rental->load(['branch', 'warehouse', 'user', 'customer', 'items.product', 'payments.paymentMethod', 'proofs', 'returnUser', 'cancelUser']);
        $paymentMethods = $this->paymentMethodsForRental($rental);

        return view('rentals.show', compact('rental', 'paymentMethods'));
    }

    public function edit(Rental $rental): View
    {
        $user = auth()->user();
        if (! $user->isSuperAdminOrAdminPusat()) {
            abort(403, __('Unauthorized.'));
        }
        if ($rental->status !== Rental::STATUS_OPEN || $rental->effects_pending || $rental->return_status === Rental::RETURN_SUDAH) {
            abort(403, __('Penyewaan tidak dapat diedit (sudah diaktifkan menunggu otorisasi, unit sudah dikembalikan, dirilis, atau dibatalkan).'));
        }
        if (app(\App\Services\AuthorizationService::class)->blocksMutation($rental)) {
            abort(403, __('Dokumen sedang menunggu otorisasi.'));
        }

        $rental->load(['items.product', 'customer', 'payments.paymentMethod', 'proofs']);
        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);
        $branches = Branch::orderBy('name')->get(['id', 'name']);
        $customers = Customer::query()
            ->where(function ($q) use ($rental) {
                $q->where('is_active', true);
                if ($rental->customer_id) {
                    $q->orWhere('id', $rental->customer_id);
                }
            })
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name', 'phone']);

        // Pastikan pelanggan transaksi selalu ada di opsi (meski di luar limit / nonaktif).
        if ($rental->customer_id && ! $customers->contains(fn ($c) => (int) $c->id === (int) $rental->customer_id)) {
            $currentCustomer = $rental->customer ?? Customer::query()->find($rental->customer_id);
            if ($currentCustomer) {
                $customers = $customers->prepend($currentCustomer)->unique('id')->values();
            }
        }

        $paymentMethods = $this->paymentMethodsForRental($rental);

        return view('rentals.edit', compact('rental', 'warehouses', 'branches', 'customers', 'paymentMethods'));
    }

    public function update(RentalRequest $request, Rental $rental): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdminOrAdminPusat()) {
            abort(403, __('Unauthorized.'));
        }
        if ($rental->status !== Rental::STATUS_OPEN || $rental->effects_pending || $rental->return_status === Rental::RETURN_SUDAH) {
            return back()->with('error', __('Penyewaan tidak dapat diedit (unit sudah dikembalikan, dirilis, menunggu aktivasi, atau dibatalkan).'));
        }
        if (app(\App\Services\AuthorizationService::class)->blocksMutation($rental)) {
            return back()->with('error', __('Dokumen sedang menunggu otorisasi.'));
        }

        $this->proofs->validateUploads($request, $rental, false);

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
            $this->proofs->storeUploaded($request, $rental, $user->id);
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
        \App\Support\UserLocationScope::assertCanAccessLocation(
            $user,
            $rental->branch_id ? (int) $rental->branch_id : null,
            $rental->warehouse_id ? (int) $rental->warehouse_id : null
        );

        $validated = $request->validate([
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.payment_method_id' => ['required', 'exists:payment_methods,id'],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'payments.*.notes' => ['nullable', 'string'],
        ]);

        try {
            $authorization = app(\App\Services\AuthorizationService::class);
            if ($authorization->blocksMutation($rental)) {
                throw new InvalidArgumentException(__('Dokumen sedang menunggu otorisasi.'));
            }
            if ($rental->effects_pending) {
                throw new InvalidArgumentException(__('Aktifkan penyewaan dulu sebelum mencatat pelunasan.'));
            }
            if ($rental->requires_authorization) {
                $authorization->assertFollowUp($rental);
            }
            $this->rentalService->addPayment($rental, $validated['payments'], $user->id);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('rentals.show', $rental)->with('success', __('Pembayaran berhasil ditambahkan.'));
    }

    public function release(Request $request, Rental $rental): RedirectResponse
    {
        try {
            // Release efek stok/kas (setelah otorisasi open). Final status RELEASED butuh otorisasi kedua.
            app(\App\Services\AuthorizationService::class)->assertCanApplyDeferredEffects($rental);
            $this->rentalService->releaseEffects($rental, (int) $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('rentals.show', $rental)->with('success', __('Penyewaan diaktifkan. Catat pelunasan & pengembalian unit di bawah.'));
    }

    public function markReturned(Rental $rental): RedirectResponse
    {
        $user = auth()->user();
        \App\Support\UserLocationScope::assertCanAccessLocation(
            $user,
            $rental->branch_id ? (int) $rental->branch_id : null,
            $rental->warehouse_id ? (int) $rental->warehouse_id : null
        );

        $this->proofs->assertHasProof($rental);

        $itemIds = $rental->items()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $validated = request()->validate([
            'actual_return_date' => ['required', 'date', 'after_or_equal:'.$rental->pickup_date?->toDateString()],
            'return_notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'in:'.implode(',', $itemIds ?: [0])],
            'items.*.returned' => ['accepted'],
            'items.*.return_condition' => ['required', 'in:baik,bermasalah'],
            'items.*.return_notes' => ['nullable', 'string', 'max:1000'],
            'payments' => ['nullable', 'array'],
            'payments.*.payment_method_id' => ['required_with:payments', 'exists:payment_methods,id'],
            'payments.*.amount' => ['required_with:payments', 'numeric', 'min:0.01'],
            'payments.*.notes' => ['nullable', 'string'],
        ], [
            'items.required' => __('Centang dan lengkapi kondisi semua unit yang dikembalikan.'),
            'items.*.returned.accepted' => __('Semua unit harus dicentang sebagai dikembalikan.'),
            'items.*.return_condition.required' => __('Pilih kondisi Baik atau Bermasalah untuk setiap unit.'),
            'actual_return_date.required' => __('Tanggal pengembalian wajib diisi.'),
        ]);

        if (count($validated['items']) !== count($itemIds)) {
            return back()->withInput()->with('error', __('Centang dan lengkapi kondisi semua unit yang dikembalikan.'));
        }

        try {
            $this->rentalService->markReturned(
                $rental,
                $validated['payments'] ?? [],
                $user->id,
                $validated['actual_return_date'],
                $validated['return_notes'] ?? null,
                $validated['items']
            );
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $msg = $rental->fresh()->status === Rental::STATUS_RELEASED
            ? __('Pelunasan & pengembalian dicatat. Status sewa Release.')
            : __('Pelunasan & pengembalian unit dicatat. Ajukan otorisasi release, lalu ubah status menjadi Release.');

        return redirect()->route('rentals.show', $rental)->with('success', $msg);
    }

    public function finalizeRelease(Request $request, Rental $rental): RedirectResponse
    {
        $user = $request->user();
        \App\Support\UserLocationScope::assertCanAccessLocation(
            $user,
            $rental->branch_id ? (int) $rental->branch_id : null,
            $rental->warehouse_id ? (int) $rental->warehouse_id : null
        );

        try {
            $this->rentalService->finalizeRelease($rental);
        } catch (InvalidArgumentException $e) {
            return $this->redirectReleaseFailure($request, $rental, $e->getMessage());
        }

        return redirect()->route('rentals.show', $rental)->with('success', __('Status penyewaan diubah menjadi Release.'));
    }

    /**
     * Release dari daftar: aktifkan efek, atau finalize status jika pengembalian sudah dicatat.
     */
    public function listRelease(Request $request, Rental $rental): RedirectResponse
    {
        $user = $request->user();
        \App\Support\UserLocationScope::assertCanAccessLocation(
            $user,
            $rental->branch_id ? (int) $rental->branch_id : null,
            $rental->warehouse_id ? (int) $rental->warehouse_id : null
        );

        $auth = app(\App\Services\AuthorizationService::class);

        try {
            if ($rental->status !== Rental::STATUS_OPEN) {
                throw new InvalidArgumentException(__('Penyewaan sudah selesai atau dibatalkan.'));
            }

            if ($rental->effects_pending) {
                $auth->assertCanApplyDeferredEffects($rental);
                $this->rentalService->releaseEffects($rental, (int) $user->id);

                return redirect()
                    ->route('rentals.show', $rental)
                    ->with('success', __('Penyewaan diaktifkan. Lengkapi pelunasan & pengembalian, lalu release lagi.'))
                    ->with('release_incomplete', true);
            }

            $auth->assertReadyForRelease($rental);

            if (! $rental->requires_authorization) {
                $this->rentalService->finalizeRelease($rental);
            } else {
                $auth->assertCanRelease($rental);
                $this->rentalService->finalizeRelease($rental);
            }
        } catch (InvalidArgumentException $e) {
            return $this->redirectReleaseFailure($request, $rental, $e->getMessage());
        }

        return redirect()->route('rentals.show', $rental)->with('success', __('Status penyewaan diubah menjadi Release.'));
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
        \App\Support\UserLocationScope::assertCanAccessLocation(
            $user,
            $rental->branch_id ? (int) $rental->branch_id : null,
            $rental->warehouse_id ? (int) $rental->warehouse_id : null
        );
        try {
            app(\App\Services\AuthorizationService::class)->assertInvoice($rental);
        } catch (\InvalidArgumentException $e) {
            abort(403, $e->getMessage());
        }

        $rental->load(['branch', 'warehouse', 'user', 'customer', 'items.product', 'payments.paymentMethod']);

        return view('rentals.invoice', compact('rental'));
    }

    public function report(Rental $rental): View
    {
        $user = auth()->user();
        \App\Support\UserLocationScope::assertCanAccessLocation(
            $user,
            $rental->branch_id ? (int) $rental->branch_id : null,
            $rental->warehouse_id ? (int) $rental->warehouse_id : null
        );

        $rental->load(['branch', 'warehouse', 'user', 'customer', 'items.product', 'payments.paymentMethod', 'proofs']);

        return view('rentals.report', compact('rental'));
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

    /**
     * Metode pembayaran untuk lokasi sewa + metode yang sudah dipakai transaksi
     * (agar baris pembayaran lama tetap bisa ditampilkan di edit/show).
     *
     * @return \Illuminate\Support\Collection<int, PaymentMethod>
     */
    private function paymentMethodsForRental(Rental $rental)
    {
        $locationType = $rental->location_type
            ?? ($rental->warehouse_id ? Rental::LOCATION_WAREHOUSE : Rental::LOCATION_BRANCH);
        $branchId = $locationType === Rental::LOCATION_BRANCH ? $rental->branch_id : null;
        $warehouseId = $locationType === Rental::LOCATION_WAREHOUSE ? $rental->warehouse_id : null;

        $paymentMethods = PaymentMethod::query()
            ->where('is_active', true)
            ->forLocation($branchId, $warehouseId)
            ->orderBy('jenis_pembayaran')
            ->orderBy('nama_bank')
            ->orderBy('id')
            ->get(['id', 'jenis_pembayaran', 'nama_bank', 'atas_nama_bank', 'no_rekening']);

        $usedIds = $rental->payments
            ->pluck('payment_method_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $missingIds = $usedIds->diff($paymentMethods->pluck('id')->map(fn ($id) => (int) $id));
        if ($missingIds->isNotEmpty()) {
            $extra = PaymentMethod::query()
                ->whereIn('id', $missingIds->all())
                ->get(['id', 'jenis_pembayaran', 'nama_bank', 'atas_nama_bank', 'no_rekening']);
            $paymentMethods = $paymentMethods->concat($extra)->unique('id')->values();
        }

        return $paymentMethods;
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

