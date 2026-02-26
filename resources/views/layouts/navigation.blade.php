{{-- Sidebar - uses Alpine.js sidebarOpen from parent --}}
{{-- Desktop background spacer (keeps sidebar background full height) --}}
<div class="hidden lg:block w-72 shrink-0">
    <div class="min-h-full bg-gradient-to-b from-slate-900 via-slate-800 to-slate-900"></div>
</div>

<aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
       class="fixed top-0 left-0 z-50 w-72 h-screen transition-transform duration-300 lg:translate-x-0 shadow-2xl lg:shadow-none"
       aria-label="Sidebar">
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
                <div class="p-2 rounded-xl bg-indigo-500/20 group-hover:bg-indigo-500/30 transition-colors">
                    <x-application-logo class="block h-8 w-auto fill-current text-indigo-400" />
                </div>
                <div>
                    <span class="text-lg font-bold text-white tracking-tight">{{ config('app.name', 'IRSAF') }}</span>
                    <p class="text-xs text-gray-500">Manajemen Toko</p>
                </div>
            </a>
        </div>

        <!-- Navigation menu -->
        <nav class="flex-1 overflow-y-auto px-4 py-5">
            <ul class="space-y-1">
                <!-- Dashboard -->
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

                <!-- Data Master -->
                <li x-data="{ open: {{ request()->routeIs('branches.*', 'categories.*', 'products.*', 'warehouses.*', 'customers.*', 'payment-methods.*') ? 'true' : 'false' }} }" class="pt-2">
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
                        @if (auth()->user()->isSuperAdmin() || auth()->user()->hasAnyRole([\App\Models\Role::STAFF_GUDANG]))
                            <li><x-sidebar-nav-link :href="route('warehouses.index')" :active="request()->routeIs('warehouses.*')">Gudang</x-sidebar-nav-link></li>
                        @endif

                        @if (auth()->user()->isSuperAdmin() || auth()->user()->hasAnyRole([\App\Models\Role::ADMIN_CABANG]))
                            <li><x-sidebar-nav-link :href="route('branches.index')" :active="request()->routeIs('branches.*')">Cabang</x-sidebar-nav-link></li>
                        @endif

                        <li><x-sidebar-nav-link :href="route('categories.index')" :active="request()->routeIs('categories.*')">Kategori</x-sidebar-nav-link></li>
                        <li><x-sidebar-nav-link :href="route('products.index')" :active="request()->routeIs('products.*')">Produk</x-sidebar-nav-link></li>
                        <li><x-sidebar-nav-link :href="route('customers.index')" :active="request()->routeIs('customers.*')">Pelanggan</x-sidebar-nav-link></li>
                        <li><x-sidebar-nav-link :href="route('payment-methods.index')" :active="request()->routeIs('payment-methods.*')">Metode Pembayaran</x-sidebar-nav-link></li>
                        <li><x-sidebar-nav-link :href="route('expense-categories.index')" :active="request()->routeIs('expense-categories.*')">Jenis Pengeluaran</x-sidebar-nav-link></li>
                    </ul>
                </li>

                <!-- Stok -->
                <li x-data="{ open: {{ request()->routeIs('incoming-goods.*', 'stock-mutations.*', 'stock-inout.*') ? 'true' : 'false' }} }" class="pt-2">
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
                        @if (auth()->user()->isSuperAdmin() || auth()->user()->hasAnyRole([\App\Models\Role::STAFF_GUDANG]))
                            <li><x-sidebar-nav-link :href="route('incoming-goods.index')" :active="request()->routeIs('incoming-goods.*')">Barang Masuk</x-sidebar-nav-link></li>
                            <li><x-sidebar-nav-link :href="route('stock-mutations.index')" :active="request()->routeIs('stock-mutations.*')">Distribusi Stok</x-sidebar-nav-link></li>
                        @endif

                        @if (auth()->user()->isSuperAdmin() || auth()->user()->hasAnyRole([\App\Models\Role::STAFF_GUDANG, \App\Models\Role::ADMIN_CABANG, \App\Models\Role::KASIR]))
                            <li><x-sidebar-nav-link :href="route('stock-inout.index')" :active="request()->routeIs('stock-inout.*')">Mutasi Stok (IN/OUT)</x-sidebar-nav-link></li>
                        @endif
                    </ul>
                </li>

                <!-- Penjualan -->
                @if (auth()->user()->isSuperAdmin() || auth()->user()->hasAnyRole([\App\Models\Role::ADMIN_CABANG, \App\Models\Role::KASIR]))
                <li x-data="{ open: {{ request()->routeIs('sales.*', 'services.*') ? 'true' : 'false' }} }" class="pt-2">
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
                        <li><x-sidebar-nav-link :href="route('sales.index')" :active="request()->routeIs('sales.*')">Penjualan</x-sidebar-nav-link></li>
                        <li><x-sidebar-nav-link :href="route('services.index')" :active="request()->routeIs('services.*')">Service Laptop</x-sidebar-nav-link></li>
                    </ul>
                </li>
                @endif

                <!-- Kas (Pengeluaran & Pemasukan Lainnya) -->
                @if (auth()->user()->isSuperAdmin() || auth()->user()->hasAnyRole([\App\Models\Role::ADMIN_CABANG, \App\Models\Role::KASIR]))
                <li x-data="{ open: {{ request()->routeIs('cash-flows.out.*', 'cash-flows.in.*', 'expense-categories.*') ? 'true' : 'false' }} }" class="pt-2">
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
                        <li><x-sidebar-nav-link :href="route('cash-flows.out.index')" :active="request()->routeIs('cash-flows.out.index')">Pengeluaran Dana</x-sidebar-nav-link></li>
                        <li><x-sidebar-nav-link :href="route('cash-flows.in.index')" :active="request()->routeIs('cash-flows.in.*')">Pemasukan Lainnya</x-sidebar-nav-link></li>
                    </ul>
                </li>
                @endif

                <!-- Finance -->
                @if (auth()->user()->isSuperAdmin() || auth()->user()->hasAnyRole([\App\Models\Role::ADMIN_CABANG, \App\Models\Role::KASIR]))
                <li x-data="{ open: {{ request()->routeIs('cash-flows.*', 'finance.*') ? 'true' : 'false' }} }" class="pt-2">
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
                        <li><x-sidebar-nav-link :href="route('cash-flows.index')" :active="request()->routeIs('cash-flows.index')">Arus Kas</x-sidebar-nav-link></li>
                        <li><x-sidebar-nav-link :href="route('finance.cash-monitoring')" :active="request()->routeIs('finance.cash-monitoring')">Monitoring Kas</x-sidebar-nav-link></li>
                        <li><x-sidebar-nav-link :href="route('finance.profit-loss')" :active="request()->routeIs('finance.profit-loss')">Laba Rugi</x-sidebar-nav-link></li>
                    </ul>
                </li>
                @endif

                <!-- Laporan -->
                @if (auth()->user()->isSuperAdmin() || auth()->user()->hasAnyRole([\App\Models\Role::ADMIN_CABANG, \App\Models\Role::KASIR, \App\Models\Role::STAFF_GUDANG]))
                <li x-data="{ open: {{ request()->routeIs('reports.*') ? 'true' : 'false' }} }" class="pt-2">
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
                        <li><x-sidebar-nav-link :href="route('reports.index')" :active="request()->routeIs('reports.*')">Laporan</x-sidebar-nav-link></li>
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
