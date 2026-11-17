<?php

namespace App\Services;

use App\Models\CashFlow;
use App\Models\ExpenseCategory;
use App\Models\Purchase;
use App\Models\Rental;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Service;
use App\Models\TransactionVerification;
use App\Models\User;
use App\Support\CatatRange;
use App\Support\VerificationCutoff;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TransactionVerificationService
{
    public function __construct(
        protected SaleService $saleService,
        protected PurchaseService $purchaseService,
        protected ServiceService $serviceService,
        protected RentalService $rentalService,
        protected CashFlowCancelService $cashFlowCancelService
    ) {}

    public function paginate(User $user, Request $request): LengthAwarePaginator
    {
        $type = $this->type($request->input('type', TransactionVerification::TYPE_SALE));

        return $this->pendingQuery($user, $request, $type)
            ->orderByDesc($this->table($type).'.id')
            ->paginate(20)
            ->withQueryString();
    }

    /**
     * Jumlah transaksi yang masih menunggu verifikasi, per jenis.
     *
     * @return array<string, int>
     */
    public function pendingCounts(User $user, Request $request): array
    {
        $counts = [];
        foreach ($this->allowedTypes() as $type) {
            $counts[$type] = $this->pendingQuery($user, $request, $type)->count();
        }

        return $counts;
    }

    public function pendingTotal(User $user): int
    {
        $key = 'verification.pending_total.'.$user->id;
        if (request()->attributes->has($key)) {
            return (int) request()->attributes->get($key);
        }

        $total = array_sum($this->pendingCounts($user, Request::create('/', 'GET')));
        request()->attributes->set($key, $total);

        return $total;
    }

    public function paginateHistory(User $user, Request $request): LengthAwarePaginator
    {
        return $this->historyQuery($user, $request)
            ->with('user')
            ->orderByDesc('verified_at')
            ->paginate(20)
            ->withQueryString();
    }

    /**
     * Jumlah riwayat verifikasi per jenis, beserta yang diterima dan ditolak.
     *
     * @return array<string, array{total: int, accepted: int, rejected: int}>
     */
    public function historyCounts(User $user, Request $request): array
    {
        $rows = $this->historyQuery($user, $request, false)
            ->select('verification_type')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN flag_accepted = 1 THEN 1 ELSE 0 END) as accepted')
            ->selectRaw('SUM(CASE WHEN flag_rejected = 1 THEN 1 ELSE 0 END) as rejected')
            ->groupBy('verification_type')
            ->get()
            ->keyBy('verification_type');

        $counts = [];
        foreach ($this->allowedTypes() as $type) {
            $row = $rows->get($type);
            $counts[$type] = [
                'total' => (int) ($row->total ?? 0),
                'accepted' => (int) ($row->accepted ?? 0),
                'rejected' => (int) ($row->rejected ?? 0),
            ];
        }

        return $counts;
    }

    public function acceptMany(User $user, string $type, array $ids): int
    {
        $type = $this->type($type, true);
        $saved = 0;
        foreach ($ids as $id) {
            $model = $this->findVisible($user, $type, (int) $id);
            if (! $model || $this->alreadyAccepted($type, (int) $model->getKey())) {
                continue;
            }
            $this->store($user, $type, $model, true, false, null);
            $saved++;
        }

        return $saved;
    }

    public function reject(User $user, string $type, int $id, string $notes): void
    {
        $type = $this->type($type, true);
        $notes = trim($notes);
        if ($notes === '') {
            throw new InvalidArgumentException(__('Keterangan wajib diisi saat menolak.'));
        }
        $model = $this->findVisible($user, $type, $id);
        if (! $model) {
            throw new InvalidArgumentException(__('Transaksi tidak ditemukan.'));
        }
        if ($this->alreadyAccepted($type, $id)) {
            throw new InvalidArgumentException(__('Transaksi ini sudah diterima.'));
        }

        $reason = 'REJECT VERIFIKASI : '.$notes;
        DB::transaction(function () use ($user, $type, $model, $reason, $notes) {
            $this->cancel($user, $type, $model, $reason);
            $this->store($user, $type, $model, false, true, $notes);
        });
    }

    private function cancel(User $user, string $type, Model $model, string $reason): void
    {
        match ($type) {
            TransactionVerification::TYPE_SALE => $this->saleService->cancelSale($model, (int) $user->id, $reason),
            TransactionVerification::TYPE_PURCHASE => $this->purchaseService->cancelPurchase($model, (int) $user->id, $reason),
            TransactionVerification::TYPE_SERVICE => $this->serviceService->cancel($model, (int) $user->id, $reason),
            TransactionVerification::TYPE_RENTAL => $this->rentalService->cancel($model, (int) $user->id, $reason),
            TransactionVerification::TYPE_CASH_IN, TransactionVerification::TYPE_CASH_OUT, TransactionVerification::TYPE_CASH_OUT_EXTERNAL => $this->cashFlowCancelService->cancel($model, (int) $user->id, $reason),
            default => throw new InvalidArgumentException(__('Jenis transaksi tidak dikenali.')),
        };
    }

    private function store(User $user, string $type, Model $model, bool $accepted, bool $rejected, ?string $notes): void
    {
        TransactionVerification::create([
            'verification_type' => $type,
            'document_number' => $this->documentNumber($type, $model),
            'document_transaction_date' => $this->transactionDate($type, $model),
            'document_recorded_at' => $model->created_at,
            'verified_at' => now(),
            'user_id' => $user->id,
            'flag_accepted' => $accepted,
            'flag_rejected' => $rejected,
            'notes' => $notes,
            'reference_type' => $type,
            'reference_id' => $model->getKey(),
            'branch_id' => $model->branch_id ?? null,
            'warehouse_id' => $model->warehouse_id ?? null,
            'client_ip' => $model->client_ip ?? null,
        ]);
    }

    private function pendingQuery(User $user, Request $request, string $type): Builder
    {
        $query = $this->baseQuery($type);
        $this->scopeLocation($query, $user, $request, $type);
        $this->applyDates($query, $request, $type);
        $this->excludeOnOrBeforeCutoff($query, $this->dateColumns($type)[0]);
        $accepted = TransactionVerification::query()
            ->where('verification_type', $type)
            ->where('flag_accepted', true)
            ->pluck('reference_id');
        if ($accepted->isNotEmpty()) {
            $query->whereNotIn($this->table($type).'.id', $accepted->all());
        }

        return $query;
    }

    private function historyQuery(User $user, Request $request, bool $limitType = true): Builder
    {
        $query = TransactionVerification::query();
        if (! $user->isSuperAdminOrAdminPusat()) {
            if ($user->hasRole(Role::KEPALA_CABANG)) {
                $query->where('branch_id', $user->branch_id ?: 0);
            } elseif ($user->hasRole(Role::KEPALA_GUDANG)) {
                $query->where('warehouse_id', $user->warehouse_id ?: 0);
            } else {
                $query->whereRaw('1 = 0');
            }
        } else {
            $this->applyChosenLocation($query, $request, 'transaction_verifications');
        }

        if ($limitType) {
            $type = (string) $request->input('type', '');
            if (in_array($type, $this->allowedTypes(), true)) {
                $query->where('verification_type', $type);
            }
        }
        if ($request->input('date_basis') === 'transaksi') {
            if ($request->filled('date_from')) {
                $query->whereDate('document_transaction_date', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->whereDate('document_transaction_date', '<=', $request->date_to);
            }
        } else {
            CatatRange::applyBetween(
                $query,
                $request->filled('date_from') ? (string) $request->date_from : null,
                $request->filled('date_to') ? (string) $request->date_to : null,
                'transaction_verifications.document_recorded_at'
            );
        }

        $this->excludeOnOrBeforeCutoff($query, 'transaction_verifications.document_transaction_date');

        return $query;
    }

    private function cashOutQuery(bool $external): Builder
    {
        $categoryId = $this->externalExpenseCategoryId();
        $query = CashFlow::query()
            ->with(['branch', 'warehouse', 'user'])
            ->where('type', CashFlow::TYPE_OUT)
            ->whereNull('cancelled_at');

        if ($external) {
            if ($categoryId === null) {
                return $query->whereRaw('1 = 0');
            }

            return $query->where('expense_category_id', $categoryId);
        }

        $query->where(function ($inner) {
            $inner->whereNull('reference_type')->orWhere('reference_type', CashFlow::REFERENCE_OTHER);
        });
        if ($categoryId !== null) {
            $query->where(function ($inner) use ($categoryId) {
                $inner->whereNull('expense_category_id')->orWhere('expense_category_id', '!=', $categoryId);
            });
        }

        return $query;
    }

    private function externalExpenseCategoryId(): ?int
    {
        $id = ExpenseCategory::query()
            ->where(function ($query) {
                $query->where('code', 'PENGELUARAN_EKSTERNAL')
                    ->orWhere('name', 'Pengeluaran Eksternal');
            })
            ->value('id');

        return $id ? (int) $id : null;
    }

    private function baseQuery(string $type): Builder
    {
        return match ($type) {
            TransactionVerification::TYPE_SALE => Sale::query()->with(['branch', 'warehouse', 'user'])->whereIn('status', [Sale::STATUS_OPEN, Sale::STATUS_RELEASED]),
            TransactionVerification::TYPE_PURCHASE => Purchase::query()->with(['branch', 'warehouse', 'user'])->where('status', Purchase::STATUS_ACTIVE),
            TransactionVerification::TYPE_SERVICE => Service::query()->with(['branch', 'warehouse', 'user'])->whereIn('status', [Service::STATUS_OPEN, Service::STATUS_COMPLETED]),
            TransactionVerification::TYPE_RENTAL => Rental::query()->with(['branch', 'warehouse', 'user'])->whereIn('status', [Rental::STATUS_OPEN, Rental::STATUS_RELEASED]),
            TransactionVerification::TYPE_CASH_IN => CashFlow::query()->with(['branch', 'warehouse', 'user'])->where('type', CashFlow::TYPE_IN)->whereNull('cancelled_at')->where(function ($q) {
                $q->whereNull('reference_type')->orWhere('reference_type', CashFlow::REFERENCE_OTHER);
            }),
            TransactionVerification::TYPE_CASH_OUT => $this->cashOutQuery(false),
            TransactionVerification::TYPE_CASH_OUT_EXTERNAL => $this->cashOutQuery(true),
            default => throw new InvalidArgumentException(__('Jenis transaksi tidak dikenali.')),
        };
    }

    private function findVisible(User $user, string $type, int $id): ?Model
    {
        $query = $this->baseQuery($type)->where($this->table($type).'.id', $id);
        $this->scopeLocation($query, $user, request(), $type);
        $this->excludeOnOrBeforeCutoff($query, $this->dateColumns($type)[0]);

        return $query->first();
    }

    private function scopeLocation(Builder $query, User $user, Request $request, string $type): void
    {
        $table = $this->table($type);
        if ($user->isSuperAdminOrAdminPusat()) {
            $this->applyChosenLocation($query, $request, $table);

            return;
        }
        if ($user->hasRole(Role::KEPALA_CABANG) && $user->branch_id) {
            $query->where($table.'.branch_id', $user->branch_id);

            return;
        }
        if ($user->hasRole(Role::KEPALA_GUDANG) && $user->warehouse_id) {
            $query->where($table.'.warehouse_id', $user->warehouse_id);

            return;
        }
        $query->whereRaw('1 = 0');
    }

    private function excludeOnOrBeforeCutoff(Builder $query, string $column): void
    {
        $cutoff = VerificationCutoff::date();
        if ($cutoff === null || preg_match('/^[A-Za-z0-9_.]+$/', $column) !== 1) {
            return;
        }

        $query->where(function (Builder $inner) use ($column, $cutoff) {
            $inner->whereNull($column)->orWhereDate($column, '>', $cutoff);
        });
    }

    private function applyChosenLocation(Builder $query, Request $request, string $table): void
    {
        if ($request->input('location_type') === 'branch' && $request->filled('branch_id')) {
            $query->where($table.'.branch_id', (int) $request->input('branch_id'));
        }
        if ($request->input('location_type') === 'warehouse' && $request->filled('warehouse_id')) {
            $query->where($table.'.warehouse_id', (int) $request->input('warehouse_id'));
        }
    }

    /**
     * @return list<string>
     */
    private function allowedTypes(): array
    {
        return [
            TransactionVerification::TYPE_SALE,
            TransactionVerification::TYPE_PURCHASE,
            TransactionVerification::TYPE_SERVICE,
            TransactionVerification::TYPE_RENTAL,
            TransactionVerification::TYPE_CASH_IN,
            TransactionVerification::TYPE_CASH_OUT,
            TransactionVerification::TYPE_CASH_OUT_EXTERNAL,
        ];
    }

    private function applyDates(Builder $query, Request $request, string $type): void
    {
        [$transactionColumn, $recordedColumn] = $this->dateColumns($type);
        if ($request->input('date_basis') === 'transaksi') {
            if ($request->filled('date_from')) {
                $query->whereDate($transactionColumn, '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->whereDate($transactionColumn, '<=', $request->date_to);
            }

            return;
        }
        CatatRange::applyBetween(
            $query,
            $request->filled('date_from') ? (string) $request->date_from : null,
            $request->filled('date_to') ? (string) $request->date_to : null,
            $recordedColumn
        );
    }

    private function alreadyAccepted(string $type, int $id): bool
    {
        return TransactionVerification::query()
            ->where('verification_type', $type)
            ->where('reference_id', $id)
            ->where('flag_accepted', true)
            ->exists();
    }

    private function type(string $type, bool $strict = false): string
    {
        if (! in_array($type, $this->allowedTypes(), true)) {
            if ($strict) {
                throw new InvalidArgumentException(__('Jenis transaksi tidak dikenali.'));
            }

            return TransactionVerification::TYPE_SALE;
        }

        return $type;
    }

    private function table(string $type): string
    {
        return match ($type) {
            TransactionVerification::TYPE_SALE => 'sales',
            TransactionVerification::TYPE_PURCHASE => 'purchases',
            TransactionVerification::TYPE_SERVICE => 'services',
            TransactionVerification::TYPE_RENTAL => 'rentals',
            default => 'cash_flows',
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function dateColumns(string $type): array
    {
        $table = $this->table($type);
        $transaction = match ($type) {
            TransactionVerification::TYPE_SALE => 'sale_date',
            TransactionVerification::TYPE_PURCHASE => 'purchase_date',
            TransactionVerification::TYPE_SERVICE => 'entry_date',
            TransactionVerification::TYPE_RENTAL => 'pickup_date',
            default => 'transaction_date',
        };

        return [$table.'.'.$transaction, $table.'.created_at'];
    }

    private function documentNumber(string $type, Model $model): string
    {
        if (in_array($type, [TransactionVerification::TYPE_CASH_IN, TransactionVerification::TYPE_CASH_OUT, TransactionVerification::TYPE_CASH_OUT_EXTERNAL], true)) {
            return 'KAS-'.$model->getKey();
        }

        return (string) ($model->invoice_number ?: $type.'-'.$model->getKey());
    }

    private function transactionDate(string $type, Model $model): mixed
    {
        return match ($type) {
            TransactionVerification::TYPE_SALE => $model->sale_date,
            TransactionVerification::TYPE_PURCHASE => $model->purchase_date,
            TransactionVerification::TYPE_SERVICE => $model->entry_date,
            TransactionVerification::TYPE_RENTAL => $model->pickup_date,
            default => $model->transaction_date,
        };
    }
}
