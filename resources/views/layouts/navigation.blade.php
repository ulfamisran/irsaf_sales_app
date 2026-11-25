{{-- Sidebar - uses Alpine.js sidebarOpen from parent --}}
{{-- Desktop background spacer (keeps sidebar background full height) --}}
<div class="hidden lg:block w-72 shrink-0">
    <div class="min-h-full bg-gradient-to-b from-slate-900 via-slate-800 to-slate-900"></div>
</div>

<aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
       class="fixed top-0 left-0 z-50 w-72 h-screen transition-transform duration-300 lg:translate-x-0 shadow-2xl lg:shadow-none"
       aria-label="Sidebar">
    <style>
        .menu-icon-orange { background-color: rgb(249 115 22 / 0.2); color: #fb923c; }
        .group:hover .menu-icon-orange { background-color: rgb(249 115 22 / 0.3); }
    </style>
    <div class="h-full flex flex-col bg-gradient-to-b from-slate-900 via-slate-800 to-slate-900">
        <!-- Mobile close button -->
        <div class="flex justify-end pt-4 px-3 lg:hidden">
            <button @click="sidebarOpen = false" type="button" class="p-2.5 rounded-xl text-gray-400 hover:text-white hover:bg-white/10 transition-all duration-200">
                <span class="sr-only">Tutup sidebar</span>
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Logo -->
        <div class="px-5 pb-5 border-b border-white/10">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3 group">
                <x-application-logo class="block h-8 w-auto fill-current text-indigo-400" />
                <div>
                    <span class="text-lg font-bold text-white tracking-tight">{{ config('app.name', 'IRSAF') }}</span>
                    <p class="text-xs text-gray-500">Manajemen Toko</p>
                </div>
            </a>
        </div>

        <!-- Navigation menu -->
        @php
            $user = auth()->user();
            $see = function (string $slug) use ($user): bool {
                return (bool) ($user?->canModule($slug, \App\Support\ModuleAccessCatalog::menuAction($slug)));
            };
            $canManageAccess = $user && ($user->isStrictSuperAdmin() || $user->isAdminPusat());
        @endphp

        <nav class="flex-1 overflow-y-auto px-4 py-5">
            <ul class="space-y-1">
                <!-- Dashboard -->
                @if ($see('dashboard'))
                <li>
                    <x-sidebar-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-white/5">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"/>
                            </svg>
                        </span>
                        Dashboard
                    </x-sidebar-nav-link>
                </li>
                @endif

                <!-- Data Master -->
                @if ($see('warehouses') || $see('branches') || $see('categories') || $see('distributors') || $see('products') || $see('customers') || $see('payment-methods') || $see('expense-categories') || $see('income-categories'))
                <li x-data="{ open: {{ request()->routeIs('branches.*', 'categories.*', 'distributors.*', 'products.*', 'warehouses.*', 'customers.*', 'payment-methods.*', 'expense-categories.*', 'income-categories.*') ? 'true' : 'false' }} }" class="pt-2">
                    <p class="px-3 mb-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Master</p>
                    <button @click="open = !open" type="button" class="flex items-center justify-between w-full px-3 py-2.5 text-sm font-medium rounded-xl text-gray-300 hover:bg-white/5 hover:text-white transition-all duration-200 group">
                        <span class="flex items-center gap-3">
                            <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-500/20 text-indigo-400 group-hover:bg-indigo-500/30 transition-colors">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                                    <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/>
                                </svg>
                            </span>
                            Data Master
                        </span>
                        <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                    <ul x-show="open" x-transition class="mt-1 space-y-0.5 ml-4 pl-4 border-l-2 border-white/5">
                        @if ($see('warehouses'))
                            <li><x-sidebar-nav-link :href="route('warehouses.index')" :active="request()->routeIs('warehouses.*')">Gudang</x-sidebar-nav-link></li>
                        @endif

                        @if ($see('branches'))
                            <li><x-sidebar-nav-link :href="route('branches.index')" :active="request()->routeIs('branches.*')">Cabang</x-sidebar-nav-link></li>
                        @endif

                        @if ($see('categories'))
                            <li><x-sidebar-nav-link :href="route('categories.index')" :active="request()->routeIs('categories.*')">Kategori</x-sidebar-nav-link></li>
                        @endif
                        @if ($see('distributors'))
                            <li><x-sidebar-nav-link :href="route('distributors.index')" :active="request()->routeIs('distributors.*')">Distributor</x-sidebar-nav-link></li>
                        @endif
                        @if ($see('products'))
                            <li><x-sidebar-nav-link :href="route('products.index')" :active="request()->routeIs('products.*')">Produk</x-sidebar-nav-link></li>
                        @endif

                        @if ($see('customers'))
                            <li><x-sidebar-nav-link :href="route('customers.index')" :active="request()->routeIs('customers.*')">Pelanggan</x-sidebar-nav-link></li>
                        @endif
                        @if ($see('payment-methods'))
                            <li><x-sidebar-nav-link :href="route('payment-methods.index')" :active="request()->routeIs('payment-methods.*')">Metode Pembayaran</x-sidebar-nav-link></li>
                        @endif
                        @if ($see('expense-categories'))
                            <li><x-sidebar-nav-link :href="route('expense-categories.index')" :active="request()->routeIs('expense-categories.*')">Jenis Pengeluaran</x-sidebar-nav-link></li>
                        @endif
                        @if ($see('income-categories'))
                            <li><x-sidebar-nav-link :href="route('income-categories.index')" :active="request()->routeIs('income-categories.*')">Kategori Pemasukan</x-sidebar-nav-link></li>
                        @endif
                    </ul>
                </li>
                @endif

                <!-- Stok -->
                @if ($see('purchases') || $see('purchase-receiving') || $see('incoming-goods') || $see('stock-mutations') || $see('stock-units') || $see('stock-monitoring') || $see('stock-inout') || $see('damaged-goods'))
                @php
                    $receivingPending = ($user && $see('purchase-receiving'))
                        ? app(\App\Services\PurchaseReceivingService::class)->pendingCount($user)
                        : 0;
                @endphp
                <li x-data="{ open: {{ request()->routeIs('incoming-goods.*', 'purchases.*', 'purchase-receiving.*', 'stock-mutations.*', 'stock-units.*', 'stock-inout.*', 'stock-monitoring.*', 'damaged-goods.*') ? 'true' : 'false' }} }" class="pt-2">
                    <p class="px-3 mb-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Stok</p>
                    <button @click="open = !open" type="button" class="flex items-center justify-between w-full px-3 py-2.5 text-sm font-medium rounded-xl text-gray-300 hover:bg-white/5 hover:text-white transition-all duration-200 group">
                        <span class="flex items-center gap-3">
                            <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-emerald-500/20 text-emerald-400 group-hover:bg-emerald-500/30 transition-colors">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M3 3a1 1 0 000 2v8a2 2 0 002 2h2.586l-1.293 1.293a1 1 0 101.414 1.414L10 15.414l2.293 2.293a1 1 0 001.414-1.414L12.414 15H15a2 2 0 002-2V5a1 1 0 100-2H3z" clip-rule="evenodd"/>
                                </svg>
                            </span>
                            Stok
                        </span>
                        <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                    <ul x-show="open" x-transition class="mt-1 space-y-0.5 ml-4 pl-4 border-l-2 border-white/5">
                        @if ($see('purchases'))
                            <li><x-sidebar-nav-link :href="route('purchases.index')" :active="request()->routeIs('purchases.*')">Pembelian</x-sidebar-nav-link></li>
                        @endif
                        @if ($see('purchase-receiving'))
                            <li><x-sidebar-nav-link :href="route('purchase-receiving.index')" :active="request()->routeIs('purchase-receiving.*')">
                                <span class="flex-1">Cek Pembelian Barang</span>
                                @if ($receivingPending > 0)
                                    <span style="min-width:18px; height:18px; padding:0 5px; border-radius:999px; background:#ef4444; color:#fff; font-size:10px; font-weight:700; line-height:18px; text-align:center;">{{ $receivingPending > 99 ? '99+' : $receivingPending }}</span>
                                @endif
                            </x-sidebar-nav-link></li>
                        @endif
                        @if ($see('incoming-goods'))
                            <li><x-sidebar-nav-link :href="route('incoming-goods.index')" :active="request()->routeIs('incoming-goods.*')">Barang Masuk</x-sidebar-nav-link></li>
                        @endif

                        @if ($see('stock-mutations'))
                            <li><x-sidebar-nav-link :href="route('stock-mutations.index')" :active="request()->routeIs('stock-mutations.*')">Distribusi Barang</x-sidebar-nav-link></li>
                        @endif

                        @if ($see('stock-units'))
                            <li><x-sidebar-nav-link :href="route('stock-units.index')" :active="request()->routeIs('stock-units.index', 'stock-units.show', 'stock-units.export', 'stock-units.export-pdf')">Daftar Unit</x-sidebar-nav-link></li>
                            @if (\Illuminate\Support\Facades\Route::has('stock-units.movement'))
                                <li><x-sidebar-nav-link :href="route('stock-units.movement')" :active="request()->routeIs('stock-units.movement')">Pergerakan Barang</x-sidebar-nav-link></li>
                            @endif
                        @endif
                        @if ($see('stock-monitoring'))
                            <li><x-sidebar-nav-link :href="route('stock-monitoring.index')" :active="request()->routeIs('stock-monitoring.*')">Monitoring Stok</x-sidebar-nav-link></li>
                        @endif
                        @if ($see('stock-inout'))
                            <li><x-sidebar-nav-link :href="route('stock-inout.index')" :active="request()->routeIs('stock-inout.*')">Mutasi Stok</x-sidebar-nav-link></li>
                        @endif
                        @if ($see('damaged-goods'))
                            <li><x-sidebar-nav-link :href="route('damaged-goods.index')" :active="request()->routeIs('damaged-goods.*')">Barang Rusak Cadangan</x-sidebar-nav-link></li>
                        @endif
                    </ul>
                </li>
                @endif

                <!-- Penjualan -->
                @if ($see('sales') || $see('unit-exchanges') || $see('services') || $see('rentals') || $see('stock-mutations'))
                <li x-data="{ open: {{ request()->routeIs('sales.*', 'services.*', 'rentals.*', 'print-reports.*') ? 'true' : 'false' }} }" class="pt-2">
                    <p class="px-3 mb-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Transaksi</p>
                    <button @click="open = !open" type="button" class="flex items-center justify-between w-full px-3 py-2.5 text-sm font-medium rounded-xl text-gray-300 hover:bg-white/5 hover:text-white transition-all duration-200 group">
                        <span class="flex items-center gap-3">
                            <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-emerald-500/20 text-emerald-400 group-hover:bg-emerald-500/30 transition-colors">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/>
                                    <path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/>
                                </svg>
                            </span>
                            Penjualan
                        </span>
                        <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                    <ul x-show="open" x-transition class="mt-1 space-y-0.5 ml-4 pl-4 border-l-2 border-white/5">
                        @if ($see('sales'))
                            <li><x-sidebar-nav-link :href="route('sales.index')" :active="request()->routeIs('sales.*') && ! request()->routeIs('sales.unit-exchanges.*')">Penjualan</x-sidebar-nav-link></li>
                        @endif
                        @if ($see('unit-exchanges'))
                            <li><x-sidebar-nav-link :href="route('sales.unit-exchanges.index')" :active="request()->routeIs('sales.unit-exchanges.*')">Tukar Unit</x-sidebar-nav-link></li>
                        @endif
                        @if ($see('services'))
                            <li><x-sidebar-nav-link :href="route('services.index')" :active="request()->routeIs('services.*')">Service Laptop</x-sidebar-nav-link></li>
                        @endif
                        @if ($see('rentals'))
                            <li><x-sidebar-nav-link :href="route('rentals.index')" :active="request()->routeIs('rentals.*')">Penyewaan</x-sidebar-nav-link></li>
                        @endif
                        @if ($see('sales') || $see('purchases') || $see('services') || $see('rentals') || $see('stock-mutations') || $see('unit-exchanges'))
                            <li><x-sidebar-nav-link :href="route('print-reports.index')" :active="request()->routeIs('print-reports.*')">Cetak Laporan</x-sidebar-nav-link></li>
                        @endif
                    </ul>
                </li>
                @endif

                @if (\Illuminate\Support\Facades\Route::has('authorizations.index') && \Illuminate\Support\Facades\Schema::hasTable('authorization_requests'))
                    @php
                        $canAuthorize = $user && app(\App\Services\AuthorizationService::class)->canOpenQueue($user);
                        $authorizationPending = $canAuthorize
                            ? app(\App\Services\AuthorizationService::class)->pendingFor($user)->count()
                            : 0;
                    @endphp
                    @if ($canAuthorize)
                    <li x-data="{ open: {{ request()->routeIs('authorizations.*') ? 'true' : 'false' }} }" class="pt-2">
                        <button @click="open = !open" type="button" class="flex items-center justify-between w-full px-3 py-2.5 text-sm font-medium rounded-xl text-gray-300 hover:bg-white/5 hover:text-white transition-all duration-200 group">
                            <span class="flex items-center gap-3">
                                <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-emerald-500/20 text-emerald-400">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 1a1 1 0 01.894.553l1.618 3.236 3.57.519a1 1 0 01.554 1.706l-2.584 2.52.61 3.555a1 1 0 01-1.451 1.054L10 12.347l-3.211 1.796a1 1 0 01-1.451-1.054l.61-3.555-2.584-2.52a1 1 0 01.554-1.706l3.57-.519L9.106 1.553A1 1 0 0110 1z" clip-rule="evenodd"/></svg>
                                </span>
                                Otorisasi
                            </span>
                            <span class="flex items-center gap-2">
                                @if ($authorizationPending > 0)
                                    <span style="min-width:18px; height:18px; padding:0 5px; border-radius:999px; background:#ef4444; color:#fff; font-size:10px; font-weight:700; line-height:18px; text-align:center;">{{ $authorizationPending > 99 ? '99+' : $authorizationPending }}</span>
                                @endif
                                <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                </svg>
                            </span>
                        </button>
                        <ul x-show="open" x-transition class="mt-1 space-y-0.5 ml-4 pl-4 border-l-2 border-white/5">
                            <li><x-sidebar-nav-link :href="route('authorizations.index', ['tab' => 'siap'])" :active="request()->routeIs('authorizations.*') && request('tab', 'siap') === 'siap'">
                                <span class="flex-1">{{ __('Data Siap Otorisasi') }}</span>
                                @if ($authorizationPending > 0)
                                    <span style="min-width:18px; height:18px; padding:0 5px; border-radius:999px; background:#ef4444; color:#fff; font-size:10px; font-weight:700; line-height:18px; text-align:center;">{{ $authorizationPending > 99 ? '99+' : $authorizationPending }}</span>
                                @endif
                            </x-sidebar-nav-link></li>
                            <li><x-sidebar-nav-link :href="route('authorizations.index', ['tab' => 'riwayat'])" :active="request()->routeIs('authorizations.*') && request('tab') === 'riwayat'">
                                <span class="flex-1">{{ __('Riwayat Otorisasi') }}</span>
                            </x-sidebar-nav-link></li>
                        </ul>
                    </li>
                    @endif
                @endif

                <!-- Kas (Pengeluaran & Pemasukan Lainnya) -->
                @if ($see('debts') || $see('cash-out') || $see('cash-out-external') || $see('cash-in') || $see('mutasi-dana'))
                <li x-data="{ open: {{ request()->routeIs('cash-flows.out.*', 'cash-flows.in.*', 'expense-categories.*', 'debts.*', 'mutasi-dana.*') ? 'true' : 'false' }} }" class="pt-2">
                    <button @click="open = !open" type="button" class="flex items-center justify-between w-full px-3 py-2.5 text-sm font-medium rounded-xl text-gray-300 hover:bg-white/5 hover:text-white transition-all duration-200 group">
                            <span class="flex items-center gap-3">
                            <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-amber-500/20 text-amber-400 group-hover:bg-amber-500/30 transition-colors">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4z" clip-rule="evenodd"/>
                                    <path fill-rule="evenodd" d="M16 4a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6a2 2 0 012-2h10z" clip-rule="evenodd"/>
                                </svg>
                            </span>
                            Kas
                        </span>
                        <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                    <ul x-show="open" x-transition class="mt-1 space-y-0.5 ml-4 pl-4 border-l-2 border-white/5">
                        @if ($see('debts'))
                        <li><x-sidebar-nav-link :href="route('debts.index')" :active="request()->routeIs('debts.*')">Data Utang</x-sidebar-nav-link></li>
                        @endif
                        @if ($see('cash-out'))
                        <li><x-sidebar-nav-link :href="route('cash-flows.out.index')" :active="request()->routeIs('cash-flows.out.index')">Pengeluaran Dana</x-sidebar-nav-link></li>
                        @endif
                        @if ($see('cash-out-external'))
                        <li><x-sidebar-nav-link :href="route('cash-flows.out.external.index')" :active="request()->routeIs('cash-flows.out.external.*')">Pengeluaran Dana Eksternal</x-sidebar-nav-link></li>
                        @endif
                        @if ($see('cash-in'))
                        <li><x-sidebar-nav-link :href="route('cash-flows.in.index')" :active="request()->routeIs('cash-flows.in.*')">Pemasukan Lainnya</x-sidebar-nav-link></li>
                        @endif
                        @if ($see('mutasi-dana'))
                        <li><x-sidebar-nav-link :href="route('mutasi-dana.index')" :active="request()->routeIs('mutasi-dana.*')">Mutasi Dana</x-sidebar-nav-link></li>
                        @endif
                    </ul>
                </li>
                @endif

                <!-- Finance -->
                @if ($see('cash-flows') || $see('cash-monitoring') || $see('saldo-adjustment') || $see('profit-loss') || $see('profit-loss-comparison'))
                <li x-data="{ open: {{ request()->routeIs('cash-flows.*', 'finance.*', 'saldo-adjustment.*') ? 'true' : 'false' }} }" class="pt-2">
                    <p class="px-3 mb-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Finance</p>
                    <button @click="open = !open" type="button" class="flex items-center justify-between w-full px-3 py-2.5 text-sm font-medium rounded-xl text-gray-300 hover:bg-white/5 hover:text-white transition-all duration-200 group">
                        <span class="flex items-center gap-3">
                            <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-amber-500/20 text-amber-400 group-hover:bg-amber-500/30 transition-colors">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M3 3a1 1 0 000 2v10a2 2 0 002 2h10a1 1 0 100-2H6a1 1 0 01-1-1V5a1 1 0 00-1-1H3z" clip-rule="evenodd" />
                                </svg>
                            </span>
                            Finance
                        </span>
                        <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                    <ul x-show="open" x-transition class="mt-1 space-y-0.5 ml-4 pl-4 border-l-2 border-white/5">
                        @if ($see('cash-flows'))
                        <li><x-sidebar-nav-link :href="route('cash-flows.index')" :active="request()->routeIs('cash-flows.index')">Arus Kas</x-sidebar-nav-link></li>
                        @endif
                        @if ($see('cash-monitoring'))
                        <li><x-sidebar-nav-link :href="route('finance.cash-monitoring')" :active="request()->routeIs('finance.cash-monitoring')">Monitoring Kas</x-sidebar-nav-link></li>
                        @endif
                        @if ($see('saldo-adjustment'))
                        <li><x-sidebar-nav-link :href="route('saldo-adjustment.index')" :active="request()->routeIs('saldo-adjustment.*')">Penyesuaian Saldo</x-sidebar-nav-link></li>
                        @endif
                        @if ($see('profit-loss'))
                        <li><x-sidebar-nav-link :href="route('finance.profit-loss')" :active="request()->routeIs('finance.profit-loss')">Laba Rugi</x-sidebar-nav-link></li>
                        @endif
                        @if ($see('profit-loss-comparison'))
                        <li><x-sidebar-nav-link :href="route('finance.profit-loss-comparison')" :active="request()->routeIs('finance.profit-loss-comparison')">Perbandingan Laba Rugi</x-sidebar-nav-link></li>
                        @endif
                    </ul>
                </li>
                @endif

                <!-- Laporan -->
                @if ($see('reports') || $see('daily-reports'))
                <li x-data="{ open: {{ request()->routeIs('reports.*', 'daily-reports.*') ? 'true' : 'false' }} }" class="pt-2">
                    <p class="px-3 mb-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Analitik</p>
                    <button @click="open = !open" type="button" class="flex items-center justify-between w-full px-3 py-2.5 text-sm font-medium rounded-xl text-gray-300 hover:bg-white/5 hover:text-white transition-all duration-200 group">
                        <span class="flex items-center gap-3">
                            <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-cyan-500/20 text-cyan-400 group-hover:bg-cyan-500/30 transition-colors">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M3 3a1 1 0 000 2v8a2 2 0 002 2h2.586l-1.293 1.293a1 1 0 101.414 1.414L10 15.414l2.293 2.293a1 1 0 001.414-1.414L12.414 15H15a2 2 0 002-2V5a1 1 0 100-2H3zm11.707 4.707a1 1 0 00-1.414-1.414L10 9.586 8.707 8.293a1 1 0 00-1.414 0l-2 2a1 1 0 101.414 1.414L8 10.414l1.293 1.293a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                            </span>
                            Laporan
                        </span>
                        <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                    <ul x-show="open" x-transition class="mt-1 space-y-0.5 ml-4 pl-4 border-l-2 border-white/5">
                        @if ($see('reports'))
                        <li><x-sidebar-nav-link :href="route('reports.index')" :active="request()->routeIs('reports.*')">Laporan</x-sidebar-nav-link></li>
                        @endif
                        @if ($see('daily-reports'))
                            <li><x-sidebar-nav-link :href="route('daily-reports.index')" :active="request()->routeIs('daily-reports.*')">Laporan Harian</x-sidebar-nav-link></li>
                        @endif
                    </ul>
                </li>
                @endif

                <!-- Pengaturan -->
                @if ($canManageAccess || $see('landing-page') || $see('users'))
                <li x-data="{ open: {{ request()->routeIs('landing-page.*', 'users.*', 'role-access.*', 'activity-logs.*', 'authorization-settings.*') ? 'true' : 'false' }} }" class="pt-2">
                    <p class="px-3 mb-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Pengaturan</p>
                    <button @click="open = !open" type="button" class="flex items-center justify-between w-full px-3 py-2.5 text-sm font-medium rounded-xl text-gray-300 hover:bg-white/5 hover:text-white transition-all duration-200 group">
                        <span class="flex items-center gap-3">
                            <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-500/20 text-indigo-400 group-hover:bg-indigo-500/30 transition-colors">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V7.414a2 2 0 00-.586-1.414l-2.414-2.414A2 2 0 0014.586 3H4z"/>
                                    <path d="M6 7h8v2H6V7zm0 4h8v2H6v-2z"/>
                                </svg>
                            </span>
                            Pengaturan
                        </span>
                        <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                    <ul x-show="open" x-transition class="mt-1 space-y-0.5 ml-4 pl-4 border-l-2 border-white/5">
                        @if ($user?->isStrictSuperAdmin() && \Illuminate\Support\Facades\Route::has('activity-logs.index'))
                            <li><x-sidebar-nav-link :href="route('activity-logs.index')" :active="request()->routeIs('activity-logs.*')">Log Aktivitas</x-sidebar-nav-link></li>
                        @endif
                        @if ($canManageAccess && \Illuminate\Support\Facades\Route::has('role-access.index'))
                            <li><x-sidebar-nav-link :href="route('role-access.index')" :active="request()->routeIs('role-access.*')">Akses Modul</x-sidebar-nav-link></li>
                        @endif
                        @if ($canManageAccess && \Illuminate\Support\Facades\Route::has('authorization-settings.edit'))
                            <li><x-sidebar-nav-link :href="route('authorization-settings.edit')" :active="request()->routeIs('authorization-settings.*')">Konfigurasi Otorisasi</x-sidebar-nav-link></li>
                        @endif
                        @if ($see('landing-page'))
                            <li><x-sidebar-nav-link :href="route('landing-page.manage')" :active="request()->routeIs('landing-page.*')">Kelola Landing Page</x-sidebar-nav-link></li>
                        @endif
                        @if ($see('users'))
                            <li><x-sidebar-nav-link :href="route('users.index')" :active="request()->routeIs('users.*')">Kelola User</x-sidebar-nav-link></li>
                        @endif
                    </ul>
                </li>
                @endif

                <!-- User -->
                <li x-data="{ open: {{ request()->routeIs('profile.*') ? 'true' : 'false' }} }" class="pt-2 mt-4 border-t border-white/10">
                    <button @click="open = !open" type="button" class="flex items-center justify-between w-full px-3 py-2.5 text-sm font-medium rounded-xl text-gray-300 hover:bg-white/5 hover:text-white transition-all duration-200 group">
                        <span class="flex items-center gap-3">
                            <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-rose-500/20 text-rose-400 group-hover:bg-rose-500/30 transition-colors">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                                </svg>
                            </span>
                            User
                        </span>
                        <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                    <ul x-show="open" x-transition class="mt-1 space-y-0.5 ml-4 pl-4 border-l-2 border-white/5">
                        <li><x-sidebar-nav-link :href="route('profile.edit')" :active="request()->routeIs('profile.*')">Profil</x-sidebar-nav-link></li>
                    </ul>
                </li>
            </ul>
        </nav>
    </div>
</aside>
