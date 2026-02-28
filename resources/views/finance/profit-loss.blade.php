<x-app-layout>
    <x-slot name="title">{{ __('Laba Rugi') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">
            {{ __('Laporan Laba Rugi') }}
        </h2>
    </x-slot>

    <div class="max-w-5xl mx-auto">
        <div class="card-modern overflow-hidden mb-6">
            <div class="p-4 border-b border-gray-100">
                <form method="GET" action="{{ route('finance.profit-loss') }}" class="flex flex-wrap gap-4 items-end">
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
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Gudang') }}</label>
                            <select name="warehouse_id" class="rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">{{ __('Semua') }}</option>
                                @foreach ($warehouses as $w)
                                    <option value="{{ $w->id }}" {{ (string) $selectedWarehouseId === (string) $w->id ? 'selected' : '' }}>{{ $w->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Dari Tanggal') }}</label>
                        <input type="date" name="date_from" value="{{ request('date_from', $dateFrom) }}" class="rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Sampai Tanggal') }}</label>
                        <input type="date" name="date_to" value="{{ request('date_to', $dateTo) }}" class="rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            {{ __('Tampilkan') }}
                        </button>
                        <a href="{{ route('finance.profit-loss') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">
                            {{ __('Reset') }}
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="space-y-4">
            <div class="card-modern p-6">
                <h3 class="text-sm font-semibold text-slate-700 mb-3">{{ __('Ringkasan') }}</h3>
                <dl class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                    <div>
                        <dt class="text-slate-500">{{ __('Periode') }}</dt>
                        <dd class="font-semibold text-slate-800">{{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">{{ __('Laba Bersih') }}</dt>
                        <dd class="font-semibold {{ $netProfit >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                            {{ number_format($netProfit, 0, ',', '.') }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">{{ __('Total Biaya (Barang / Tukar Tambah)') }}</dt>
                        <dd class="font-semibold text-indigo-600">{{ number_format($totalTradeIn ?? 0, 0, ',', '.') }}</dd>
                    </div>
                </dl>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="card-modern p-6">
                    <h3 class="text-sm font-semibold text-slate-700 mb-3">{{ __('Penjualan Barang') }}</h3>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-slate-600">{{ __('Total Penjualan') }}</dt>
                            <dd class="font-semibold text-slate-800">{{ number_format($totalSales, 0, ',', '.') }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-600">{{ __('Total HPP') }}</dt>
                            <dd class="font-semibold text-red-600">-{{ number_format($totalSalesHpp, 0, ',', '.') }}</dd>
                        </div>
                        <div class="flex justify-between border-t border-slate-200 pt-2 mt-1">
                            <dt class="text-slate-700 font-semibold">{{ __('Laba Kotor Penjualan') }}</dt>
                            <dd class="font-semibold {{ $totalSalesProfit >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                                {{ number_format($totalSalesProfit, 0, ',', '.') }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <div class="card-modern p-6">
                    <h3 class="text-sm font-semibold text-slate-700 mb-3">{{ __('Service Laptop') }}</h3>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-slate-600">{{ __('Total Pendapatan Service') }}</dt>
                            <dd class="font-semibold text-slate-800">{{ number_format($totalServiceRevenue, 0, ',', '.') }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-600">{{ __('Total Biaya Service') }}</dt>
                            <dd class="font-semibold text-red-600">-{{ number_format($totalServiceCost, 0, ',', '.') }}</dd>
                        </div>
                        <div class="flex justify-between border-t border-slate-200 pt-2 mt-1">
                            <dt class="text-slate-700 font-semibold">{{ __('Laba Kotor Service') }}</dt>
                            <dd class="font-semibold {{ $totalServiceProfit >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                                {{ number_format($totalServiceProfit, 0, ',', '.') }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="card-modern p-6">
                    <h3 class="text-sm font-semibold text-slate-700 mb-3">{{ __('Penyewaan Laptop') }}</h3>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-600">{{ __('Total Pendapatan Sewa') }}</span>
                        <span class="font-semibold text-emerald-600">+{{ number_format($totalRentalIncome ?? 0, 0, ',', '.') }}</span>
                    </div>
                </div>
                <div class="card-modern p-6">
                    <h3 class="text-sm font-semibold text-slate-700 mb-3">{{ __('Pemasukan Lainnya') }}</h3>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-600">{{ __('Total Pemasukan Lainnya') }}</span>
                        <span class="font-semibold text-emerald-600">+{{ number_format($totalOtherIncome, 0, ',', '.') }}</span>
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                <div class="card-modern p-6">
                    <h3 class="text-sm font-semibold text-slate-700 mb-3">{{ __('Pengeluaran (Semua Jenis)') }}</h3>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-600">{{ __('Total Pengeluaran') }}</span>
                        <span class="font-semibold text-red-600">-{{ number_format($totalExpense, 0, ',', '.') }}</span>
                    </div>
                </div>

                <div class="card-modern p-6">
                    <h3 class="text-sm font-semibold text-slate-700 mb-3">{{ __('Rincian Pengeluaran') }}</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead>
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Tanggal') }}</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Jenis Pengeluaran') }}</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Deskripsi') }}</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Total') }}</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                            @forelse ($expenseDetails as $row)
                                <tr>
                                    <td class="px-4 py-2">{{ $row->transaction_date?->format('d/m/Y') ?? '-' }}</td>
                                    <td class="px-4 py-2">{{ $row->expenseCategory?->name ?? '-' }}</td>
                                    <td class="px-4 py-2">{{ $row->description ?? '-' }}</td>
                                    <td class="px-4 py-2 text-right text-red-600">-{{ number_format($row->amount, 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-slate-500">{{ __('Tidak ada data pengeluaran untuk periode ini.') }}</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

