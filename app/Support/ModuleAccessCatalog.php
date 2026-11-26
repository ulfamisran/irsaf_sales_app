<?php

namespace App\Support;

use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ModuleAccessCatalog
{
    public const ACTIONS = [
        'select' => 'Lihat',
        'create' => 'Tambah',
        'update' => 'Ubah',
        'delete' => 'Hapus',
        'download' => 'Unduh',
        'report' => 'Laporan',
        'cancel' => 'Batal transaksi',
        'authorize' => 'Otorisasi',
    ];

    /**
     * Halaman yang dibuka lewat centang Laporan, bukan Lihat.
     *
     * @var list<string>
     */
    public const REPORT_MODULES = [
        'reports',
        'daily-reports',
        'cash-flows',
        'cash-monitoring',
        'profit-loss',
        'profit-loss-comparison',
    ];

    /**
     * Modul yang mendukung alur otorisasi (centang Otorisasi di akses role).
     *
     * @var list<string>
     */
    public const AUTHORIZATION_MODULES = [
        'purchases',
        'stock-mutations',
        'sales',
        'unit-exchanges',
        'rentals',
        'services',
    ];

    /**
     * @var list<array{slug: string, name: string, group: string, sort: int}>
     */
    public const MODULES = [
        ['slug' => 'dashboard', 'name' => 'Dashboard', 'group' => 'Umum', 'sort' => 10],
        ['slug' => 'warehouses', 'name' => 'Gudang', 'group' => 'Master', 'sort' => 20],
        ['slug' => 'branches', 'name' => 'Cabang', 'group' => 'Master', 'sort' => 30],
        ['slug' => 'categories', 'name' => 'Kategori', 'group' => 'Master', 'sort' => 40],
        ['slug' => 'distributors', 'name' => 'Distributor', 'group' => 'Master', 'sort' => 50],
        ['slug' => 'products', 'name' => 'Produk', 'group' => 'Master', 'sort' => 60],
        ['slug' => 'customers', 'name' => 'Pelanggan', 'group' => 'Master', 'sort' => 70],
        ['slug' => 'payment-methods', 'name' => 'Metode Pembayaran', 'group' => 'Master', 'sort' => 80],
        ['slug' => 'expense-categories', 'name' => 'Jenis Pengeluaran', 'group' => 'Master', 'sort' => 90],
        ['slug' => 'income-categories', 'name' => 'Kategori Pemasukan', 'group' => 'Master', 'sort' => 100],
        ['slug' => 'purchases', 'name' => 'Pembelian', 'group' => 'Stok', 'sort' => 110],
        ['slug' => 'purchase-receiving', 'name' => 'Cek Pembelian Barang', 'group' => 'Stok', 'sort' => 120],
        ['slug' => 'incoming-goods', 'name' => 'Barang Masuk', 'group' => 'Stok', 'sort' => 130],
        ['slug' => 'stock-mutations', 'name' => 'Distribusi Barang', 'group' => 'Stok', 'sort' => 140],
        ['slug' => 'stock-units', 'name' => 'Daftar Unit', 'group' => 'Stok', 'sort' => 150],
        ['slug' => 'stock-monitoring', 'name' => 'Monitoring Stok', 'group' => 'Stok', 'sort' => 160],
        ['slug' => 'stock-inout', 'name' => 'Mutasi Stok', 'group' => 'Stok', 'sort' => 170],
        ['slug' => 'damaged-goods', 'name' => 'Barang Rusak Cadangan', 'group' => 'Stok', 'sort' => 180],
        ['slug' => 'sales', 'name' => 'Penjualan', 'group' => 'Transaksi', 'sort' => 190],
        ['slug' => 'unit-exchanges', 'name' => 'Tukar Unit', 'group' => 'Transaksi', 'sort' => 195],
        ['slug' => 'services', 'name' => 'Service Laptop', 'group' => 'Transaksi', 'sort' => 200],
        ['slug' => 'rentals', 'name' => 'Penyewaan', 'group' => 'Transaksi', 'sort' => 210],
        ['slug' => 'debts', 'name' => 'Data Utang', 'group' => 'Kas', 'sort' => 220],
        ['slug' => 'cash-out', 'name' => 'Pengeluaran Dana', 'group' => 'Kas', 'sort' => 230],
        ['slug' => 'cash-out-external', 'name' => 'Pengeluaran Dana Eksternal', 'group' => 'Kas', 'sort' => 240],
        ['slug' => 'cash-in', 'name' => 'Pemasukan Lainnya', 'group' => 'Kas', 'sort' => 250],
        ['slug' => 'mutasi-dana', 'name' => 'Mutasi Dana', 'group' => 'Kas', 'sort' => 260],
        ['slug' => 'cash-flows', 'name' => 'Arus Kas', 'group' => 'Finance', 'sort' => 270],
        ['slug' => 'cash-monitoring', 'name' => 'Monitoring Kas', 'group' => 'Finance', 'sort' => 280],
        ['slug' => 'saldo-adjustment', 'name' => 'Penyesuaian Saldo', 'group' => 'Finance', 'sort' => 290],
        ['slug' => 'profit-loss', 'name' => 'Laba Rugi', 'group' => 'Finance', 'sort' => 300],
        ['slug' => 'profit-loss-comparison', 'name' => 'Perbandingan Laba Rugi', 'group' => 'Finance', 'sort' => 310],
        ['slug' => 'reports', 'name' => 'Laporan', 'group' => 'Analitik', 'sort' => 320],
        ['slug' => 'daily-reports', 'name' => 'Laporan Harian', 'group' => 'Analitik', 'sort' => 330],
        ['slug' => 'landing-page', 'name' => 'Kelola Landing Page', 'group' => 'Pengaturan', 'sort' => 340],
        ['slug' => 'users', 'name' => 'Kelola User', 'group' => 'Pengaturan', 'sort' => 350],
        ['slug' => 'verification', 'name' => 'Verifikasi Transaksi', 'group' => 'Transaksi', 'sort' => 215],
    ];

    public static function supportsAuthorization(string $slug): bool
    {
        return in_array($slug, self::AUTHORIZATION_MODULES, true);
    }

    /**
     * Prefix nama route ke slug modul. Prefix yang lebih panjang dicek lebih dulu.
     *
     * @return array<string, string>
     */
    public static function routePrefixes(): array
    {
        return [
            'dashboard' => 'dashboard',
            'warehouses.' => 'warehouses',
            'branches.' => 'branches',
            'categories.' => 'categories',
            'distributors.' => 'distributors',
            'products.' => 'products',
            'customers.' => 'customers',
            'payment-methods.' => 'payment-methods',
            'expense-categories.' => 'expense-categories',
            'income-categories.' => 'income-categories',
            'purchases.' => 'purchases',
            'purchase-receiving.' => 'purchase-receiving',
            'incoming-goods.' => 'incoming-goods',
            'stock-mutations.' => 'stock-mutations',
            'stock-units.' => 'stock-units',
            'stock-monitoring.' => 'stock-monitoring',
            'stock-inout.' => 'stock-inout',
            'damaged-goods.' => 'damaged-goods',
            'sales.unit-exchanges.' => 'unit-exchanges',
            'sales.' => 'sales',
            'services.' => 'services',
            'rentals.' => 'rentals',
            'verification.' => 'verification',
            'debts.' => 'debts',
            'cash-flows.out.external.' => 'cash-out-external',
            'cash-flows.out.' => 'cash-out',
            'cash-flows.in.' => 'cash-in',
            'cash-flows.' => 'cash-flows',
            'saldo-adjustment.' => 'saldo-adjustment',
            'mutasi-dana.' => 'mutasi-dana',
            'finance.profit-loss-comparison' => 'profit-loss-comparison',
            'finance.profit-loss' => 'profit-loss',
            'finance.cash-monitoring' => 'cash-monitoring',
            'reports.' => 'reports',
            'daily-reports.' => 'daily-reports',
            'landing-page.' => 'landing-page',
            'users.' => 'users',
            'admin-tx.edit-sale' => 'sales',
            'admin-tx.update-sale' => 'sales',
            'admin-tx.edit-service' => 'services',
            'admin-tx.update-service' => 'services',
            'admin-tx.edit-rental' => 'rentals',
            'admin-tx.update-rental' => 'rentals',
            'admin-tx.edit-purchase' => 'purchases',
            'admin-tx.update-purchase' => 'purchases',
        ];
    }

    public static function menuAction(string $slug): string
    {
        return in_array($slug, self::REPORT_MODULES, true) ? 'report' : 'select';
    }

    /**
     * @return list<string>
     */
    public static function actionKeys(): array
    {
        return array_keys(self::ACTIONS);
    }

    public static function install(): void
    {
        if (! Schema::hasTable('app_modules') || ! Schema::hasTable('role_module_permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        self::syncModuleRows();

        // Backfill (authorize / tukar-unit) must NOT run before this check — otherwise
        // grantDefaults() is skipped forever and system roles keep empty CRUD access.
        if (! DB::table('role_module_permissions')->exists()) {
            self::grantDefaults();
        }

        self::backfillUnitExchangeAndAuthorizeGrants();
        self::ensureDefaultsForUnconfiguredRoles();
    }

    public static function syncModules(): void
    {
        self::syncModuleRows();
        self::backfillUnitExchangeAndAuthorizeGrants();
    }

    private static function syncModuleRows(): void
    {
        $now = now();
        foreach (self::MODULES as $module) {
            DB::table('app_modules')->updateOrInsert(
                ['slug' => $module['slug']],
                [
                    'name' => $module['name'],
                    'group_name' => $module['group'],
                    'sort_order' => $module['sort'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    /**
     * Role yang belum pernah dikonfigurasi CRUD (kosong / hanya sisa backfill
     * authorize+verification) mendapat grant default katalog agar tombol seperti
     * "Tambah Produk" mengikuti canModule, bukan whitelist role di Blade.
     */
    public static function ensureDefaultsForUnconfiguredRoles(): void
    {
        if (! Schema::hasTable('app_modules') || ! Schema::hasTable('role_module_permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        $roleIds = DB::table('roles')->pluck('id', 'name');
        $moduleIds = DB::table('app_modules')->pluck('id', 'slug');
        $now = now();
        $rows = [];

        foreach (self::defaultGrantMap() as $roleName => $modules) {
            $roleId = $roleIds[$roleName] ?? null;
            if (! $roleId) {
                continue;
            }

            if (! self::roleLooksUnconfigured((int) $roleId)) {
                continue;
            }

            foreach ($modules as $slug => $actions) {
                $moduleId = $moduleIds[$slug] ?? null;
                if (! $moduleId) {
                    continue;
                }
                foreach ($actions as $action) {
                    $exists = DB::table('role_module_permissions')
                        ->where('role_id', $roleId)
                        ->where('module_id', $moduleId)
                        ->where('action', $action)
                        ->exists();
                    if ($exists) {
                        continue;
                    }
                    $rows[] = [
                        'role_id' => $roleId,
                        'module_id' => $moduleId,
                        'action' => $action,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        foreach (array_chunk($rows, 400) as $chunk) {
            DB::table('role_module_permissions')->insert($chunk);
        }
    }

    /**
     * Belum dikonfigurasi jika tidak ada grant sama sekali, atau hanya grant
     * authorize / modul verification (sisa backfill otomatis).
     *
     * @param  iterable<int, object>|null  $rows  Optional rows with slug+action to avoid extra query.
     */
    public static function roleLooksUnconfigured(int $roleId, ?iterable $rows = null): bool
    {
        if ($rows === null) {
            $rows = DB::table('role_module_permissions as p')
                ->join('app_modules as m', 'm.id', '=', 'p.module_id')
                ->where('p.role_id', $roleId)
                ->get(['m.slug', 'p.action']);
        }

        foreach ($rows as $row) {
            $action = is_array($row) ? ($row['action'] ?? null) : ($row->action ?? null);
            $slug = is_array($row) ? ($row['slug'] ?? null) : ($row->slug ?? null);
            if ($action !== 'authorize' && $slug !== 'verification') {
                return false;
            }
        }

        return true;
    }

    /**
     * Salin akses penjualan → tukar unit (jika belum ada) dan berikan
     * aksi otorisasi ke role kepala/admin pusat untuk modul otorisasi.
     */
    public static function backfillUnitExchangeAndAuthorizeGrants(): void
    {
        if (! Schema::hasTable('app_modules') || ! Schema::hasTable('role_module_permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        $now = now();
        $moduleIds = DB::table('app_modules')->pluck('id', 'slug');
        $salesId = $moduleIds['sales'] ?? null;
        $exchangeId = $moduleIds['unit-exchanges'] ?? null;
        if ($salesId && $exchangeId) {
            // Hanya salin sekali per role: jika role belum punya akses tukar unit sama sekali.
            $rolesWithExchange = DB::table('role_module_permissions')
                ->where('module_id', $exchangeId)
                ->distinct()
                ->pluck('role_id')
                ->all();
            $salesRows = DB::table('role_module_permissions')
                ->where('module_id', $salesId)
                ->when($rolesWithExchange !== [], fn ($q) => $q->whereNotIn('role_id', $rolesWithExchange))
                ->get(['role_id', 'action']);
            $insert = [];
            foreach ($salesRows as $row) {
                if ($row->action === 'authorize') {
                    continue;
                }
                $insert[] = [
                    'role_id' => $row->role_id,
                    'module_id' => $exchangeId,
                    'action' => $row->action,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            foreach (array_chunk($insert, 400) as $chunk) {
                DB::table('role_module_permissions')->insert($chunk);
            }
        }

        $approverRoles = [
            Role::SUPER_ADMIN,
            Role::ADMIN_PUSAT,
            Role::KEPALA_CABANG,
            Role::KEPALA_GUDANG,
        ];
        $roleIds = DB::table('roles')->whereIn('name', $approverRoles)->pluck('id', 'name');
        $authInsert = [];
        foreach (self::AUTHORIZATION_MODULES as $slug) {
            $moduleId = $moduleIds[$slug] ?? null;
            if (! $moduleId) {
                continue;
            }
            foreach ($roleIds as $roleId) {
                $exists = DB::table('role_module_permissions')
                    ->where('role_id', $roleId)
                    ->where('module_id', $moduleId)
                    ->where('action', 'authorize')
                    ->exists();
                if ($exists) {
                    continue;
                }
                $authInsert[] = [
                    'role_id' => $roleId,
                    'module_id' => $moduleId,
                    'action' => 'authorize',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        foreach (array_chunk($authInsert, 400) as $chunk) {
            DB::table('role_module_permissions')->insert($chunk);
        }
    }

    /**
     * @return array<string, array<string, list<string>>>
     */
    public static function grantsForRole(string $roleName): array
    {
        $map = self::defaultGrantMap();
        $modules = $map[$roleName] ?? [];
        $out = [];
        foreach ($modules as $slug => $actions) {
            $out[$slug] = array_values($actions);
        }

        return $out;
    }

    public static function grantDefaults(): void
    {
        $roleIds = DB::table('roles')->pluck('id', 'name');
        $moduleIds = DB::table('app_modules')->pluck('id', 'slug');
        $now = now();
        $rows = [];

        foreach (self::defaultGrantMap() as $roleName => $modules) {
            $roleId = $roleIds[$roleName] ?? null;
            if (! $roleId) {
                continue;
            }
            foreach ($modules as $slug => $actions) {
                $moduleId = $moduleIds[$slug] ?? null;
                if (! $moduleId) {
                    continue;
                }
                foreach ($actions as $action) {
                    $rows[] = [
                        'role_id' => $roleId,
                        'module_id' => $moduleId,
                        'action' => $action,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        foreach (array_chunk($rows, 400) as $chunk) {
            DB::table('role_module_permissions')->insert($chunk);
        }
    }

    /**
     * @return array<string, array<string, array<string, string>>>
     */
    private static function defaultGrantMap(): array
    {
        static $map = null;
        if ($map !== null) {
            return $map;
        }

        $map = [];
        $see = ['select'];
        $export = ['select', 'download'];
        $crud = ['select', 'create', 'update', 'delete'];
        $entry = ['select', 'create'];
        $transact = ['select', 'create', 'download'];
        $service = ['select', 'create', 'update', 'delete', 'download'];
        $serviceGudang = ['select', 'create', 'update', 'download'];
        $receive = ['select', 'create', 'update'];
        $reportPage = ['select', 'report', 'download'];
        $purchase = ['select', 'create', 'update', 'download'];

        foreach (self::MODULES as $module) {
            foreach (self::actionKeys() as $action) {
                $map[Role::SUPER_ADMIN][$module['slug']][$action] = $action;
            }
        }

        foreach (self::MODULES as $module) {
            if (in_array($module['slug'], ['landing-page', 'users', 'daily-reports'], true)) {
                continue;
            }
            foreach (self::actionKeys() as $action) {
                $map[Role::ADMIN_PUSAT][$module['slug']][$action] = $action;
            }
        }

        self::grantMany($map, Role::ADMIN_CABANG, [
            'dashboard' => $see,
            'branches' => $crud,
            'categories' => $crud,
            'distributors' => $crud,
            'products' => $crud,
            'customers' => $crud,
            'payment-methods' => $crud,
            'expense-categories' => $crud,
            'income-categories' => $crud,
            'purchases' => $purchase,
            'purchase-receiving' => $receive,
            'incoming-goods' => $entry,
            'stock-mutations' => $transact,
            'stock-units' => $export,
            'stock-monitoring' => $see,
            'stock-inout' => $export,
            'damaged-goods' => $entry,
            'sales' => $transact,
            'unit-exchanges' => $transact,
            'services' => $service,
            'rentals' => $transact,
            'debts' => $export,
            'cash-out' => $entry,
            'cash-out-external' => $entry,
            'cash-in' => $entry,
            'mutasi-dana' => $entry,
            'cash-flows' => $reportPage,
            'cash-monitoring' => $reportPage,
            'profit-loss' => $reportPage,
            'reports' => $reportPage,
        ]);

        self::grantMany($map, Role::ADMIN_GUDANG, [
            'dashboard' => $see,
            'warehouses' => $crud,
            'categories' => $crud,
            'distributors' => $crud,
            'products' => $crud,
            'purchases' => array_merge($purchase, ['cancel']),
            'purchase-receiving' => $receive,
            'incoming-goods' => $entry,
            'stock-mutations' => $transact,
            'stock-units' => $export,
            'stock-monitoring' => $see,
            'stock-inout' => $export,
            'sales' => $transact,
            'unit-exchanges' => $transact,
            'services' => $serviceGudang,
            'rentals' => $transact,
            'debts' => $export,
            'cash-out' => $entry,
            'cash-out-external' => $entry,
            'cash-in' => $entry,
            'mutasi-dana' => $entry,
            'reports' => $reportPage,
        ]);

        self::grantMany($map, Role::KASIR, [
            'dashboard' => $see,
            'customers' => $crud,
            'sales' => $transact,
            'unit-exchanges' => $transact,
            'services' => $service,
        ]);

        $map[Role::KEPALA_CABANG] = $map[Role::ADMIN_CABANG];
        foreach ($map[Role::KASIR] as $slug => $actions) {
            foreach ($actions as $action) {
                $map[Role::KEPALA_CABANG][$slug][$action] = $action;
            }
        }
        $map[Role::KEPALA_GUDANG] = $map[Role::ADMIN_GUDANG];
        foreach ($map[Role::KASIR] as $slug => $actions) {
            foreach ($actions as $action) {
                $map[Role::KEPALA_GUDANG][$slug][$action] = $action;
            }
        }
        foreach (self::actionKeys() as $action) {
            $map[Role::KEPALA_CABANG]['verification'][$action] = $action;
            $map[Role::KEPALA_GUDANG]['verification'][$action] = $action;
            $map[Role::SUPER_ADMIN]['verification'][$action] = $action;
            $map[Role::ADMIN_PUSAT]['verification'][$action] = $action;
        }
        foreach (self::AUTHORIZATION_MODULES as $slug) {
            $map[Role::KEPALA_CABANG][$slug]['authorize'] = 'authorize';
            $map[Role::KEPALA_GUDANG][$slug]['authorize'] = 'authorize';
            $map[Role::ADMIN_PUSAT][$slug]['authorize'] = 'authorize';
            $map[Role::SUPER_ADMIN][$slug]['authorize'] = 'authorize';
        }

        return $map;
    }

    /**
     * @param  array<string, array<string, array<string, string>>>  $map
     * @param  array<string, list<string>>  $modules
     */
    private static function grantMany(array &$map, string $role, array $modules): void
    {
        foreach ($modules as $slug => $actions) {
            foreach ($actions as $action) {
                $map[$role][$slug][$action] = $action;
            }
        }
    }
}
