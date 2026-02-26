<x-app-layout>
    <x-slot name="title">{{ __('Monitor Kas') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">
            {{ __('Monitoring Kas') }}
        </h2>
    </x-slot>

    <div class="max-w-6xl mx-auto">
        <div class="card-modern overflow-hidden mb-6">
            <div class="p-4 border-b border-gray-100">
                <form method="GET" action="{{ route('finance.cash-monitoring') }}" class="flex flex-wrap gap-4 items-end">
                    @if (auth()->user()->isSuperAdmin())
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Cabang') }}</label>
                            <select name="branch_id" class="rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">{{ __('Semua') }}</option>
                                @foreach ($branches as $b)
                                    <option value="{{ $b->id }}" {{ (string) $selectedBranchId === (string) $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Dari Tanggal') }}</label>
                        <input type="date" name="date_from" value="{{ request('date_from', $dateFrom ?? '') }}" class="rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Sampai Tanggal') }}</label>
                        <input type="date" name="date_to" value="{{ request('date_to', $dateTo ?? '') }}" class="rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            {{ __('Tampilkan') }}
                        </button>
                        <a href="{{ route('finance.cash-monitoring') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">
                            {{ __('Reset') }}
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="space-y-6">
            @php
                $displayBranches = $selectedBranchId
                    ? $branches->where('id', $selectedBranchId)
                    : $branches;
            @endphp

            @php
                $cardColors = [
                    ['bg' => 'bg-blue-500/15', 'text' => 'text-blue-800', 'bold' => 'text-blue-900'],
                    ['bg' => 'bg-emerald-500/15', 'text' => 'text-emerald-800', 'bold' => 'text-emerald-900'],
                    ['bg' => 'bg-amber-500/20', 'text' => 'text-amber-800', 'bold' => 'text-amber-900'],
                    ['bg' => 'bg-indigo-500/15', 'text' => 'text-indigo-800', 'bold' => 'text-indigo-900'],
                    ['bg' => 'bg-teal-500/15', 'text' => 'text-teal-800', 'bold' => 'text-teal-900'],
                    ['bg' => 'bg-violet-500/15', 'text' => 'text-violet-800', 'bold' => 'text-violet-900'],
                    ['bg' => 'bg-rose-500/15', 'text' => 'text-rose-800', 'bold' => 'text-rose-900'],
                    ['bg' => 'bg-cyan-500/15', 'text' => 'text-cyan-800', 'bold' => 'text-cyan-900'],
                    ['bg' => 'bg-slate-400/20', 'text' => 'text-slate-700', 'bold' => 'text-slate-900'],
                ];
                $bankColorMap = [
                    'Tunai' => 2,
                    'BNI' => 0,
                    'BRI' => 1,
                    'BCA' => 3,
                    'Mandiri' => 4,
                    'CIMB' => 5,
                    'BTN' => 6,
                    'Permata' => 7,
                ];
                $getColorForKey = function ($key) use ($cardColors, $bankColorMap) {
                    if (isset($bankColorMap[$key])) {
                        return $cardColors[$bankColorMap[$key]];
                    }
                    $bank = explode('|', $key, 2)[0] ?? '';
                    if (isset($bankColorMap[$bank])) {
                        return $cardColors[$bankColorMap[$bank]];
                    }
                    $idx = abs(crc32($key)) % count($cardColors);
                    return $cardColors[$idx];
                };
            @endphp

            @forelse ($displayBranches as $branch)
                @php
                    $totals = $branchTotals[$branch->id] ?? [];
                    $expense = $branchExpense[$branch->id] ?? 0;
                    $totalPemasukan = array_sum($totals);
                @endphp
                <div class="card-modern overflow-hidden">
                    <div class="p-4 bg-slate-50 border-b border-slate-100">
                        <h3 class="font-semibold text-slate-800">{{ $branch->name }}</h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5 mb-6">
                            @forelse ($kasKeys as $key)
                                @php
                                    $amount = $totals[$key] ?? 0;
                                    $info = $kasLabels[$key] ?? ['label' => $key, 'subtitle' => null];
                                    $color = $getColorForKey($key);
                                @endphp
                                <a href="{{ route('finance.cash-monitoring.detail', array_filter(['branch_id' => $branch->id, 'kas_key' => $key, 'date_from' => request('date_from'), 'date_to' => request('date_to')])) }}" class="block rounded-2xl {{ $color['bg'] }} p-5 min-h-[120px] hover:opacity-90 hover:scale-[1.02] transition-all duration-200 shadow-sm">
                                    <div class="flex justify-between items-start h-full">
                                        <div class="flex-1">
                                            <p class="text-sm font-semibold {{ $color['text'] }} mb-1">{{ $info['label'] }}</p>
                                            @if ($info['subtitle'])
                                                <p class="text-xs {{ $color['text'] }} opacity-75 mb-2">{{ $info['subtitle'] }}</p>
                                            @endif
                                            <p class="text-xl font-bold {{ $color['bold'] }}">{{ number_format($amount, 0, ',', '.') }}</p>
                                        </div>
                                        <span class="text-xs {{ $color['text'] }} opacity-70 shrink-0">Detail →</span>
                                    </div>
                                </a>
                            @empty
                                <div class="col-span-full text-center py-4 text-slate-500">
                                    {{ __('Belum ada metode pembayaran. Tambahkan di Data Master > Metode Pembayaran.') }}
                                </div>
                            @endforelse
                        </div>

                        <div class="flex flex-wrap gap-6 pt-4 border-t border-slate-200">
                            <div>
                                <span class="text-sm text-slate-500">{{ __('Total Pemasukan') }}</span>
                                <p class="font-semibold text-emerald-600">{{ number_format($totalPemasukan, 0, ',', '.') }}</p>
                            </div>
                            <div>
                                <span class="text-sm text-slate-500">{{ __('Total Pengeluaran') }}</span>
                                <p class="font-semibold text-red-600">-{{ number_format($expense, 0, ',', '.') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="card-modern p-8 text-center">
                    <p class="text-slate-500">{{ __('Tidak ada data untuk ditampilkan. Pilih cabang atau pastikan ada transaksi.') }}</p>
                </div>
            @endforelse
        </div>

        @if ($selectedBranchId && $branches->isEmpty())
            <div class="card-modern p-8 text-center">
                <p class="text-slate-500">{{ __('Cabang tidak ditemukan.') }}</p>
            </div>
        @endif
    </div>
</x-app-layout>
