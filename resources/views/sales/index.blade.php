<x-app-layout>
    <x-slot name="title">{{ __('Penjualan') }}</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Penjualan') }}</h2>
            <x-icon-btn-add :href="route('sales.create')" :label="__('Penjualan Baru')" />
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto">
        @if (session('success'))
            <div class="mb-6 rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-emerald-800 flex items-center gap-3">
                <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="mb-6 rounded-xl bg-red-50 border border-red-200 p-4 text-red-800 flex items-center gap-3">
                <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                {{ session('error') }}
            </div>
        @endif

        <div class="card-modern overflow-hidden mb-6">
            <div class="p-4 border-b border-gray-100">
                <form method="GET" action="{{ route('sales.index') }}" class="flex flex-wrap gap-3 items-end">
                    <div class="min-w-[180px]">
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Cabang') }}</label>
                        <select name="branch_id" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('Semua') }}</option>
                            @foreach ($branches as $b)
                                <option value="{{ $b->id }}" {{ request('branch_id') == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                            @endforeach
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
                        <a href="{{ route('sales.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">
                            {{ __('Reset') }}
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card-modern overflow-hidden">
            <div class="p-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Invoice') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Cabang') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Pelanggan') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Tanggal') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Status') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Pembayaran') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('User') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Total') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($sales as $sale)
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-4 py-3">{{ $sale->invoice_number }}</td>
                                <td class="px-4 py-3">{{ $sale->branch?->name }}</td>
                                <td class="px-4 py-3">{{ $sale->customer?->name ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $sale->sale_date->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">
                                    @php
                                        $statusClass = match($sale->status) {
                                            'released' => 'bg-emerald-100 text-emerald-800',
                                            'cancel' => 'bg-red-100 text-red-800',
                                            default => 'bg-blue-100 text-blue-800',
                                        };
                                    @endphp
                                    <span class="px-2 py-1 rounded-lg text-xs font-medium {{ $statusClass }}">
                                        {{ $sale->status === 'cancel' ? __('Dibatalkan') : $sale->status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($sale->status === 'released')
                                        @php $paid = (float)$sale->total_paid; @endphp
                                        <span class="px-2 py-1 rounded-lg text-xs font-medium {{ $paid >= (float)$sale->total - 0.02 ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                            {{ $paid >= (float)$sale->total - 0.02 ? __('Lunas') : __('Belum Lunas') }}
                                        </span>
                                    @else
                                        <span class="text-slate-400">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $sale->user?->name }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($sale->total, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        @if ($sale->status === \App\Models\Sale::STATUS_OPEN)
                                            <x-icon-btn-edit :href="route('sales.edit', $sale)" :label="__('Edit')" />
                                        @endif
                                        <x-icon-btn-view :href="route('sales.show', $sale)" />
                                        <a href="{{ route('sales.invoice', $sale) }}" target="_blank" class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-sm font-medium text-slate-700 hover:text-slate-900 transition-colors" title="{{ __('Print Invoice') }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6v-8z"/>
                                            </svg>
                                            {{ __('Invoice') }}
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-12 text-center text-slate-500">{{ __('Tidak ada data penjualan.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="mt-4">{{ $sales->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
