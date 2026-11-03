<?php

namespace App\Services;

use App\Models\User;
use App\Support\ModuleAccessCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ModuleAccessService
{
    /** @var array<int, bool> */
    private array $fullAccess = [];

    /** @var array<int, array<string, true>> */
    private array $grantsByUser = [];

    private ?bool $tablesReady = null;

    /** @var array<string, string>|null */
    private ?array $prefixes = null;

    public function flush(): void
    {
        $this->fullAccess = [];
        $this->grantsByUser = [];
        $this->tablesReady = null;
    }

    public function tablesReady(): bool
    {
        if ($this->tablesReady === null) {
            $this->tablesReady = Schema::hasTable('app_modules') && Schema::hasTable('role_module_permissions');
        }

        return $this->tablesReady;
    }

    public function allows(User $user, string $slug, string $action): bool
    {
        if (! array_key_exists($action, ModuleAccessCatalog::ACTIONS)) {
            return false;
        }

        if ($this->isFullAccess($user)) {
            return true;
        }

        $grants = $this->grantsFor($user);

        return isset($grants[$slug.'|'.$action]);
    }

    /**
     * @return array{module: string, action: string}|null
     */
    public function resolve(Request $request): ?array
    {
        $name = $request->route()?->getName();
        if (! is_string($name) || $name === '' || str_starts_with($name, 'role-access.')) {
            return null;
        }

        $module = null;
        foreach ($this->prefixes() as $prefix => $slug) {
            if ($this->prefixMatches($name, $prefix)) {
                $module = $slug;
                break;
            }
        }

        if ($module === null) {
            return null;
        }

        return [
            'module' => $module,
            'action' => $this->actionFor($name, $request->method()),
        ];
    }

    private function isFullAccess(User $user): bool
    {
        if (! array_key_exists($user->id, $this->fullAccess)) {
            $this->fullAccess[$user->id] = $user->isStrictSuperAdmin();
        }

        return $this->fullAccess[$user->id];
    }

    /**
     * @return array<string, true>
     */
    private function grantsFor(User $user): array
    {
        if (array_key_exists($user->id, $this->grantsByUser)) {
            return $this->grantsByUser[$user->id];
        }

        $set = [];
        if ($this->tablesReady()) {
            $roleIds = $user->roles()->pluck('roles.id');
            if ($roleIds->isNotEmpty()) {
                $rows = DB::table('role_module_permissions as p')
                    ->join('app_modules as m', 'm.id', '=', 'p.module_id')
                    ->whereIn('p.role_id', $roleIds)
                    ->get(['m.slug', 'p.action']);
                foreach ($rows as $row) {
                    $set[$row->slug.'|'.$row->action] = true;
                }
            }
        } else {
            $names = $user->roles()->pluck('name');
            foreach ($names as $name) {
                foreach (ModuleAccessCatalog::grantsForRole((string) $name) as $slug => $actions) {
                    foreach ($actions as $action) {
                        $set[$slug.'|'.$action] = true;
                    }
                }
            }
        }

        return $this->grantsByUser[$user->id] = $set;
    }

    /**
     * @return array<string, string>
     */
    private function prefixes(): array
    {
        if ($this->prefixes === null) {
            $map = ModuleAccessCatalog::routePrefixes();
            uksort($map, fn (string $a, string $b): int => strlen($b) <=> strlen($a));
            $this->prefixes = $map;
        }

        return $this->prefixes;
    }

    private function prefixMatches(string $name, string $prefix): bool
    {
        if (str_ends_with($prefix, '.')) {
            return str_starts_with($name, $prefix);
        }

        return $name === $prefix || str_starts_with($name, $prefix.'.');
    }

    private function actionFor(string $name, string $method): string
    {
        $method = strtoupper($method);

        if (str_contains($name, 'cancel')) {
            return 'cancel';
        }

        if (preg_match('/export|download|invoice|\.pdf/', $name) === 1) {
            return 'download';
        }

        if ($this->isReportRoute($name) && in_array($method, ['GET', 'HEAD'], true)) {
            return 'report';
        }

        if ($method === 'DELETE' || str_contains($name, '.destroy') || str_contains($name, '.delete')) {
            return 'delete';
        }

        if (in_array($method, ['PUT', 'PATCH'], true)) {
            return 'update';
        }

        if ($method === 'POST') {
            return 'create';
        }

        if (in_array($method, ['GET', 'HEAD'], true) && preg_match('/(^|\.)edit($|\.)/', $name) === 1) {
            return 'update';
        }

        return 'select';
    }

    private function isReportRoute(string $name): bool
    {
        return str_starts_with($name, 'reports.')
            || str_starts_with($name, 'daily-reports.')
            || $name === 'cash-flows.index'
            || str_starts_with($name, 'finance.profit-loss')
            || str_starts_with($name, 'finance.cash-monitoring');
    }
}
