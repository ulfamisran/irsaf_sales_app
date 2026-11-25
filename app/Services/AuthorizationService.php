<?php

namespace App\Services;

use App\Models\AuthorizationAssignee;
use App\Models\AuthorizationPickerRole;
use App\Models\AuthorizationRequest;
use App\Models\AuthorizationSetting;
use App\Models\Role;
use App\Models\Distribution;
use App\Models\ProductUnit;
use App\Models\Purchase;
use App\Models\Rental;
use App\Models\Sale;
use App\Models\Service;
use App\Models\UnitExchange;
use App\Models\User;
use App\Support\CatatRange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class AuthorizationService
{
    /** @var array<string, string> */
    private static array $phaseCache = [];

    public const MODULES = [
        'purchases' => ['label' => 'Pembelian', 'model' => Purchase::class],
        'stock-mutations' => ['label' => 'Distribusi', 'model' => Distribution::class],
        'sales' => ['label' => 'Penjualan', 'model' => Sale::class],
        'unit-exchanges' => ['label' => 'Tukar Unit', 'model' => UnitExchange::class],
        'rentals' => ['label' => 'Sewa', 'model' => Rental::class],
        'services' => ['label' => 'Service', 'model' => Service::class],
    ];

    public function enabled(string $module): bool
    {
        if (! isset(self::MODULES[$module]) || ! Schema::hasTable('authorization_settings')) {
            return false;
        }

        return AuthorizationSetting::query()
            ->where('module', $module)
            ->where('is_active', true)
            ->exists();
    }

    public function moduleFor(Model $model): ?string
    {
        foreach (self::MODULES as $module => $meta) {
            if ($model instanceof $meta['model']) {
                return $module;
            }
        }

        return null;
    }

    public function label(string $module): string
    {
        return self::MODULES[$module]['label'] ?? $module;
    }

    public function attach(Model $model): void
    {
        $module = $this->moduleFor($model);
        if ($module === null || ! $this->enabled($module) || ! $model->isFillable('requires_authorization')) {
            return;
        }
        if ((bool) $model->getAttribute('requires_authorization')) {
            return;
        }

        $model->forceFill(['requires_authorization' => true])->save();
    }

    public function phase(Model $model): string
    {
        $cacheKey = $model::class.'#'.$model->getKey();
        if (array_key_exists($cacheKey, self::$phaseCache)) {
            return self::$phaseCache[$cacheKey];
        }

        if (! (bool) $model->getAttribute('requires_authorization')) {
            return self::$phaseCache[$cacheKey] = 'off';
        }

        $status = $this->latest($model)?->status ?? 'draft';
        if ($status === AuthorizationRequest::STATUS_APPROVED && $this->isCommitted($model)) {
            $status = 'released';
        }

        return self::$phaseCache[$cacheKey] = $status;
    }

    /**
     * @param  iterable<int, Model>  $models
     */
    public function warmPhases(iterable $models): void
    {
        $grouped = [];
        $modelsByKey = [];
        foreach ($models as $model) {
            if (! $model instanceof Model || ! $model->getKey()) {
                continue;
            }
            $cacheKey = $model::class.'#'.$model->getKey();
            if (array_key_exists($cacheKey, self::$phaseCache)) {
                continue;
            }
            if (! (bool) $model->getAttribute('requires_authorization')) {
                self::$phaseCache[$cacheKey] = 'off';
                continue;
            }
            $grouped[$model::class][] = (int) $model->getKey();
            $modelsByKey[$cacheKey] = $model;
        }

        foreach ($grouped as $type => $ids) {
            $latestById = AuthorizationRequest::query()
                ->where('reference_type', $type)
                ->whereIn('reference_id', array_values(array_unique($ids)))
                ->orderByDesc('id')
                ->get()
                ->unique('reference_id')
                ->keyBy('reference_id');
            foreach ($ids as $id) {
                $cacheKey = $type.'#'.$id;
                $status = $latestById->get($id)?->status ?? 'draft';
                $model = $modelsByKey[$cacheKey] ?? null;
                if ($status === AuthorizationRequest::STATUS_APPROVED && $model && $this->isCommitted($model)) {
                    $status = 'released';
                }
                self::$phaseCache[$cacheKey] = $status;
            }
        }
    }

    public function isApproved(Model $model): bool
    {
        $phase = $this->phase($model);

        return $phase === 'off'
            || $phase === AuthorizationRequest::STATUS_APPROVED
            || $phase === 'released';
    }

    public function blocksMutation(Model $model): bool
    {
        return $this->phase($model) === AuthorizationRequest::STATUS_PENDING;
    }

    public function allowsCommit(Model $model): bool
    {
        return $this->isApproved($model);
    }

    /**
     * Service butuh 2 kali otorisasi: (1) open, (2) data lengkap sebelum release.
     */
    public function approvedAuthorizationCount(Model $model): int
    {
        if (! Schema::hasTable('authorization_requests') || ! $model->getKey()) {
            return 0;
        }

        return (int) AuthorizationRequest::query()
            ->where('reference_type', $model::class)
            ->where('reference_id', $model->getKey())
            ->where('status', AuthorizationRequest::STATUS_APPROVED)
            ->count();
    }

    public function serviceNeedsReleaseAuthorization(Service $service): bool
    {
        return (bool) $service->getAttribute('requires_authorization')
            && $service->status === Service::STATUS_OPEN
            && $this->phase($service) === AuthorizationRequest::STATUS_APPROVED
            && $this->approvedAuthorizationCount($service) < 2;
    }

    public function rentalNeedsReleaseAuthorization(Rental $rental): bool
    {
        return (bool) $rental->getAttribute('requires_authorization')
            && $rental->status === Rental::STATUS_OPEN
            && ! (bool) $rental->effects_pending
            && $this->phase($rental) === AuthorizationRequest::STATUS_APPROVED
            && $this->approvedAuthorizationCount($rental) < 2;
    }

    /**
     * Baris daftar perlu highlight "belum ajukan" (sama untuk penjualan/service/sewa).
     */
    public function needsSubmitHighlight(Model $model): bool
    {
        if (! (bool) $model->getAttribute('requires_authorization')) {
            return false;
        }

        $phase = $this->phase($model);
        if ($phase === 'draft') {
            return true;
        }

        // Tolak otorisasi ke-2 → dokumen masih open, harus diajukan ulang.
        if ($phase !== AuthorizationRequest::STATUS_REJECTED) {
            return false;
        }

        return ($model instanceof Service && $model->status === Service::STATUS_OPEN)
            || ($model instanceof Rental && $model->status === Rental::STATUS_OPEN);
    }

    public function serviceReadyToRelease(Service $service): bool
    {
        if (! (bool) $service->getAttribute('requires_authorization')) {
            return $service->status === Service::STATUS_OPEN;
        }

        return $service->status === Service::STATUS_OPEN
            && $this->phase($service) === AuthorizationRequest::STATUS_APPROVED
            && $this->approvedAuthorizationCount($service) >= 2;
    }

    public function rentalReadyToRelease(Rental $rental): bool
    {
        if (! (bool) $rental->getAttribute('requires_authorization')) {
            return $rental->status === Rental::STATUS_OPEN && ! (bool) $rental->effects_pending;
        }

        return $rental->status === Rental::STATUS_OPEN
            && ! (bool) $rental->effects_pending
            && $this->phase($rental) === AuthorizationRequest::STATUS_APPROVED
            && $this->approvedAuthorizationCount($rental) >= 2;
    }

    public function needsDualReleaseAuthorization(Model $model): bool
    {
        return ($model instanceof Service || $model instanceof Rental)
            && (bool) $model->getAttribute('requires_authorization');
    }

    public function assertCanApplyDeferredEffects(Model $model): void
    {
        if ($this->blocksMutation($model)) {
            throw new InvalidArgumentException(__('Dokumen sedang menunggu otorisasi.'));
        }
        if (! $this->allowsCommit($model)) {
            throw new InvalidArgumentException(__('Release hanya bisa dilakukan setelah otorisasi disetujui.'));
        }
    }

    public function assertCanRelease(Model $model): void
    {
        if ($this->blocksMutation($model)) {
            throw new InvalidArgumentException(__('Dokumen sedang menunggu otorisasi.'));
        }
        if (! $this->allowsCommit($model)) {
            throw new InvalidArgumentException(__('Release hanya bisa dilakukan setelah otorisasi disetujui.'));
        }
        if ($this->needsDualReleaseAuthorization($model) && $this->approvedAuthorizationCount($model) < 2) {
            throw new InvalidArgumentException(__('Sebelum release, lengkapi data lalu ajukan otorisasi lagi. Release hanya setelah otorisasi kedua disetujui.'));
        }
    }

    /**
     * Syarat bisnis sebelum Release: lunas; service sudah diambil; sewa sudah dikembalikan.
     */
    public function assertReadyForRelease(Model $model): void
    {
        match (true) {
            $model instanceof Sale => $this->assertDocumentPaidOff(
                $model->isPaidOff(),
                __('Transaksi harus lunas sebelum release. Lengkapi pembayaran di form edit.')
            ),
            $model instanceof Purchase => $this->assertDocumentPaidOff(
                $model->isPaidOffAgainstInvoice(),
                __('Transaksi harus lunas sebelum release. Lengkapi pembayaran di form edit.')
            ),
            $model instanceof Distribution => $this->assertDocumentPaidOff(
                $model->isPaidOff(),
                __('Transaksi harus lunas sebelum release. Lengkapi pembayaran di halaman tambah pembayaran.')
            ),
            $model instanceof Service => $this->assertServiceReadyForRelease($model),
            $model instanceof Rental => $this->assertRentalReadyForRelease($model),
            default => null,
        };
    }

    private function assertDocumentPaidOff(bool $paidOff, string $message): void
    {
        if (! $paidOff) {
            throw new InvalidArgumentException($message);
        }
    }

    private function assertServiceReadyForRelease(Service $service): void
    {
        $total = (float) $service->total_service_price;
        $paid = (float) $service->total_paid;
        if ($paid <= 0 && $service->relationLoaded('payments')) {
            $paid = (float) $service->payments->sum('amount');
        } elseif ($paid <= 0) {
            $paid = (float) $service->payments()->sum('amount');
        }
        if ($total > 0 && $paid < $total - 0.02) {
            throw new InvalidArgumentException(__('Transaksi harus lunas sebelum release. Lengkapi pembayaran di form edit.'));
        }
        if ($service->pickup_status !== Service::PICKUP_SUDAH) {
            throw new InvalidArgumentException(__('Barang service harus sudah diambil sebelum release. Tandai pengambilan di form edit.'));
        }
    }

    private function assertRentalReadyForRelease(Rental $rental): void
    {
        if ($rental->effects_pending) {
            return;
        }
        if ($rental->return_status !== Rental::RETURN_SUDAH) {
            throw new InvalidArgumentException(__('Barang sewa harus sudah dikembalikan sebelum release. Lengkapi pelunasan & pengembalian di form.'));
        }
        if (! $rental->isPaidOff()) {
            throw new InvalidArgumentException(__('Transaksi harus lunas sebelum release. Lengkapi pelunasan di form edit.'));
        }
    }

    /**
     * Tampilkan tombol Release di daftar aksi jika dokumen masih open dan boleh di-commit.
     */
    public function showListRelease(Model $model): bool
    {
        if ($this->blocksMutation($model)) {
            return false;
        }

        return match (true) {
            $model instanceof Purchase => $model->status === Purchase::STATUS_OPEN && $this->allowsCommit($model),
            $model instanceof Distribution => $model->status === Distribution::STATUS_OPEN && $this->allowsCommit($model),
            $model instanceof Sale => $model->status === Sale::STATUS_OPEN && $this->allowsCommit($model),
            $model instanceof UnitExchange => $model->status === UnitExchange::STATUS_OPEN && $this->allowsCommit($model),
            $model instanceof Service => $model->status === Service::STATUS_OPEN
                && $this->allowsCommit($model)
                && $this->serviceReadyToRelease($model),
            $model instanceof Rental => $model->status === Rental::STATUS_OPEN
                && $this->allowsCommit($model)
                && (
                    (bool) $model->effects_pending
                    || ($this->rentalReadyToRelease($model) && $model->return_status === Rental::RETURN_SUDAH && $model->isPaidOff())
                ),
            default => false,
        };
    }

    /**
     * URL form untuk melengkapi data jika release dari daftar gagal validasi.
     */
    public function releaseCompletionUrl(Model $model): string
    {
        return match (true) {
            $model instanceof Purchase => route('purchases.edit', $model),
            $model instanceof Distribution => route('stock-mutations.add-payment', $model),
            $model instanceof Sale => route('sales.edit', $model),
            $model instanceof UnitExchange => route('sales.unit-exchanges.edit', $model),
            $model instanceof Service => route('services.edit', $model),
            $model instanceof Rental => (! $model->isPaidOff() || $model->return_status !== Rental::RETURN_SUDAH)
                ? route('rentals.show', $model).'#pelunasan-pengembalian'
                : route('rentals.show', $model),
            default => url()->previous() ?: url('/'),
        };
    }

    /**
     * Route POST untuk aksi Release dari daftar.
     */
    public function listReleaseRoute(Model $model): ?string
    {
        return match (true) {
            $model instanceof Purchase => route('purchases.release', $model),
            $model instanceof Distribution => route('stock-mutations.release', $model),
            $model instanceof Sale => route('sales.release', $model),
            $model instanceof UnitExchange => route('sales.unit-exchanges.release', $model),
            $model instanceof Service => route('services.release', $model),
            $model instanceof Rental => route('rentals.list-release', $model),
            default => null,
        };
    }

    public function assertFollowUp(Model $model): void
    {
        if ($this->blocksMutation($model) || ! $this->allowsCommit($model)) {
            throw new InvalidArgumentException(__('Transaksi ini belum diotorisasi.'));
        }
    }

    public function assertInvoice(Model $model): void
    {
        if (! (bool) $model->getAttribute('requires_authorization')) {
            return;
        }
        if (! $this->isCommitted($model)) {
            throw new InvalidArgumentException(__('Invoice baru tersedia setelah dokumen di-release.'));
        }
    }

    public function assertSparepartsReady(Service $service): void
    {
        $purchases = $service->sparePartServicePurchases()->get();
        foreach ($purchases as $purchase) {
            if ($purchase->status === Purchase::STATUS_OPEN || $purchase->status === Purchase::STATUS_CANCELLED) {
                throw new InvalidArgumentException(__('Pembelian sparepart :no belum siap dipakai.', ['no' => $purchase->invoice_number]));
            }
            if ((bool) $purchase->requires_authorization && ! $this->isApproved($purchase)) {
                throw new InvalidArgumentException(__('Pembelian sparepart :no belum diotorisasi.', ['no' => $purchase->invoice_number]));
            }
        }

        $ids = $purchases->pluck('id')->filter()->all();
        if ($ids === []) {
            return;
        }

        $blocked = ProductUnit::query()
            ->whereIn('purchase_id', $ids)
            ->whereIn('status', [
                ProductUnit::STATUS_NOT_IN_STOCK,
                ProductUnit::STATUS_PENDING,
                ProductUnit::STATUS_RETURE,
                ProductUnit::STATUS_KEEP,
            ])
            ->exists();
        if ($blocked) {
            throw new InvalidArgumentException(__('Sparepart belum diterima (masih menunggu cek pembelian).'));
        }
    }

    public function submit(Model $model, User $user): AuthorizationRequest
    {
        $module = $this->moduleFor($model);
        if ($module === null || ! (bool) $model->getAttribute('requires_authorization')) {
            throw new InvalidArgumentException(__('Dokumen ini tidak membutuhkan otorisasi.'));
        }
        if ($this->phase($model) === AuthorizationRequest::STATUS_PENDING) {
            throw new InvalidArgumentException(__('Dokumen ini sudah diajukan.'));
        }

        $phase = $this->phase($model);
        $dualReleaseResubmit = $this->needsDualReleaseAuthorization($model)
            && ! $this->isCommitted($model)
            && $phase === AuthorizationRequest::STATUS_APPROVED
            && $this->approvedAuthorizationCount($model) === 1
            && (! ($model instanceof Rental) || ! (bool) $model->effects_pending);

        if ($phase === AuthorizationRequest::STATUS_APPROVED && ! $dualReleaseResubmit) {
            throw new InvalidArgumentException(__('Dokumen ini sudah disetujui.'));
        }
        if ($phase === 'released') {
            throw new InvalidArgumentException(__('Dokumen ini sudah di-release.'));
        }
        if (! $this->isOpenDocument($model)) {
            throw new InvalidArgumentException(__('Hanya dokumen open yang bisa diajukan.'));
        }

        [$branchId, $warehouseId] = $this->locationOf($model);

        $row = AuthorizationRequest::create([
            'module' => $module,
            'reference_type' => $model::class,
            'reference_id' => $model->getKey(),
            'document_number' => $this->documentNumber($model),
            'status' => AuthorizationRequest::STATUS_PENDING,
            'requested_by' => $user->id,
            'requested_at' => now(),
            'branch_id' => $branchId,
            'warehouse_id' => $warehouseId,
        ]);

        unset(self::$phaseCache[$model::class.'#'.$model->getKey()]);
        \App\Jobs\SendAuthorizationPushNotification::dispatch($row->id)->afterResponse();

        return $row;
    }

    public function approve(AuthorizationRequest $request, User $user): void
    {
        $this->assertCanDecide($request, $user);
        if (! $request->isPending()) {
            throw new InvalidArgumentException(__('Pengajuan ini sudah diputuskan.'));
        }

        $request->update([
            'status' => AuthorizationRequest::STATUS_APPROVED,
            'decided_by' => $user->id,
            'decided_at' => now(),
        ]);
        if ($request->subject) {
            unset(self::$phaseCache[$request->subject::class.'#'.$request->subject->getKey()]);
        }
    }

    public function reject(AuthorizationRequest $request, User $user, string $notes): void
    {
        $notes = trim($notes);
        if ($notes === '') {
            throw new InvalidArgumentException(__('Alasan penolakan wajib diisi.'));
        }
        $this->assertCanDecide($request, $user);
        if (! $request->isPending()) {
            throw new InvalidArgumentException(__('Pengajuan ini sudah diputuskan.'));
        }

        DB::transaction(function () use ($request, $user, $notes) {
            $subject = $request->subject;
            // Tolak otorisasi ke-2 (release): jangan batalkan dokumen, biar bisa diperbaiki & diajukan ulang.
            $skipCancel = ($subject instanceof Service || $subject instanceof Rental)
                && $this->approvedAuthorizationCount($subject) >= 1;

            $request->update([
                'status' => AuthorizationRequest::STATUS_REJECTED,
                'decided_by' => $user->id,
                'decided_at' => now(),
                'notes' => mb_substr($notes, 0, 500),
            ]);
            if (! $skipCancel) {
                $this->cancelSubject($subject, $user, $notes);
            }
            if ($subject) {
                unset(self::$phaseCache[$subject::class.'#'.$subject->getKey()]);
            }
        });
    }

    public function pendingFor(User $user): Builder
    {
        return $this->scoped($user)->where('status', AuthorizationRequest::STATUS_PENDING);
    }

    public function historyFor(User $user): Builder
    {
        return $this->scoped($user)->whereIn('status', [
            AuthorizationRequest::STATUS_APPROVED,
            AuthorizationRequest::STATUS_REJECTED,
        ]);
    }

    /**
     * @return array<string, int>
     */
    public function pendingCounts(User $user, Request $request): array
    {
        $counts = array_fill_keys(array_keys(self::MODULES), 0);
        $rows = $this->applyListFilters($this->pendingFor($user), $request, false)
            ->selectRaw('module, COUNT(*) as aggregate')
            ->groupBy('module')
            ->pluck('aggregate', 'module');
        foreach ($rows as $module => $count) {
            if (isset($counts[$module])) {
                $counts[$module] = (int) $count;
            }
        }

        return $counts;
    }

    /**
     * @return array<string, array{total: int, accepted: int, rejected: int}>
     */
    public function historyCounts(User $user, Request $request): array
    {
        $counts = [];
        foreach (array_keys(self::MODULES) as $module) {
            $counts[$module] = ['total' => 0, 'accepted' => 0, 'rejected' => 0];
        }

        $rows = $this->applyListFilters($this->historyFor($user), $request, true)
            ->selectRaw('module, status, COUNT(*) as aggregate')
            ->groupBy('module', 'status')
            ->get();
        foreach ($rows as $row) {
            if (! isset($counts[$row->module])) {
                continue;
            }
            $counts[$row->module]['total'] += (int) $row->aggregate;
            if ($row->status === AuthorizationRequest::STATUS_APPROVED) {
                $counts[$row->module]['accepted'] += (int) $row->aggregate;
            }
            if ($row->status === AuthorizationRequest::STATUS_REJECTED) {
                $counts[$row->module]['rejected'] += (int) $row->aggregate;
            }
        }

        return $counts;
    }

    public function listQuery(User $user, Request $request, string $tab): Builder
    {
        $query = $tab === 'riwayat' ? $this->historyFor($user) : $this->pendingFor($user);
        $module = (string) $request->input('module', '');
        if (isset(self::MODULES[$module])) {
            $query->where('module', $module);
        }
        $this->applyListFilters($query, $request, $tab === 'riwayat');

        return $query->with(['requester', 'decider', 'branch', 'warehouse', 'subject']);
    }

    /**
     * @param  array<int, int|string>  $ids
     */
    public function approveMany(User $user, array $ids): int
    {
        $saved = 0;
        $rows = AuthorizationRequest::query()
            ->whereIn('id', array_map('intval', $ids))
            ->where('status', AuthorizationRequest::STATUS_PENDING)
            ->get();
        foreach ($rows as $row) {
            try {
                $this->approve($row, $user);
                $saved++;
            } catch (InvalidArgumentException) {
                continue;
            }
        }

        return $saved;
    }

    private function applyListFilters(Builder $query, Request $request, bool $history): Builder
    {
        if ($request->filled('branch_id')) {
            $query->where('branch_id', (int) $request->input('branch_id'));
        }
        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', (int) $request->input('warehouse_id'));
        }
        if ($request->input('location_type') === 'branch') {
            $query->whereNotNull('branch_id')->whereNull('warehouse_id');
        }
        if ($request->input('location_type') === 'warehouse') {
            $query->whereNotNull('warehouse_id');
        }

        if ($request->input('date_basis') === 'transaksi') {
            $this->applyTransactionDateFilters($query, $request);

            return $query;
        }

        $dateField = $history ? 'decided_at' : 'requested_at';
        CatatRange::applyBetween(
            $query,
            $request->filled('date_from') ? (string) $request->input('date_from') : null,
            $request->filled('date_to') ? (string) $request->input('date_to') : null,
            $dateField
        );

        return $query;
    }

    private function applyTransactionDateFilters(Builder $query, Request $request): void
    {
        if (! $request->filled('date_from') && ! $request->filled('date_to')) {
            return;
        }

        $dateColumns = [
            Purchase::class => 'purchase_date',
            Distribution::class => 'distribution_date',
            Sale::class => 'sale_date',
            UnitExchange::class => 'exchange_date',
            Rental::class => 'pickup_date',
            Service::class => 'entry_date',
        ];

        $query->where(function (Builder $outer) use ($request, $dateColumns) {
            foreach ($dateColumns as $modelClass => $column) {
                $outer->orWhere(function (Builder $part) use ($request, $modelClass, $column) {
                    $part->where('reference_type', $modelClass)
                        ->whereHasMorph('subject', [$modelClass], function (Builder $subject) use ($request, $column) {
                            if ($request->filled('date_from')) {
                                $subject->whereDate($column, '>=', $request->input('date_from'));
                            }
                            if ($request->filled('date_to')) {
                                $subject->whereDate($column, '<=', $request->input('date_to'));
                            }
                        });
                });
            }
        });
    }

    public function canOpenQueue(User $user): bool
    {
        return $this->assignedModules($user) !== [];
    }

    /**
     * Modul yang user ini dipilih sebagai otorisator di konfigurasi
     * dan role-nya punya akses Otorisasi untuk modul tersebut.
     *
     * @return list<string>
     */
    public function assignedModules(User $user): array
    {
        if (! Schema::hasTable('authorization_assignees')) {
            return [];
        }

        return AuthorizationAssignee::query()
            ->where('user_id', $user->id)
            ->pluck('module')
            ->filter(fn ($module) => isset(self::MODULES[$module]) && $user->canModule((string) $module, 'authorize'))
            ->unique()
            ->values()
            ->all();
    }

    public function canAuthorizeModule(User $user, string $module): bool
    {
        return in_array($module, $this->assignedModules($user), true);
    }

    /**
     * Role yang boleh muncul di pilihan user otorisasi.
     *
     * @return list<int>
     */
    public function pickerRoleIds(): array
    {
        if (! Schema::hasTable('authorization_picker_roles')) {
            return [];
        }

        return AuthorizationPickerRole::query()
            ->pluck('role_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  array<int, mixed>  $roleIds
     */
    public function savePickerRoles(array $roleIds): void
    {
        if (! Schema::hasTable('authorization_picker_roles')) {
            return;
        }

        $ids = collect($roleIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        AuthorizationPickerRole::query()->delete();
        foreach ($ids as $roleId) {
            if (! Role::query()->whereKey($roleId)->exists()) {
                continue;
            }
            AuthorizationPickerRole::query()->create(['role_id' => $roleId]);
        }
    }

    /**
     * @return array{count: int, items: \Illuminate\Support\Collection<int, AuthorizationRequest>}
     */
    public function notificationSummary(?User $user, int $limit = 8): array
    {
        if (! $user || ! Schema::hasTable('authorization_requests') || ! $this->canOpenQueue($user)) {
            return ['count' => 0, 'items' => collect()];
        }

        return $this->pendingSummary($user, $limit);
    }

    /**
     * @return array{count: int, items: \Illuminate\Support\Collection<int, AuthorizationRequest>}
     */
    public function pendingSummary(User $user, int $limit = 8): array
    {
        if (! $this->canOpenQueue($user) || ! Schema::hasTable('authorization_requests')) {
            return ['count' => 0, 'items' => collect()];
        }

        $count = $this->pendingFor($user)->count();
        $items = $this->pendingFor($user)
            ->with(['requester'])
            ->latest('id')
            ->limit($limit)
            ->get();

        return ['count' => $count, 'items' => $items];
    }

    public function documentUrl(AuthorizationRequest $request): ?string
    {
        $model = $request->subject;
        if (! $model) {
            return null;
        }

        $route = match ($request->module) {
            'purchases' => 'purchases.show',
            'stock-mutations' => 'stock-mutations.show',
            'sales' => 'sales.show',
            'unit-exchanges' => 'sales.unit-exchanges.show',
            'rentals' => 'rentals.show',
            'services' => 'services.show',
            default => null,
        };
        if ($route === null || ! \Illuminate\Support\Facades\Route::has($route)) {
            return null;
        }

        return route($route, $model);
    }

    /**
     * @param  array<string, array{is_active?: mixed, user_ids?: array<int, mixed>}>  $input
     */
    public function saveSettings(array $input): void
    {
        foreach (array_keys(self::MODULES) as $module) {
            $active = filter_var($input[$module]['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $userIds = collect($input[$module]['user_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values();

            AuthorizationSetting::query()->updateOrCreate(
                ['module' => $module],
                ['is_active' => $active]
            );
            AuthorizationAssignee::query()->where('module', $module)->delete();
            foreach ($userIds as $userId) {
                AuthorizationAssignee::query()->create([
                    'module' => $module,
                    'user_id' => $userId,
                ]);
            }
        }
    }

    private function scoped(User $user): Builder
    {
        $query = AuthorizationRequest::query()->with(['requester', 'decider']);
        $modules = $this->assignedModules($user);
        if ($modules === []) {
            return $query->whereRaw('1 = 0');
        }

        $query->whereIn('module', $modules);

        // Super Admin: semua lokasi. Admin Pusat & role lain: hanya lokasi yang bisa diakses.
        if ($user->hasUnrestrictedLocationAccess()) {
            return $query;
        }

        $branchIds = $user->accessibleBranchIds();
        $warehouseIds = $user->accessibleWarehouseIds();
        $query->where(function (Builder $inner) use ($branchIds, $warehouseIds) {
            $matched = false;
            if ($warehouseIds !== []) {
                $inner->orWhereIn('warehouse_id', $warehouseIds);
                $matched = true;
            }
            if ($branchIds !== []) {
                $inner->orWhereIn('branch_id', $branchIds);
                $matched = true;
            }
            if (! $matched) {
                $inner->whereRaw('1 = 0');
            }
        });

        return $query;
    }

    private function assertCanDecide(AuthorizationRequest $request, User $user): void
    {
        if ((int) $request->requested_by === (int) $user->id) {
            throw new InvalidArgumentException(__('Pembuat dokumen tidak dapat menyetujui atau menolak pengajuannya sendiri.'));
        }

        if (! $this->canAuthorizeModule($user, (string) $request->module)) {
            throw new InvalidArgumentException(__('Anda tidak berwenang memutuskan pengajuan ini. Pastikan user dipilih di konfigurasi otorisasi dan role punya akses Otorisasi untuk modul ini.'));
        }

        // Super Admin boleh semua lokasi; selain itu harus cocok dengan lokasi yang bisa diakses.
        if ($user->hasUnrestrictedLocationAccess()) {
            return;
        }
        if (! $this->locationMatches($user, $request)) {
            throw new InvalidArgumentException(__('Anda tidak berwenang memutuskan pengajuan ini.'));
        }
    }

    private function locationMatches(User $user, AuthorizationRequest $request): bool
    {
        if ($request->warehouse_id) {
            return $user->canAccessWarehouse((int) $request->warehouse_id);
        }
        if ($request->branch_id) {
            return $user->canAccessBranch((int) $request->branch_id);
        }

        return false;
    }

    private function latest(Model $model): ?AuthorizationRequest
    {
        if (! Schema::hasTable('authorization_requests')) {
            return null;
        }

        return AuthorizationRequest::query()
            ->where('reference_type', $model::class)
            ->where('reference_id', $model->getKey())
            ->latest('id')
            ->first();
    }

    private function isOpenDocument(Model $model): bool
    {
        return match (true) {
            $model instanceof Sale => $model->status === Sale::STATUS_OPEN,
            $model instanceof Purchase => $model->status === Purchase::STATUS_OPEN,
            $model instanceof Service => $model->status === Service::STATUS_OPEN,
            $model instanceof Rental => $model->status === Rental::STATUS_OPEN,
            $model instanceof UnitExchange => $model->status === UnitExchange::STATUS_OPEN,
            $model instanceof Distribution => $model->status === Distribution::STATUS_OPEN,
            default => false,
        };
    }

    private function isCommitted(Model $model): bool
    {
        return match (true) {
            $model instanceof Sale => $model->status === Sale::STATUS_RELEASED,
            $model instanceof Purchase => $model->status === Purchase::STATUS_ACTIVE,
            $model instanceof Service => $model->status === Service::STATUS_COMPLETED,
            $model instanceof Rental => $model->status === Rental::STATUS_RELEASED,
            $model instanceof UnitExchange => $model->status === UnitExchange::STATUS_RELEASED,
            $model instanceof Distribution => $model->status === Distribution::STATUS_ACTIVE,
            default => false,
        };
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    private function locationOf(Model $model): array
    {
        if ($model instanceof Distribution) {
            if ($model->from_location_type === 'warehouse') {
                return [null, (int) $model->from_location_id];
            }

            return [(int) $model->from_location_id, null];
        }

        return [
            $model->getAttribute('branch_id') ? (int) $model->getAttribute('branch_id') : null,
            $model->getAttribute('warehouse_id') ? (int) $model->getAttribute('warehouse_id') : null,
        ];
    }

    private function documentNumber(Model $model): string
    {
        foreach (['invoice_number', 'exchange_number'] as $field) {
            $value = trim((string) $model->getAttribute($field));
            if ($value !== '') {
                return $value;
            }
        }

        return '#'.$model->getKey();
    }

    private function cancelSubject(?Model $model, User $user, string $notes): void
    {
        if (! $model) {
            return;
        }

        match (true) {
            $model instanceof Sale => app(SaleService::class)->cancelSale($model, (int) $user->id, $notes),
            $model instanceof Purchase => app(PurchaseService::class)->cancelPurchase($model, (int) $user->id, $notes),
            $model instanceof Service => app(ServiceService::class)->cancel($model, (int) $user->id, $notes),
            $model instanceof Rental => app(RentalService::class)->cancel($model, (int) $user->id, $notes),
            $model instanceof Distribution => app(StockMutationService::class)->cancelOpenDistribution($model, (int) $user->id, $notes),
            $model instanceof UnitExchange => $this->cancelExchange($model),
            default => null,
        };
    }

    private function cancelExchange(UnitExchange $exchange): void
    {
        if ($exchange->status !== UnitExchange::STATUS_OPEN) {
            throw new InvalidArgumentException(__('Tukar unit ini tidak bisa ditolak.'));
        }

        app(StockMutationService::class)->unreserveExchangeUnits($exchange);
        $exchange->update(['status' => UnitExchange::STATUS_CANCEL]);
    }
}
