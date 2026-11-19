<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Distribution;
use App\Models\Purchase;
use App\Models\Rental;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Service;
use App\Models\Stock;
use App\Models\UnitExchange;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\CatatRange;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PrintReportController extends Controller
{
    public function index(Request $request): View
    {
        $context = $this->context($request);
        $rows = [];
        foreach ($this->typeDefinitions() as $type) {
            if (! in_array($type['key'], $context['allowed'], true)) {
                continue;
            }
            $rows[] = [
                'key' => $type['key'],
                'label' => $type['label'],
                'count' => $this->filteredQuery($type, $context)->count(),
            ];
        }

        return view('reports.index', [
            'rows' => $rows,
            'filters' => $context['filters'],
            'datesDefaulted' => $context['datesDefaulted'],
            'canFilterLocation' => $context['canFilterLocation'],
            'locationLabel' => $context['locationLabel'],
            'locations' => $context['locations'],
            'users' => $context['users'],
            'rangeLabel' => $this->rangeLabel($context['filters']['date_from'], $context['filters']['date_to']),
        ]);
    }

    public function show(Request $request, string $type): View
    {
        $context = $this->context($request);
        $definition = collect($this->typeDefinitions())->firstWhere('key', $type);
        if (! $definition || ! in_array($type, $context['allowed'], true)) {
            abort(404);
        }

        $documents = $this->filteredQuery($definition, $context)
            ->get()
            ->map(fn ($model) => $definition['document']($model))
            ->all();

        return view('reports.print-bundle', [
            'groups' => [[
                'label' => $definition['label'],
                'documents' => $documents,
            ]],
            'backUrl' => route('print-reports.index', array_filter($context['filters'], fn ($value) => $value !== null && $value !== '')),
        ]);
    }

    /**
     * @return list<string>
     */
    private function allowedTypes(User $user): array
    {
        $types = [];
        if ($user->canModule('purchases', 'select')) {
            $types[] = 'purchases';
        }
        if ($user->canModule('sales', 'select')) {
            $types[] = 'sales';
            $types[] = 'unit-exchanges';
        }
        if ($user->canModule('services', 'select')) {
            $types[] = 'services';
        }
        if ($user->canModule('rentals', 'select')) {
            $types[] = 'rentals';
        }
        if ($user->canModule('stock-mutations', 'select')) {
            $types[] = 'distributions';
        }

        return $types;
    }

    /**
     * @return list<array{key: string, label: string, date_column: string, branch_excludes_warehouse: bool, query: \Closure, document: \Closure}>
     */
    private function typeDefinitions(): array
    {
        return [
            [
                'key' => 'purchases',
                'label' => __('Pembelian'),
                'date_column' => 'purchase_date',
                'branch_excludes_warehouse' => false,
                'query' => fn () => Purchase::query()
                    ->with(['distributor', 'warehouse', 'branch', 'user', 'details.product.category', 'details.units', 'payments', 'proofs'])
                    ->where('status', '!=', Purchase::STATUS_CANCELLED)
                    ->orderBy('purchase_date')
                    ->orderBy('id'),
                'document' => fn (Purchase $purchase) => $this->document(
                    $purchase->invoice_number,
                    $purchase->proofs,
                    'purchases.proofs.show',
                    $purchase,
                    null,
                    []
                ),
            ],
            [
                'key' => 'sales',
                'label' => __('Penjualan'),
                'date_column' => 'sale_date',
                'branch_excludes_warehouse' => true,
                'query' => fn () => Sale::query()
                    ->with([...Sale::invoiceEagerLoads(), 'proofs'])
                    ->where('status', '!=', Sale::STATUS_CANCEL)
                    ->orderBy('sale_date')
                    ->orderBy('id'),
                'document' => fn (Sale $sale) => $this->document(
                    $sale->invoice_number,
                    $sale->proofs,
                    'sales.proofs.show',
                    $sale,
                    'sales.invoice',
                    ['sale' => $sale, 'embedded' => true, 'forReport' => true]
                ),
            ],
            [
                'key' => 'services',
                'label' => __('Service'),
                'date_column' => 'entry_date',
                'branch_excludes_warehouse' => false,
                'query' => fn () => Service::query()
                    ->with([
                        'branch', 'warehouse', 'user', 'customer', 'payments.paymentMethod',
                        'serviceMaterials', 'sparePartServicePurchases.details.product',
                        'sparePartServicePurchases.distributor', 'proofs',
                    ])
                    ->where('status', '!=', Service::STATUS_CANCEL)
                    ->orderBy('entry_date')
                    ->orderBy('id'),
                'document' => fn (Service $service) => $this->document(
                    $service->invoice_number,
                    $service->proofs,
                    'services.proofs.show',
                    $service,
                    'services.invoice',
                    ['service' => $service, 'embedded' => true, 'forReport' => true]
                ),
            ],
            [
                'key' => 'rentals',
                'label' => __('Sewa'),
                'date_column' => 'pickup_date',
                'branch_excludes_warehouse' => false,
                'query' => fn () => Rental::query()
                    ->with(['branch', 'warehouse', 'user', 'customer', 'items.product', 'payments.paymentMethod', 'proofs'])
                    ->where('status', '!=', Rental::STATUS_CANCEL)
                    ->orderBy('pickup_date')
                    ->orderBy('id'),
                'document' => fn (Rental $rental) => $this->document(
                    $rental->invoice_number,
                    $rental->proofs,
                    'rentals.proofs.show',
                    $rental,
                    'rentals.invoice',
                    ['rental' => $rental, 'embedded' => true, 'forReport' => true]
                ),
            ],
            [
                'key' => 'unit-exchanges',
                'label' => __('Tukar Unit'),
                'date_column' => 'exchange_date',
                'branch_excludes_warehouse' => true,
                'query' => fn () => UnitExchange::query()
                    ->with([
                        'sale' => fn ($query) => $query->with(Sale::invoiceEagerLoads()),
                        'user', 'branch', 'warehouse', 'proofs',
                    ])
                    ->orderBy('exchange_date')
                    ->orderBy('id'),
                'document' => function (UnitExchange $exchange) {
                    $sale = $exchange->sale;

                    return $this->document(
                        $exchange->exchange_number,
                        $exchange->proofs,
                        'sales.unit-exchanges.proofs.show',
                        $exchange,
                        $sale ? 'sales.invoice' : null,
                        $sale ? ['sale' => $sale, 'embedded' => true, 'forReport' => true] : []
                    );
                },
            ],
            [
                'key' => 'distributions',
                'label' => __('Distribusi'),
                'date_column' => 'distribution_date',
                'branch_excludes_warehouse' => false,
                'place' => 'distribution',
                'query' => fn () => Distribution::query()
                    ->with(['user', 'details.product', 'payments.paymentMethod', 'proofs'])
                    ->where('status', '!=', Distribution::STATUS_CANCELLED)
                    ->orderBy('distribution_date')
                    ->orderBy('id'),
                'document' => fn (Distribution $distribution) => $this->document(
                    $distribution->invoice_number,
                    $distribution->proofs,
                    'stock-mutations.proofs.show',
                    $distribution,
                    null,
                    []
                ),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function document(string $number, $proofs, string $proofRoute, object $owner, ?string $invoiceView, array $invoiceData = []): array
    {
        return [
            'number' => $number,
            'proofs' => $proofs,
            'proofRoute' => $proofRoute,
            'owner' => $owner,
            'invoiceView' => $invoiceView,
            'invoiceData' => $invoiceData,
        ];
    }

    /**
     * @param  array{key: string, date_column: string, branch_excludes_warehouse: bool, query: \Closure}  $type
     * @param  array<string, mixed>  $context
     */
    private function filteredQuery(array $type, array $context): Builder
    {
        $query = $type['query']();
        if (($type['place'] ?? null) === 'distribution') {
            $this->scopeDistribution($query, $context['user'], $context['filters']['location']);
        } else {
            $this->scopePlace($query, $context['user'], $context['filters']['location'], $type['branch_excludes_warehouse']);
        }
        $this->applyTransactionDates($query, $type['date_column'], $context['filters']['date_from'], $context['filters']['date_to']);
        CatatRange::applyBetween($query, $context['filters']['catat_from'], $context['filters']['catat_to'], 'created_at');
        if ($context['filters']['user_id']) {
            $query->where('user_id', $context['filters']['user_id']);
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function context(Request $request): array
    {
        $user = $request->user();
        $allowed = $this->allowedTypes($user);
        if ($allowed === []) {
            abort(403, __('Unauthorized.'));
        }

        $canFilterLocation = $user->isSuperAdminOrAdminPusat();
        $location = $this->lockedLocation($user);
        $locationKey = $canFilterLocation ? (string) $request->input('location', '') : '';
        if ($canFilterLocation && ! $this->locationParts($locationKey)) {
            $locationKey = '';
        }

        $userId = ctype_digit((string) $request->input('user_id')) ? (int) $request->input('user_id') : null;
        [$dateFrom, $dateTo, $catatFrom, $catatTo, $datesDefaulted] = $this->ranges($request);

        return [
            'user' => $user,
            'allowed' => $allowed,
            'canFilterLocation' => $canFilterLocation,
            'locationLabel' => $location['label'],
            'locations' => $canFilterLocation ? $this->locationOptions() : [],
            'users' => $this->userOptions($user, $location, $locationKey),
            'datesDefaulted' => $datesDefaulted,
            'filters' => [
                'location' => $locationKey,
                'user_id' => $userId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'catat_from' => $catatFrom,
                'catat_to' => $catatTo,
            ],
        ];
    }

    private function rangeLabel(?string $from, ?string $to): string
    {
        $fromText = $from ? Carbon::parse($from)->format('d/m/Y') : null;
        $toText = $to ? Carbon::parse($to)->format('d/m/Y') : null;
        if ($fromText && $toText) {
            return $fromText.' - '.$toText;
        }
        if ($fromText) {
            return $fromText.' - '.__('seterusnya');
        }
        if ($toText) {
            return __('sampai').' '.$toText;
        }

        return __('Semua tanggal');
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: ?string, 3: ?string, 4: bool}
     */
    private function ranges(Request $request): array
    {
        $dateFrom = $this->dateOrNull($request->input('date_from'));
        $dateTo = $this->dateOrNull($request->input('date_to'));
        $catatFrom = $this->dateOrNull($request->input('catat_from'));
        $catatTo = $this->dateOrNull($request->input('catat_to'));
        $datesDefaulted = $dateFrom === null && $dateTo === null && $catatFrom === null && $catatTo === null;
        if ($datesDefaulted) {
            $today = Carbon::now(CatatRange::TZ);
            $dateFrom = $today->copy()->startOfMonth()->toDateString();
            $dateTo = $today->toDateString();
        }

        return [$dateFrom, $dateTo, $catatFrom, $catatTo, $datesDefaulted];
    }

    private function applyTransactionDates(Builder $query, string $column, ?string $from, ?string $to): void
    {
        if ($from) {
            $query->whereDate($column, '>=', $from);
        }
        if ($to) {
            $query->whereDate($column, '<=', $to);
        }
    }

    private function scopePlace(Builder $query, User $user, string $locationKey, bool $branchExcludesWarehouse): void
    {
        if (! $user->isSuperAdminOrAdminPusat()) {
            if ($user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR, Role::KEPALA_CABANG]) && $user->branch_id) {
                $query->where('branch_id', $user->branch_id);
                if ($branchExcludesWarehouse) {
                    $query->whereNull('warehouse_id');
                }

                return;
            }
            if ($user->hasAnyRole([Role::ADMIN_GUDANG, Role::KEPALA_GUDANG]) && $user->warehouse_id) {
                $query->where('warehouse_id', $user->warehouse_id);

                return;
            }
            abort(403, __('User branch or warehouse not set.'));
        }

        $parts = $this->locationParts($locationKey);
        if (! $parts) {
            return;
        }
        if ($parts[0] === 'warehouse') {
            $query->where('warehouse_id', $parts[1]);

            return;
        }
        $query->where('branch_id', $parts[1]);
        if ($branchExcludesWarehouse) {
            $query->whereNull('warehouse_id');
        }
    }

    private function scopeDistribution(Builder $query, User $user, string $locationKey): void
    {
        $apply = function (Builder $query, string $type, int $id): void {
            $query->where(function (Builder $places) use ($type, $id) {
                $places->where(function (Builder $from) use ($type, $id) {
                    $from->where('from_location_type', $type)->where('from_location_id', $id);
                })->orWhere(function (Builder $to) use ($type, $id) {
                    $to->where('to_location_type', $type)->where('to_location_id', $id);
                });
            });
        };

        if (! $user->isSuperAdminOrAdminPusat()) {
            if ($user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR, Role::KEPALA_CABANG]) && $user->branch_id) {
                $apply($query, Stock::LOCATION_BRANCH, (int) $user->branch_id);

                return;
            }
            if ($user->hasAnyRole([Role::ADMIN_GUDANG, Role::KEPALA_GUDANG]) && $user->warehouse_id) {
                $apply($query, Stock::LOCATION_WAREHOUSE, (int) $user->warehouse_id);

                return;
            }
            abort(403, __('User branch or warehouse not set.'));
        }

        $parts = $this->locationParts($locationKey);
        if (! $parts) {
            return;
        }
        $apply($query, $parts[0] === 'warehouse' ? Stock::LOCATION_WAREHOUSE : Stock::LOCATION_BRANCH, $parts[1]);
    }

    /**
     * @return array{type: ?string, id: ?int, label: ?string}
     */
    private function lockedLocation(User $user): array
    {
        if ($user->isSuperAdminOrAdminPusat()) {
            return ['type' => null, 'id' => null, 'label' => null];
        }
        if ($user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR, Role::KEPALA_CABANG]) && $user->branch_id) {
            $branch = Branch::find($user->branch_id);

            return ['type' => 'branch', 'id' => (int) $user->branch_id, 'label' => __('Cabang').': '.($branch?->name ?? '-')];
        }
        if ($user->hasAnyRole([Role::ADMIN_GUDANG, Role::KEPALA_GUDANG]) && $user->warehouse_id) {
            $warehouse = Warehouse::find($user->warehouse_id);

            return ['type' => 'warehouse', 'id' => (int) $user->warehouse_id, 'label' => __('Gudang').': '.($warehouse?->name ?? '-')];
        }

        return ['type' => null, 'id' => null, 'label' => null];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function locationOptions(): array
    {
        $options = [];
        foreach (Branch::query()->orderBy('name')->get(['id', 'name']) as $branch) {
            $options[] = ['value' => 'branch-'.$branch->id, 'label' => __('Cabang').': '.$branch->name];
        }
        foreach (Warehouse::query()->orderBy('name')->get(['id', 'name']) as $warehouse) {
            $options[] = ['value' => 'warehouse-'.$warehouse->id, 'label' => __('Gudang').': '.$warehouse->name];
        }

        return $options;
    }

    /**
     * @param  array{type: ?string, id: ?int, label: ?string}  $locked
     */
    private function userOptions(User $user, array $locked, string $locationKey)
    {
        $query = User::query()->orderBy('name');
        if ($locked['type'] === 'branch') {
            $query->where('branch_id', $locked['id']);
        } elseif ($locked['type'] === 'warehouse') {
            $query->where('warehouse_id', $locked['id']);
        } else {
            $parts = $this->locationParts($locationKey);
            if ($parts && $parts[0] === 'branch') {
                $query->where('branch_id', $parts[1]);
            } elseif ($parts && $parts[0] === 'warehouse') {
                $query->where('warehouse_id', $parts[1]);
            }
        }

        return $query->get(['id', 'name']);
    }

    /**
     * @return array{0: string, 1: int}|null
     */
    private function locationParts(string $locationKey): ?array
    {
        if (preg_match('/^(branch|warehouse)-(\d+)$/', $locationKey, $match) !== 1) {
            return null;
        }

        return [$match[1], (int) $match[2]];
    }

    private function dateOrNull(mixed $value): ?string
    {
        $value = is_string($value) ? $value : '';

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }
}
