<x-app-layout>
    <x-slot name="title">{{ __('Perbandingan Laba Rugi') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">
            {{ __('Perbandingan Laba Rugi Antar Gudang & Cabang') }}
        </h2>
    </x-slot>

    <div class="max-w-[1600px] mx-auto" x-data="{ showDiagram: true, showTable: true, transposed: false }">
        <div class="card-modern overflow-hidden mb-6">
            <div class="p-4 border-b border-gray-100">
                <form method="GET" action="{{ route('finance.profit-loss-comparison') }}" class="flex flex-wrap gap-4 items-end">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Dari Tanggal') }}</label>
                        <input type="date" name="date_from" value="{{ request('date_from', $dateFrom ?? '') }}" class="rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Sampai Tanggal') }}</label>
                        <input type="date" name="date_to" value="{{ request('date_to', $dateTo ?? '') }}" class="rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    @php
                        $downloadQuery = request()->query();
                    @endphp
                    <div class="flex gap-2">
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            {{ __('Tampilkan') }}
                        </button>
                        <a href="{{ route('finance.profit-loss-comparison') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">
                            {{ __('Reset') }}
                        </a>
                        <a href="{{ route('finance.profit-loss-comparison.export', $downloadQuery) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700">
                            {{ __('Download Excel') }}
                        </a>
                        <a href="{{ route('finance.profit-loss-comparison.export-pdf', $downloadQuery) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium hover:bg-rose-700">
                            {{ __('Download PDF') }}
                        </a>
                    </div>
                    <div class="w-full">
                        <input type="hidden" name="include_external_expense" value="0">
                        <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                            <input type="checkbox" name="include_external_expense" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" {{ ($includeExternalExpense ?? true) ? 'checked' : '' }}>
                            <span class="text-sm font-medium text-slate-700">{{ __('Hitung Pengeluaran Dana Eksternal') }}</span>
                        </label>
                    </div>
                </form>
            </div>
        </div>

        @if(!empty($comparisonData))
        {{-- Grafik --}}
        <div class="card-modern overflow-hidden mb-6" x-show="showDiagram" x-transition>
            <div class="p-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-4">
                <h3 class="text-sm font-semibold text-slate-700">{{ __('Grafik Perbandingan') }}</h3>
                <button type="button" @click="showDiagram = false" class="text-sm text-slate-500 hover:text-slate-700 inline-flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                    {{ __('Sembunyikan grafik') }}
                </button>
            </div>
            <div class="p-4 space-y-6">
                <div>
                    <p class="text-xs font-medium text-slate-500 uppercase mb-2">{{ __('Pemasukan vs Pengeluaran per Lokasi') }}</p>
                    <div class="h-64">
                        <canvas id="chart-pemasukan-pengeluaran"></canvas>
                    </div>
                </div>
                <div>
                    <p class="text-xs font-medium text-slate-500 uppercase mb-2">{{ __('Laba Bersih per Lokasi') }}</p>
                    <div class="h-64">
                        <canvas id="chart-laba-bersih"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div x-show="!showDiagram" x-transition class="mb-6">
            <button type="button" @click="showDiagram = true" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                {{ __('Tampilkan grafik') }}
            </button>
        </div>
        @endif

        <div class="card-modern overflow-hidden" x-show="showTable" x-transition>
            @if(empty($comparisonData))
                <div class="p-8 text-center text-slate-500">
                    {{ __('Tidak ada data cabang atau gudang untuk ditampilkan.') }}
                </div>
            @else
            <div class="p-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-4">
                <span class="text-sm text-slate-600">{{ __('Periode') }}: {{ $dateFrom ?? '-' }} s/d {{ $dateTo ?? '-' }}</span>
                <div class="flex flex-wrap items-center gap-4">
                    <button type="button" @click="showTable = false" class="text-sm text-slate-500 hover:text-slate-700 inline-flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                        {{ __('Sembunyikan tabel') }}
                    </button>
                    <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" x-model="transposed" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm font-medium text-slate-700">{{ __('Transpose tabel') }}</span>
                    </label>
                </div>
            </div>
            <div class="overflow-x-auto" x-show="!transposed">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase sticky left-0 bg-slate-50 z-10 min-w-[200px]">{{ __('Keterangan') }}</th>
                            @foreach($comparisonData ?? [] as $row)
                                <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase whitespace-nowrap">{{ $row['location']['label'] }}</th>
                            @endforeach
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-600 uppercase whitespace-nowrap bg-indigo-50">{{ __('Total') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @php
                            $totals = [
                                'total_pemasukan' => 0,
                                'total_pengeluaran' => 0,
                                'dana_tukar_tambah' => 0,
                                'beban_barang_rusak' => 0,
                                'laba_bersih' => 0,
                            ];
                            foreach ($comparisonData ?? [] as $row) {
                                $totals['total_pemasukan'] += $row['total_pemasukan'];
                                $totals['total_pengeluaran'] += $row['total_pengeluaran'];
                                $totals['dana_tukar_tambah'] += $row['dana_tukar_tambah'];
                                $totals['beban_barang_rusak'] += $row['beban_barang_rusak'];
                                $totals['laba_bersih'] += $row['laba_bersih'];
                            }
                        @endphp
                        <tr class="hover:bg-slate-50/50">
                            <td class="px-4 py-3 font-medium text-slate-800 sticky left-0 bg-white z-10">{{ __('Total Pemasukan') }}</td>
                            @foreach($comparisonData ?? [] as $row)
                                <td class="px-4 py-3 text-right text-emerald-600">{{ number_format($row['total_pemasukan'], 0, ',', '.') }}</td>
                            @endforeach
                            <td class="px-4 py-3 text-right font-semibold text-emerald-700 bg-indigo-50">{{ number_format($totals['total_pemasukan'], 0, ',', '.') }}</td>
                        </tr>
                        <tr class="hover:bg-slate-50/50">
                            <td class="px-4 py-3 font-medium text-slate-800 sticky left-0 bg-white z-10">{{ __('Pengeluaran') }}</td>
                            @foreach($comparisonData ?? [] as $row)
                                <td class="px-4 py-3 text-right text-red-600">{{ number_format($row['total_pengeluaran'], 0, ',', '.') }}</td>
                            @endforeach
                            <td class="px-4 py-3 text-right font-semibold text-red-700 bg-indigo-50">{{ number_format($totals['total_pengeluaran'], 0, ',', '.') }}</td>
                        </tr>
                        <tr class="hover:bg-slate-50/50">
                            <td class="px-4 py-3 font-medium text-slate-800 sticky left-0 bg-white z-10">{{ __('Dana (Barang Tukar Tambah)') }}</td>
                            @foreach($comparisonData ?? [] as $row)
                                <td class="px-4 py-3 text-right text-indigo-600">{{ number_format($row['dana_tukar_tambah'], 0, ',', '.') }}</td>
                            @endforeach
                            <td class="px-4 py-3 text-right font-semibold text-indigo-700 bg-indigo-50">{{ number_format($totals['dana_tukar_tambah'], 0, ',', '.') }}</td>
                        </tr>
                        <tr class="hover:bg-slate-50/50">
                            <td class="px-4 py-3 font-medium text-slate-800 sticky left-0 bg-white z-10">{{ __('Beban Barang Rusak Cadangan') }}</td>
                            @foreach($comparisonData ?? [] as $row)
                                <td class="px-4 py-3 text-right text-red-600">{{ number_format($row['beban_barang_rusak'], 0, ',', '.') }}</td>
                            @endforeach
                            <td class="px-4 py-3 text-right font-semibold text-red-700 bg-indigo-50">{{ number_format($totals['beban_barang_rusak'], 0, ',', '.') }}</td>
                        </tr>
                        <tr class="border-t-2 border-slate-200 bg-slate-50/50">
                            <td class="px-4 py-3 font-bold text-slate-900 sticky left-0 bg-slate-100 z-10">{{ __('Laba Bersih') }}</td>
                            @foreach($comparisonData ?? [] as $row)
                                <td class="px-4 py-3 text-right font-bold {{ $row['laba_bersih'] >= 0 ? 'text-emerald-600' : 'text-red-600' }}">{{ number_format($row['laba_bersih'], 0, ',', '.') }}</td>
                            @endforeach
                            <td class="px-4 py-3 text-right font-bold {{ $totals['laba_bersih'] >= 0 ? 'text-emerald-700' : 'text-red-700' }} bg-indigo-100">{{ number_format($totals['laba_bersih'], 0, ',', '.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="overflow-x-auto" x-show="transposed" x-cloak style="display: none;">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase sticky left-0 bg-slate-50 z-10 min-w-[180px]">{{ __('Lokasi') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase whitespace-nowrap">{{ __('Total Pemasukan') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase whitespace-nowrap">{{ __('Pengeluaran') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase whitespace-nowrap">{{ __('Dana (Tukar Tambah)') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase whitespace-nowrap">{{ __('Beban Barang Rusak') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-bold text-slate-600 uppercase whitespace-nowrap bg-indigo-50">{{ __('Laba Bersih') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @php
                            $totalsT = [
                                'total_pemasukan' => 0,
                                'total_pengeluaran' => 0,
                                'dana_tukar_tambah' => 0,
                                'beban_barang_rusak' => 0,
                                'laba_bersih' => 0,
                            ];
                            foreach ($comparisonData ?? [] as $row) {
                                $totalsT['total_pemasukan'] += $row['total_pemasukan'];
                                $totalsT['total_pengeluaran'] += $row['total_pengeluaran'];
                                $totalsT['dana_tukar_tambah'] += $row['dana_tukar_tambah'];
                                $totalsT['beban_barang_rusak'] += $row['beban_barang_rusak'];
                                $totalsT['laba_bersih'] += $row['laba_bersih'];
                            }
                        @endphp
                        @foreach($comparisonData ?? [] as $row)
                        <tr class="hover:bg-slate-50/50">
                            <td class="px-4 py-3 font-medium text-slate-800 sticky left-0 bg-white z-10">{{ $row['location']['label'] }}</td>
                            <td class="px-4 py-3 text-right text-emerald-600">{{ number_format($row['total_pemasukan'], 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right text-red-600">{{ number_format($row['total_pengeluaran'], 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right text-indigo-600">{{ number_format($row['dana_tukar_tambah'], 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right text-red-600">{{ number_format($row['beban_barang_rusak'], 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-bold {{ $row['laba_bersih'] >= 0 ? 'text-emerald-600' : 'text-red-600' }}">{{ number_format($row['laba_bersih'], 0, ',', '.') }}</td>
                        </tr>
                        @endforeach
                        <tr class="border-t-2 border-slate-200 bg-slate-50/50">
                            <td class="px-4 py-3 font-bold text-slate-900 sticky left-0 bg-slate-100 z-10">{{ __('Total') }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-emerald-700 bg-indigo-50">{{ number_format($totalsT['total_pemasukan'], 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-red-700 bg-indigo-50">{{ number_format($totalsT['total_pengeluaran'], 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-indigo-700 bg-indigo-50">{{ number_format($totalsT['dana_tukar_tambah'], 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-red-700 bg-indigo-50">{{ number_format($totalsT['beban_barang_rusak'], 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-bold {{ $totalsT['laba_bersih'] >= 0 ? 'text-emerald-700' : 'text-red-700' }} bg-indigo-100">{{ number_format($totalsT['laba_bersih'], 0, ',', '.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            @endif
        </div>
        @if(!empty($comparisonData))
        <div x-show="!showTable" x-transition class="mt-6">
            <button type="button" @click="showTable = true" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                {{ __('Tampilkan tabel') }}
            </button>
        </div>
        @endif

        <div class="mt-4 flex gap-4">
            <a href="{{ route('finance.profit-loss') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                {{ __('Ke Laba Rugi') }}
            </a>
        </div>
    </div>

    @if(!empty($comparisonData))
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        (function() {
            const data = @json($comparisonData ?? []);
            const labels = data.map(r => r.location.label);
            const pemasukan = data.map(r => r.total_pemasukan);
            const pengeluaran = data.map(r => r.total_pengeluaran);
            const labaBersih = data.map(r => r.laba_bersih);

            const colorPemasukan = 'rgb(16, 185, 129)';
            const colorPengeluaran = 'rgb(239, 68, 68)';
            const colorLabaPos = 'rgb(5, 150, 105)';
            const colorLabaNeg = 'rgb(220, 38, 38)';

            new Chart(document.getElementById('chart-pemasukan-pengeluaran'), {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        { label: 'Pemasukan', data: pemasukan, backgroundColor: colorPemasukan },
                        { label: 'Pengeluaran', data: pengeluaran, backgroundColor: colorPengeluaran }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => ctx.raw.toLocaleString('id-ID')
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { callback: v => v.toLocaleString('id-ID') }
                        }
                    }
                }
            });

            new Chart(document.getElementById('chart-laba-bersih'), {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Laba Bersih',
                        data: labaBersih,
                        backgroundColor: labaBersih.map(v => v >= 0 ? colorLabaPos : colorLabaNeg)
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => ctx.raw.toLocaleString('id-ID')
                            }
                        }
                    },
                    scales: {
                        y: {
                            ticks: { callback: v => v.toLocaleString('id-ID') }
                        }
                    }
                }
            });
        })();
    </script>
    @endif
</x-app-layout>
