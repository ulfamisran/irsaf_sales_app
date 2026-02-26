<x-app-layout>
    <x-slot name="title">{{ __('Dashboard') }}</x-slot>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-800 tracking-tight">
                    {{ __('Dashboard') }}
                </h1>
                <p class="mt-1 text-sm text-slate-500">{{ __('Selamat datang di IRSAF Laptop Sales') }}</p>
            </div>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto space-y-8">
        <!-- Welcome Card -->
        <div class="card-modern">
            <div class="card-modern-header">
                <h2 class="text-lg font-semibold text-slate-800">{{ __('Laptop Sales Management System') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ __('Kelola cabang, produk, gudang, stok, dan penjualan dari satu dashboard') }}</p>
            </div>
            <div class="p-6">
                <p class="text-slate-600 mb-6">{{ __('Gunakan menu navigasi di sidebar untuk mengakses fitur manajemen.') }}</p>

                <!-- Quick Access Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                    <a href="{{ route('branches.index') }}" class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-indigo-50 to-indigo-100/50 p-5 border border-indigo-100 hover:shadow-lg hover:shadow-indigo-100/50 transition-all duration-300 hover:-translate-y-0.5">
                        <div class="flex items-start gap-4">
                            <span class="flex items-center justify-center w-12 h-12 rounded-xl bg-indigo-500 text-white shadow-lg shadow-indigo-500/30 group-hover:scale-110 transition-transform">
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4z" clip-rule="evenodd"/>
                                    <path d="M12 12a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4a2 2 0 012-2h6z"/>
                                </svg>
                            </span>
                            <div>
                                <span class="font-semibold text-slate-800 group-hover:text-indigo-700">{{ __('Cabang') }}</span>
                                <p class="text-sm text-slate-500 mt-0.5">{{ __('Kelola cabang') }}</p>
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('products.index') }}" class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-emerald-50 to-emerald-100/50 p-5 border border-emerald-100 hover:shadow-lg hover:shadow-emerald-100/50 transition-all duration-300 hover:-translate-y-0.5">
                        <div class="flex items-start gap-4">
                            <span class="flex items-center justify-center w-12 h-12 rounded-xl bg-emerald-500 text-white shadow-lg shadow-emerald-500/30 group-hover:scale-110 transition-transform">
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 2a4 4 0 00-4 4v1H5a1 1 0 00-.994.89l-1 9A1 1 0 004 18h12a1 1 0 00.994-1.11l-1-9A1 1 0 0015 7h-1V6a4 4 0 00-4-4zm2 5V6a2 2 0 10-4 0v1h4z" clip-rule="evenodd"/>
                                </svg>
                            </span>
                            <div>
                                <span class="font-semibold text-slate-800 group-hover:text-emerald-700">{{ __('Produk') }}</span>
                                <p class="text-sm text-slate-500 mt-0.5">{{ __('Kelola produk') }}</p>
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('sales.index') }}" class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-amber-50 to-amber-100/50 p-5 border border-amber-100 hover:shadow-lg hover:shadow-amber-100/50 transition-all duration-300 hover:-translate-y-0.5">
                        <div class="flex items-start gap-4">
                            <span class="flex items-center justify-center w-12 h-12 rounded-xl bg-amber-500 text-white shadow-lg shadow-amber-500/30 group-hover:scale-110 transition-transform">
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/>
                                    <path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/>
                                </svg>
                            </span>
                            <div>
                                <span class="font-semibold text-slate-800 group-hover:text-amber-700">{{ __('Penjualan') }}</span>
                                <p class="text-sm text-slate-500 mt-0.5">{{ __('Buat & lihat penjualan') }}</p>
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('reports.index') }}" class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-cyan-50 to-cyan-100/50 p-5 border border-cyan-100 hover:shadow-lg hover:shadow-cyan-100/50 transition-all duration-300 hover:-translate-y-0.5">
                        <div class="flex items-start gap-4">
                            <span class="flex items-center justify-center w-12 h-12 rounded-xl bg-cyan-500 text-white shadow-lg shadow-cyan-500/30 group-hover:scale-110 transition-transform">
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M3 3a1 1 0 000 2v8a2 2 0 002 2h2.586l-1.293 1.293a1 1 0 101.414 1.414L10 15.414l2.293 2.293a1 1 0 001.414-1.414L12.414 15H15a2 2 0 002-2V5a1 1 0 100-2H3zm11.707 4.707a1 1 0 00-1.414-1.414L10 9.586 8.707 8.293a1 1 0 00-1.414 0l-2 2a1 1 0 101.414 1.414L8 10.414l1.293 1.293a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                            </span>
                            <div>
                                <span class="font-semibold text-slate-800 group-hover:text-cyan-700">{{ __('Laporan') }}</span>
                                <p class="text-sm text-slate-500 mt-0.5">{{ __('Lihat laporan') }}</p>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Charts (below quick access cards) -->
                <div class="mt-8">
                    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4 mb-4">
                        <div>
                            <br><br>
                            <h3 class="text-base font-semibold text-slate-800">{{ __('Trend 7 Hari (Default)') }}</h3>
                            <p class="text-sm text-slate-500 mt-0.5">{{ $chartRangeText ?? '' }}</p>
                        </div>

                        <form method="GET" action="{{ route('dashboard') }}" class="flex flex-wrap gap-3 items-end">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Dari') }}</label>
                                <input type="date" name="date_from" value="{{ request('date_from', $chartDateFrom ?? '') }}"
                                    class="rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Sampai') }}</label>
                                <input type="date" name="date_to" value="{{ request('date_to', $chartDateTo ?? '') }}"
                                    class="rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div class="flex gap-2">
                                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                                    {{ __('Terapkan') }}
                                </button>
                                <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">
                                    {{ __('Reset') }}
                                </a>
                            </div>
                        </form>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="card-modern overflow-hidden">
                            <div class="p-4 border-b border-gray-100">
                                <h4 class="text-sm font-semibold text-slate-800">{{ __('Pergerakan Barang') }}</h4>
                                <p class="text-xs text-slate-500 mt-0.5">{{ ($chartIncomingLabel ?? __('Barang Masuk')) }} vs {{ __('Barang Terjual') }}</p>
                            </div>
                            <div class="p-4">
                                <div class="relative h-64">
                                    <canvas id="chartItems"></canvas>
                                </div>
                            </div>
                        </div>

                        <div class="card-modern overflow-hidden">
                            <div class="p-4 border-b border-gray-100">
                                <h4 class="text-sm font-semibold text-slate-800">{{ __('Pergerakan Dana') }}</h4>
                                <p class="text-xs text-slate-500 mt-0.5">{{ __('Dana Masuk') }} vs {{ __('Dana Keluar') }}</p>
                            </div>
                            <div class="p-4">
                                <div class="relative h-64">
                                    <canvas id="chartCash"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        const labels = @json($chartLabels ?? []);

        const itemsIncomingLabel = @json($chartIncomingLabel ?? 'Barang Masuk');
        const itemsIncoming = @json($chartIncomingQty ?? []);
        const itemsSold = @json($chartSoldQty ?? []);

        const cashIn = @json($chartCashIn ?? []);
        const cashOut = @json($chartCashOut ?? []);

        const fmtIdr = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });

        const commonOptions = {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: true, labels: { boxWidth: 12 } },
                tooltip: { enabled: true }
            },
            scales: {
                x: { grid: { display: false } }
            }
        };

        const elItems = document.getElementById('chartItems');
        if (elItems) {
            new Chart(elItems, {
                type: 'line',
                data: {
                    labels,
                    datasets: [
                        {
                            label: itemsIncomingLabel,
                            data: itemsIncoming,
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16,185,129,0.15)',
                            tension: 0.3,
                            fill: true,
                            pointRadius: 2
                        },
                        {
                            label: 'Barang Terjual',
                            data: itemsSold,
                            borderColor: '#f59e0b',
                            backgroundColor: 'rgba(245,158,11,0.12)',
                            tension: 0.3,
                            fill: true,
                            pointRadius: 2
                        }
                    ]
                },
                options: {
                    ...commonOptions,
                    scales: {
                        ...commonOptions.scales,
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        }

        const elCash = document.getElementById('chartCash');
        if (elCash) {
            new Chart(elCash, {
                type: 'line',
                data: {
                    labels,
                    datasets: [
                        {
                            label: 'Dana Masuk',
                            data: cashIn,
                            borderColor: '#6366f1',
                            backgroundColor: 'rgba(99,102,241,0.12)',
                            tension: 0.3,
                            fill: true,
                            pointRadius: 2
                        },
                        {
                            label: 'Dana Keluar',
                            data: cashOut,
                            borderColor: '#ef4444',
                            backgroundColor: 'rgba(239,68,68,0.10)',
                            tension: 0.3,
                            fill: true,
                            pointRadius: 2
                        }
                    ]
                },
                options: {
                    ...commonOptions,
                    plugins: {
                        ...commonOptions.plugins,
                        tooltip: {
                            callbacks: {
                                label: function(ctx) {
                                    const v = Number(ctx.raw || 0);
                                    return `${ctx.dataset.label}: ${fmtIdr.format(v)}`;
                                }
                            }
                        }
                    },
                    scales: {
                        ...commonOptions.scales,
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: (v) => fmtIdr.format(Number(v))
                            }
                        }
                    }
                }
            });
        }
    </script>
</x-app-layout>
