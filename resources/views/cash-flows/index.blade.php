<x-app-layout>
    <x-slot name="title">{{ __('Laporan Arus Kas') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Laporan Arus Kas') }}</h2>
    </x-slot>

    <div class="max-w-7xl mx-auto">
        <div class="card-modern overflow-hidden mb-6">
            <div class="p-4 border-b border-gray-100">
                <form method="GET" action="{{ route('cash-flows.index') }}" class="flex flex-wrap gap-4 items-end">
                    @if (auth()->user()->isSuperAdmin())
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Cabang') }}</label>
                            <select name="branch_id" class="rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">{{ __('Semua') }}</option>
                                @foreach ($branches as $b)
                                    <option value="{{ $b->id }}" {{ request('branch_id') == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Gudang') }}</label>
                            <select name="warehouse_id" class="rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">{{ __('Semua') }}</option>
                                @foreach ($warehouses as $w)
                                    <option value="{{ $w->id }}" {{ request('warehouse_id') == $w->id ? 'selected' : '' }}>{{ $w->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Tipe') }}</label>
                        <select name="type" class="rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('Semua') }}</option>
                            <option value="IN" {{ request('type') == 'IN' ? 'selected' : '' }}>{{ __('Masuk') }}</option>
                            <option value="OUT" {{ request('type') == 'OUT' ? 'selected' : '' }}>{{ __('Keluar') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Dari Tanggal') }}</label>
                        <input type="date" name="date_from" value="{{ request('date_from') }}" class="rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Sampai Tanggal') }}</label>
                        <input type="date" name="date_to" value="{{ request('date_to') }}" class="rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            {{ __('Filter') }}
                        </button>
                        <a href="{{ route('cash-flows.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">
                            {{ __('Reset') }}
                        </a>
                        <a href="{{ route('cash-flows.out.create') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-red-600 text-white text-sm font-medium hover:bg-red-700">
                            {{ __('Catat Dana Keluar') }}
                        </a>
                    </div>
                </form>
            </div>
        </div>

        @php
            $totalCashIn = (float) ($summary['IN'] ?? 0);
            $totalTradeInValue = (float) ($totalTradeIn ?? 0);
            $totalInCombined = $totalCashIn + $totalTradeInValue;
        @endphp

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div class="card-modern p-6 flex flex-col gap-4">
                <div class="flex items-center gap-4">
                <span class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-emerald-100 text-emerald-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v2m14 0h2a2 2 0 012 2v6a2 2 0 01-2 2H7a2 2 0 01-2-2v-2m12-6H5m6 3a2 2 0 100 4 2 2 0 000-4z"/>
                    </svg>
                </span>
                <div>
                    <p class="text-sm text-slate-600">{{ __('Total Dana Masuk (Gabungan)') }}</p>
                    <p class="text-xl font-semibold text-emerald-600">{{ number_format($totalInCombined, 0, ',', '.') }}</p>
                    <p class="text-xs text-slate-500 mt-1">{{ __('Gabungan dana masuk + nilai barang tukar tambah') }}</p>
                </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="rounded-lg border border-emerald-100 bg-emerald-50/40 p-3">
                        <p class="text-xs text-emerald-700">{{ __('Dana Masuk (Uang)') }}</p>
                        <p class="text-lg font-semibold text-emerald-700">{{ number_format($totalCashIn, 0, ',', '.') }}</p>
                    </div>
                    <div class="rounded-lg border border-indigo-100 bg-indigo-50/40 p-3">
                        <p class="text-xs text-indigo-700">{{ __('Dana Masuk (Barang / Tukar Tambah)') }}</p>
                        <p class="text-lg font-semibold text-indigo-700">{{ number_format($totalTradeInValue, 0, ',', '.') }}</p>
                    </div>
                </div>
            </div>
            <div class="card-modern p-6 flex items-center gap-4 self-start h-fit">
                <span class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-red-100 text-red-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v2m14 0h2a2 2 0 012 2v6a2 2 0 01-2 2H7a2 2 0 01-2-2v-2m12-6H5m8 3h-2"/>
                    </svg>
                </span>
                <div>
                    <p class="text-sm text-slate-600">{{ __('Total Dana Keluar') }}</p>
                    <p class="text-xl font-semibold text-red-600">{{ number_format($summary['OUT'] ?? 0, 0, ',', '.') }}</p>
                </div>
            </div>
        </div>


        <div class="card-modern overflow-hidden">
            <div class="p-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Tanggal') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Tipe') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Lokasi') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Jenis Pengeluaran') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Deskripsi') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Referensi') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Jumlah') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('User') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($cashFlows as $cf)
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-4 py-3">{{ $cf->transaction_date->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 rounded-lg text-xs font-medium {{ $cf->type === 'IN' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800' }}">
                                        {{ $cf->type }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($cf->warehouse_id)
                                        {{ __('Gudang') }}: {{ $cf->warehouse?->name ?? '-' }}
                                    @else
                                        {{ __('Cabang') }}: {{ $cf->branch?->name ?? '-' }}
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if ($cf->type === 'OUT')
                                        {{ $cf->expenseCategory?->name ?? '-' }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $cf->description }}</td>
                                <td class="px-4 py-3">{{ $cf->reference_type }} #{{ $cf->reference_id }}</td>
                                <td class="px-4 py-3 text-right font-medium {{ $cf->type === 'IN' ? 'text-emerald-600' : 'text-red-600' }}">
                                    {{ $cf->type === 'IN' ? '+' : '-' }}{{ number_format($cf->amount, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3">{{ $cf->user?->name }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-12 text-center text-slate-500">{{ __('Tidak ada data.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="mt-4">{{ $cashFlows->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
