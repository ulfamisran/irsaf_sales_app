<?php

namespace App\Services;

use App\Models\AuthorizationRequest;
use App\Models\AuthorizationSetting;
use App\Models\Branch;
use App\Models\CashFlow;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DamagedGood;
use App\Models\Distribution;
use App\Models\Distributor;
use App\Models\ExpenseCategory;
use App\Models\IncomingGood;
use App\Models\IncomeCategory;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Purchase;
use App\Models\Rental;
use App\Models\Sale;
use App\Models\Service;
use App\Models\Stock;
use App\Models\StockMutation;
use App\Models\TransactionProof;
use App\Models\UnitExchange;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Models\Warehouse;
use App\Support\ClientIp;
use App\Support\ModuleAccessCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\ImplicitRouteBinding;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class UserActivityLogger
{
    /** @var array<string, string>|null */
    private ?array $moduleNames = null;

    /** @var list<Model> */
    private array $touched = [];

    private bool $watching = false;

    public function recordRequest(Request $request, Response $response): void
    {
        $user = $request->user();
        $action = $this->classify($request);
        if ($user === null || $action === null || ! $this->succeeded($request, $response)) {
            return;
        }
        [$referenceType, $referenceId, $subject, $model] = $this->subject($request, $response, $action);
        $action = $this->refineAction($request, $action, $model);
        $module = $this->moduleSlug($request);
        $moduleLabel = $model instanceof ProductUnit ? 'Nomor Serial' : $this->moduleLabel($module);
        $description = $this->description($request, $action, $moduleLabel, $subject, $model);

        $this->write([
            'user_id' => $user?->id,
            'action' => $action,
            'module' => $module,
            'description' => $description,
            'route_name' => $request->route()?->getName(),
            'http_method' => strtoupper($request->method()),
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'ip_address' => ClientIp::resolve($request),
            'user_agent' => $this->agent($request),
            'occurred_at' => now(),
        ]);
    }

    public function login(Request $request, User $user): void
    {
        $this->write([
            'user_id' => $user->id,
            'action' => UserActivityLog::LOGIN,
            'module' => 'auth',
            'description' => 'Masuk ke sistem',
            'route_name' => 'login',
            'http_method' => 'POST',
            'ip_address' => ClientIp::resolve($request),
            'user_agent' => $this->agent($request),
            'occurred_at' => now(),
        ]);
    }

    public function loginFailed(Request $request): void
    {
        $email = mb_substr(trim((string) $request->input('email')), 0, 120);
        $this->write([
            'user_id' => null,
            'action' => UserActivityLog::LOGIN_FAILED,
            'module' => 'auth',
            'description' => 'Gagal masuk'.($email !== '' ? ' · '.$email : ''),
            'route_name' => 'login',
            'http_method' => 'POST',
            'ip_address' => ClientIp::resolve($request),
            'user_agent' => $this->agent($request),
            'occurred_at' => now(),
        ]);
    }

    public function logout(Request $request, ?User $user): void
    {
        $this->write([
            'user_id' => $user?->id,
            'action' => UserActivityLog::LOGOUT,
            'module' => 'auth',
            'description' => 'Keluar dari sistem',
            'route_name' => 'logout',
            'http_method' => 'POST',
            'ip_address' => ClientIp::resolve($request),
            'user_agent' => $this->agent($request),
            'occurred_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function write(array $attributes): void
    {
        try {
            if (! Schema::hasTable('user_activity_logs')) {
                return;
            }

            UserActivityLog::query()->create($attributes);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function classify(Request $request): ?string
    {
        $method = strtoupper($request->method());
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return null;
        }

        $name = (string) $request->route()?->getName();
        $path = trim($request->path(), '/');
        if ($this->ignored($name, $path)) {
            return null;
        }

        if (str_contains($name, 'cancel') || str_contains($path, '/cancel')) {
            return UserActivityLog::CANCEL;
        }

        if ($method === 'DELETE' || str_contains($name, '.destroy') || str_contains($name, '.delete')) {
            return UserActivityLog::DELETE;
        }

        if (in_array($method, ['PUT', 'PATCH'], true) || str_contains($name, '.update')) {
            return UserActivityLog::UPDATE;
        }

        if ($method !== 'POST') {
            return null;
        }

        if (preg_match('/release|complete|picked-up|mark-|reactivate|accept|approve|reject|toggle|reset-password|resolve-deferred|materials|finalize/', $name) === 1) {
            return UserActivityLog::UPDATE;
        }

        return UserActivityLog::CREATE;
    }

    private function refineAction(Request $request, string $action, ?Model $model): string
    {
        $name = (string) $request->route()?->getName();
        if (! str_contains($name, 'toggle-active') || ! $model || ! array_key_exists('is_active', $model->getAttributes())) {
            return $action;
        }

        return $model->is_active ? UserActivityLog::ACTIVATE : UserActivityLog::DEACTIVATE;
    }

    private function ignored(string $name, string $path): bool
    {
        if (in_array($path, ['login', 'logout', 'confirm-password', 'forgot-password', 'reset-password', 'email/verification-notification'], true)) {
            return true;
        }

        $haystack = $name.' '.$path;

        return preg_match('/form-data|check-reusable|available-|search|activity-logs|push\.subscriptions|push-subscriptions/', $haystack) === 1;
    }

    private function succeeded(Request $request, Response $response): bool
    {
        if ($response->getStatusCode() >= 400) {
            return false;
        }

        if (! $response->isRedirection()) {
            return $response->isSuccessful();
        }

        $session = $request->hasSession() ? $request->session() : null;

        return $session === null || (! $session->has('errors') && ! $session->has('error'));
    }

    public function documentUrl(UserActivityLog $log): ?string
    {
        $id = $log->reference_id ? (int) $log->reference_id : null;
        if ($id && $log->reference_type === AuthorizationRequest::class) {
            $request = AuthorizationRequest::query()->find($id);

            return $request ? app(AuthorizationService::class)->documentUrl($request) : null;
        }
        if ($id && in_array($log->reference_type, [StockMutation::class, 'stock_mutation'], true)) {
            $distributionId = StockMutation::query()->whereKey($id)->value('distribution_id');
            if ($distributionId && Route::has('stock-mutations.show')) {
                return route('stock-mutations.show', $distributionId);
            }
        }
        $route = $this->routeForReference($log->reference_type, $id);
        if ($route === null) {
            $route = $this->routeForName((string) $log->route_name);
        }
        if ($route === null || $id === null || ! Route::has($route)) {
            return null;
        }

        try {
            return route($route, $id);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{0: ?string, 1: ?int, 2: string, 3: ?Model}
     */
    private function subject(Request $request, Response $response, string $action): array
    {
        $model = $this->modelFromRoute($request)
            ?? $this->modelFromRedirect($response)
            ?? $this->modelFromTouched((string) $request->route()?->getName());
        if ($model === null && $action === UserActivityLog::CREATE) {
            $model = $this->recentModel($request);
        }
        if ($model === null) {
            return [null, null, '', null];
        }

        return [$model->getMorphClass(), (int) $model->getKey(), $this->modelLabel($model), $model];
    }

    private function modelFromRoute(Request $request): ?Model
    {
        $route = $request->route();
        if ($route === null) {
            return null;
        }

        $preferred = $this->modelClassForRoute((string) $route->getName());
        $fallback = null;
        foreach ($route->parameters() as $key => $value) {
            $candidate = $value instanceof Model ? $value : $this->modelFromParameter((string) $key, $value);
            if (! $candidate instanceof Model) {
                continue;
            }
            if ($preferred !== null && $candidate instanceof $preferred) {
                return $candidate;
            }
            $fallback = $candidate;
        }

        return $fallback;
    }

    private function modelFromParameter(string $key, mixed $value): ?Model
    {
        if (! is_scalar($value) || ! ctype_digit((string) $value)) {
            return null;
        }

        $class = match ($key) {
            'authorizationRequest' => AuthorizationRequest::class,
            'sale' => Sale::class,
            'exchange' => UnitExchange::class,
            'service' => Service::class,
            'rental' => Rental::class,
            'purchase' => Purchase::class,
            'distribution' => Distribution::class,
            'product' => Product::class,
            'unit' => ProductUnit::class,
            'customer' => Customer::class,
            'category' => Category::class,
            'distributor' => Distributor::class,
            'paymentMethod', 'payment_method' => PaymentMethod::class,
            'warehouse' => Warehouse::class,
            'branch' => Branch::class,
            'user' => User::class,
            'cashFlow', 'cash_flow' => CashFlow::class,
            'incomingGood', 'incoming_good' => IncomingGood::class,
            'damagedGood', 'damaged_good' => DamagedGood::class,
            default => null,
        };
        if ($class === null) {
            return null;
        }

        return $class::query()->find((int) $value);
    }

    public function watch(): void
    {
        if ($this->watching) {
            return;
        }
        $this->watching = true;

        $remember = function ($event, $payload): void {
            try {
                $model = is_array($payload) ? ($payload[0] ?? null) : $payload;
                if (! $model instanceof Model || $model instanceof UserActivityLog) {
                    return;
                }
                if ($model instanceof TransactionProof || $this->isDocument($model)) {
                    $this->touched[] = $model;
                }
            } catch (Throwable) {
                return;
            }
        };

        foreach (['created', 'updated', 'deleted'] as $event) {
            Event::listen('eloquent.'.$event.': *', $remember);
        }
    }

    private function modelFromTouched(string $routeName): ?Model
    {
        $preferred = $this->modelClassForRoute($routeName);
        $match = null;
        foreach ($this->touched as $model) {
            if ($model instanceof TransactionProof) {
                $parent = $model->proofable;
                if ($parent instanceof Model && ($preferred === null || $parent instanceof $preferred)) {
                    $match = $parent;
                }
                continue;
            }
            if ($preferred === null || $model instanceof $preferred) {
                $match = $model;
            }
        }

        return $match;
    }

    private function isDocument(Model $model): bool
    {
        return $model instanceof Sale
            || $model instanceof UnitExchange
            || $model instanceof Service
            || $model instanceof Rental
            || $model instanceof Purchase
            || $model instanceof Distribution
            || $model instanceof StockMutation
            || $model instanceof Product
            || $model instanceof ProductUnit
            || $model instanceof Customer
            || $model instanceof Category
            || $model instanceof Distributor
            || $model instanceof PaymentMethod
            || $model instanceof ExpenseCategory
            || $model instanceof IncomeCategory
            || $model instanceof Warehouse
            || $model instanceof Branch
            || $model instanceof IncomingGood
            || $model instanceof DamagedGood
            || $model instanceof CashFlow
            || $model instanceof User
            || $model instanceof AuthorizationRequest
            || $model instanceof AuthorizationSetting;
    }

    private function modelFromRedirect(Response $response): ?Model
    {
        $location = $response->headers->get('Location');
        if (! is_string($location) || $location === '') {
            return null;
        }

        $path = parse_url($location, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            $path = $location;
        }

        try {
            $matched = Route::getRoutes()->match(Request::create($path, 'GET'));
        } catch (Throwable) {
            return null;
        }

        try {
            ImplicitRouteBinding::resolveForRoute(app(), $matched);
        } catch (Throwable) {
            // Nomor di URL tetap dipakai bila model belum terikat.
        }

        $preferred = $this->modelClassForRoute((string) $matched->getName());
        $fallback = null;
        foreach ($matched->parameters() as $key => $value) {
            $candidate = $value instanceof Model ? $value : $this->modelFromParameter((string) $key, $value);
            if (! $candidate instanceof Model) {
                continue;
            }
            if ($preferred !== null && $candidate instanceof $preferred) {
                return $candidate;
            }
            $fallback = $candidate;
        }

        return $fallback;
    }

    private function recentModel(Request $request): ?Model
    {
        $class = $this->modelClassForRoute((string) $request->route()?->getName());
        if ($class === null || ! is_subclass_of($class, Model::class)) {
            return null;
        }

        /** @var Model $sample */
        $sample = new $class;
        $table = $sample->getTable();
        if (! Schema::hasColumn($table, 'created_at')) {
            return null;
        }

        $query = $class::query()->where('created_at', '>=', now()->subSeconds(20))->latest('id');
        $userId = $request->user()?->id;
        if ($userId && Schema::hasColumn($table, 'user_id')) {
            $query->where('user_id', $userId);
        }

        return $query->first();
    }

    private function modelClassForRoute(string $name): ?string
    {
        foreach ([
            'authorizations.' => AuthorizationRequest::class,
            'authorization-settings.' => AuthorizationSetting::class,
            'sales.unit-exchanges.' => UnitExchange::class,
            'sales.' => Sale::class,
            'services.' => Service::class,
            'rentals.' => Rental::class,
            'purchases.' => Purchase::class,
            'stock-mutations.' => Distribution::class,
            'products.units.' => ProductUnit::class,
            'products.' => Product::class,
            'customers.' => Customer::class,
            'categories.' => Category::class,
            'distributors.' => Distributor::class,
            'payment-methods.' => PaymentMethod::class,
            'expense-categories.' => ExpenseCategory::class,
            'income-categories.' => IncomeCategory::class,
            'warehouses.' => Warehouse::class,
            'branches.' => Branch::class,
            'incoming-goods.' => IncomingGood::class,
            'damaged-goods.' => DamagedGood::class,
            'cash-flows.' => CashFlow::class,
            'mutasi-dana.' => CashFlow::class,
            'users.' => User::class,
        ] as $prefix => $class) {
            if (str_starts_with($name, $prefix)) {
                return $class;
            }
        }

        return null;
    }

    private function modelLabel(Model $model): string
    {
        $numbers = [];
        foreach ($model->getAttributes() as $field => $value) {
            if (! is_string($field) || ! str_ends_with($field, '_number') || ! is_scalar($value)) {
                continue;
            }
            $text = trim((string) $value);
            if ($text !== '') {
                $numbers[$field] = $text;
            }
        }
        foreach (['invoice_number', 'exchange_number', 'document_number', 'serial_number'] as $field) {
            if (isset($numbers[$field])) {
                return $numbers[$field];
            }
        }
        if ($numbers !== []) {
            return reset($numbers);
        }

        foreach (['sku', 'name', 'code'] as $field) {
            $value = trim((string) $model->getAttribute($field));
            if ($value !== '') {
                return $value;
            }
        }

        if ($model instanceof CashFlow) {
            $description = trim((string) $model->description);
            if ($description !== '') {
                return mb_substr($description, 0, 120);
            }
        }

        if ($model instanceof Product) {
            $label = trim((string) $model->sku.' '.trim((string) $model->brand.' '.(string) $model->series));
            if ($label !== '') {
                return $label;
            }
        }

        return '#'.$model->getKey();
    }

    private function routeForReference(?string $type, ?int $id): ?string
    {
        if ($type === null || $id === null) {
            return null;
        }

        $route = match ($type) {
            Sale::class => 'sales.show',
            UnitExchange::class => 'sales.unit-exchanges.show',
            Distribution::class => 'stock-mutations.show',
            Service::class => 'services.show',
            Rental::class => 'rentals.show',
            Purchase::class => 'purchases.show',
            Product::class => 'products.show',
            ProductUnit::class => 'stock-units.show',
            Customer::class => 'customers.edit',
            Branch::class => 'branches.edit',
            Warehouse::class => 'warehouses.edit',
            Category::class => 'categories.edit',
            Distributor::class => 'distributors.edit',
            PaymentMethod::class => 'payment-methods.edit',
            ExpenseCategory::class => 'expense-categories.edit',
            IncomeCategory::class => 'income-categories.edit',
            IncomingGood::class => 'incoming-goods.show',
            DamagedGood::class => 'damaged-goods.show',
            StockMutation::class => 'stock-mutations.invoice',
            User::class => 'users.edit',
            default => null,
        };

        if ($type === CashFlow::class) {
            $flow = CashFlow::query()->find($id);
            if (! $flow) {
                return null;
            }

            return $flow->type === CashFlow::TYPE_IN ? 'cash-flows.in.show' : 'cash-flows.out.show';
        }

        return $route;
    }

    private function routeForName(string $name): ?string
    {
        if ($name === '' || str_contains($name, '.units.')) {
            return str_contains($name, '.units.') ? 'stock-units.show' : null;
        }

        $candidate = preg_replace('/\.(store|update|destroy|edit|cancel|release)$/', '.show', $name);

        return is_string($candidate) && $candidate !== $name ? $candidate : null;
    }

    private function moduleSlug(Request $request): ?string
    {
        $name = (string) $request->route()?->getName();
        if ($name === '') {
            return null;
        }
        if (str_starts_with($name, 'sales.unit-exchanges')) {
            return 'unit-exchanges';
        }
        if (str_starts_with($name, 'authorizations')) {
            return 'authorizations';
        }
        if (str_starts_with($name, 'authorization-settings')) {
            return 'authorization-settings';
        }

        $prefixes = ModuleAccessCatalog::routePrefixes();
        uksort($prefixes, fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        foreach ($prefixes as $prefix => $slug) {
            $matched = str_ends_with($prefix, '.')
                ? str_starts_with($name, $prefix)
                : ($name === $prefix || str_starts_with($name, $prefix.'.'));
            if ($matched) {
                return $slug;
            }
        }

        foreach (['role-access' => 'role-access', 'verification-cutoff' => 'verification-cutoff', 'profile' => 'profile', 'password' => 'profile'] as $prefix => $slug) {
            if ($name === $prefix || str_starts_with($name, $prefix.'.')) {
                return $slug;
            }
        }

        return null;
    }

    private function moduleLabel(?string $slug): string
    {
        if ($slug === null || $slug === '') {
            return '';
        }

        return $this->moduleNames()[$slug] ?? $slug;
    }

    /**
     * @return array<string, string>
     */
    private function moduleNames(): array
    {
        if ($this->moduleNames === null) {
            $names = [];
            foreach (ModuleAccessCatalog::MODULES as $module) {
                $names[$module['slug']] = $module['name'];
            }
            $names['auth'] = 'Akun';
            $names['profile'] = 'Profil';
            $names['role-access'] = 'Akses Modul';
            $names['verification-cutoff'] = 'Cut-off Verifikasi';
            $names['unit-exchanges'] = 'Tukar Unit';
            $names['authorizations'] = 'Otorisasi';
            $names['authorization-settings'] = 'Konfigurasi Otorisasi';
            $this->moduleNames = $names;
        }

        return $this->moduleNames;
    }

    private function description(Request $request, string $action, string $moduleLabel, string $subject, ?Model $model): string
    {
        $route = (string) $request->route()?->getName();
        $userChange = $model instanceof User ? $this->userChangeDescription($request, $model) : null;
        if ($userChange !== null) {
            return $this->withDocument($userChange, $subject);
        }

        if (str_starts_with($route, 'authorizations.') || str_starts_with($route, 'authorization-settings')) {
            return $this->authorizationDescription($route, $subject, $model);
        }

        if (str_starts_with($route, 'stock-mutations')) {
            return $this->distributionDescription($request, $action, $subject, $model);
        }

        if (str_starts_with($route, 'sales.unit-exchanges')) {
            $verb = match (true) {
                str_contains($route, 'proofs.store') => 'Tambah Bukti Tukar Unit',
                str_contains($route, 'proofs.destroy') => 'Hapus Bukti Tukar Unit',
                $action === UserActivityLog::CREATE => 'Tambah Tukar Unit',
                $action === UserActivityLog::DELETE => 'Hapus Tukar Unit',
                $action === UserActivityLog::CANCEL => 'Batal Tukar Unit',
                $model instanceof UnitExchange && $model->status === UnitExchange::STATUS_RELEASED && $model->wasChanged('status') => 'Release Tukar Unit',
                default => 'Ubah Tukar Unit',
            };

            return $this->withDocument($verb, $subject);
        }

        if (str_contains($route, '.proofs.store')) {
            return $this->withDocument('Tambah Bukti Transaksi '.$moduleLabel, $subject);
        }
        if (str_contains($route, '.proofs.destroy')) {
            return $this->withDocument('Hapus Bukti Transaksi '.$moduleLabel, $subject);
        }
        if (str_contains($route, 'payment')) {
            return $this->withDocument('Tambah Pembayaran '.$moduleLabel, $subject);
        }
        if (str_contains($route, 'finalize-release')) {
            return $this->withDocument('Release '.$moduleLabel, $subject);
        }
        if (str_contains($route, 'mark-returned')) {
            return $this->withDocument('Catat Pengembalian '.$moduleLabel, $subject);
        }
        if (str_ends_with($route, '.release') || str_contains($route, '.release')) {
            // rentals.release = Aktifkan Penyewaan (efek stok/kas), bukan status Release final
            if (str_starts_with($route, 'rentals.')) {
                return $this->withDocument('Aktifkan Penyewaan', $subject);
            }

            return $this->withDocument('Release '.$moduleLabel, $subject);
        }

        $label = $moduleLabel !== '' && strtolower($moduleLabel) !== 'data'
            ? $moduleLabel
            : $this->fallbackModuleLabel($route);
        $verb = match ($action) {
            UserActivityLog::ACTIVATE => 'Aktifkan',
            UserActivityLog::DEACTIVATE => 'Nonaktifkan',
            default => $this->actionLabel($action),
        };

        return $this->withDocument(trim($verb.' '.$label), $subject);
    }

    private function fallbackModuleLabel(string $route): string
    {
        if ($route === '') {
            return 'dokumen';
        }

        $base = (string) strtok($route, '.');
        $map = [
            'rentals' => 'Penyewaan',
            'sales' => 'Penjualan',
            'services' => 'Service',
            'purchases' => 'Pembelian',
            'stock-mutations' => 'Distribusi',
            'authorizations' => 'Otorisasi',
            'customers' => 'Pelanggan',
            'products' => 'Produk',
            'users' => 'User',
            'cash-flows' => 'Kas',
            'mutasi-dana' => 'Mutasi Dana',
            'push' => 'Notifikasi Push',
        ];

        return $map[$base] ?? str_replace('-', ' ', $base);
    }

    private function authorizationDescription(string $route, string $subject, ?Model $model): string
    {
        if (str_starts_with($route, 'authorization-settings')) {
            return 'Ubah konfigurasi otorisasi';
        }

        $verb = match (true) {
            str_ends_with($route, '.submit') => 'Ajukan Otorisasi',
            str_ends_with($route, '.approve') => 'Setujui Otorisasi',
            str_ends_with($route, '.reject') => 'Tolak Otorisasi',
            default => 'Otorisasi',
        };
        $module = $model instanceof AuthorizationRequest
            ? app(AuthorizationService::class)->label($model->module)
            : '';
        $text = trim($verb.' '.$module);
        if ($model instanceof AuthorizationRequest && $model->status === AuthorizationRequest::STATUS_REJECTED && $model->notes) {
            $text .= ' · '.mb_substr((string) $model->notes, 0, 120);
        }

        return mb_substr($this->withDocument($text, $subject), 0, 500);
    }

    private function distributionDescription(Request $request, string $action, string $subject, ?Model $model): string
    {
        $distribution = $this->distributionForLog($request, $model);
        $route = (string) $request->route()?->getName();
        $verb = match (true) {
            str_contains($route, 'proofs.store') => 'Tambah Bukti Distribusi',
            str_contains($route, 'proofs.destroy') => 'Hapus Bukti Distribusi',
            str_contains($route, 'payment') => 'Tambah Pembayaran Distribusi',
            $action === UserActivityLog::CANCEL => 'Batal Distribusi',
            $action === UserActivityLog::CREATE => 'Tambah Distribusi',
            default => 'Ubah Distribusi',
        };

        if (! $distribution instanceof Distribution) {
            return $this->withDocument($verb, $subject);
        }

        $distribution->loadMissing(['details.product']);
        $from = $this->locationLabel($distribution->from_location_type, (int) $distribution->from_location_id);
        $to = $this->locationLabel($distribution->to_location_type, (int) $distribution->to_location_id);
        $parts = [
            $verb,
            $distribution->invoice_number ?: $subject,
            $from.' → '.$to,
            'Biaya Rp '.number_format((float) $distribution->total, 0, ',', '.'),
        ];

        if (str_contains($route, 'payment')) {
            $paid = round((float) $request->input('amount', 0), 2);
            if ($paid > 0) {
                $parts[] = 'Bayar Rp '.number_format($paid, 0, ',', '.');
            }
        }

        $distribution->loadMissing('stockMutations');
        $hppLabels = [];
        foreach ($distribution->stockMutations as $mutation) {
            $label = $mutation->hppChangeLabel();
            if ($label !== '') {
                $hppLabels[$label] = $label;
            }
        }
        $recordsHpp = $action === UserActivityLog::CREATE
            && ! str_contains($route, 'payment')
            && ! str_contains($route, 'proofs');
        if ($action === UserActivityLog::CANCEL && $hppLabels !== []) {
            $parts[] = 'HPP dikembalikan ke nilai asal';
        } elseif ($recordsHpp && $hppLabels !== []) {
            $parts[] = implode('; ', $hppLabels);
        }

        $units = $this->distributionUnits($distribution);
        if ($units !== '') {
            $parts[] = 'Unit '.$units;
        }

        if ($action === UserActivityLog::CANCEL) {
            $reason = trim((string) $request->input('cancel_reason', $distribution->cancel_reason));
            if ($reason !== '') {
                $parts[] = $reason;
            }
        }

        return mb_substr(implode(' · ', array_filter($parts, fn ($part) => $part !== '')), 0, 500);
    }

    private function distributionForLog(Request $request, ?Model $model): ?Distribution
    {
        $routed = $request->route('distribution');
        if ($routed instanceof Distribution) {
            return $routed;
        }
        if (is_scalar($routed) && ctype_digit((string) $routed)) {
            $found = Distribution::query()->find((int) $routed);
            if ($found instanceof Distribution) {
                return $found;
            }
        }
        if ($model instanceof Distribution) {
            return $model;
        }
        if ($model instanceof StockMutation) {
            return $model->distribution ?: Distribution::query()->find($model->distribution_id);
        }
        if ($model instanceof TransactionProof && $model->proofable instanceof Distribution) {
            return $model->proofable;
        }

        return null;
    }

    private function locationLabel(?string $type, int $id): string
    {
        if ($type === Stock::LOCATION_WAREHOUSE) {
            return 'Gudang '.((string) (Warehouse::query()->find($id)?->name ?: '#'.$id));
        }

        return 'Cabang '.((string) (Branch::query()->find($id)?->name ?: '#'.$id));
    }

    private function distributionUnits(Distribution $distribution): string
    {
        $serials = [];
        $quantity = 0;
        foreach ($distribution->details as $detail) {
            $quantity += (int) $detail->quantity;
            $lines = preg_split('/[\r\n,]+/', (string) $detail->serial_numbers) ?: [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $serials[] = $line;
                }
            }
        }
        if ($serials !== []) {
            return implode(', ', $serials);
        }

        return $quantity > 0 ? $quantity.' unit' : '';
    }

    private function withDocument(string $text, string $subject): string
    {
        $text = trim($text);
        if ($subject === '' || str_contains($text, $subject)) {
            return $text;
        }

        return $text.' · '.$subject;
    }

    private function userChangeDescription(Request $request, User $user): ?string
    {
        $name = $this->modelLabel($user);
        $route = (string) $request->route()?->getName();

        if ($route === 'users.reset-password') {
            return 'Reset Password User '.$name;
        }

        if ($route === 'users.toggle-active') {
            return ($user->is_active ? 'Aktifkan' : 'Nonaktifkan').' User '.$name;
        }

        if ($route !== 'users.update' || ! $user->wasChanged('is_active')) {
            return null;
        }

        $status = $user->is_active ? 'Aktifkan' : 'Nonaktifkan';
        $dataChanged = $user->wasChanged(['name', 'email', 'placement_type', 'branch_id', 'warehouse_id'])
            || $request->attributes->get('user_role_changed') === true;

        return $dataChanged
            ? 'Ubah User '.$name.' · '.$status
            : $status.' User '.$name;
    }

    private function actionLabel(string $action): string
    {
        return match ($action) {
            UserActivityLog::CREATE => 'Tambah',
            UserActivityLog::UPDATE => 'Ubah',
            UserActivityLog::DELETE => 'Hapus',
            UserActivityLog::CANCEL => 'Batal',
            UserActivityLog::ACTIVATE => 'Aktifkan',
            UserActivityLog::DEACTIVATE => 'Nonaktifkan',
            default => 'Aktivitas',
        };
    }

    private function agent(Request $request): ?string
    {
        $agent = trim((string) $request->userAgent());

        return $agent !== '' ? mb_substr($agent, 0, 500) : null;
    }
}
