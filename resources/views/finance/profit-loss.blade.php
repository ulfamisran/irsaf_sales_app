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
                    @if(($canFilterLocation ?? false) || ($filterLocked ?? false))
                        @php
                            $branchSelectDisabled = $filterLocked ?? false;
                            $warehouseSelectDisabled = $filterLocked ?? false;
                            $selectedBranchId = $filterLocked && ($lockedBranchId ?? null) ? $lockedBranchId : request('branch_id');
                            $selectedWarehouseId = $filterLocked && ($lockedWarehouseId ?? null) ? $lockedWarehouseId : request('warehouse_id');
                            $locType = request('location_type') ?? '';
                            if ($filterLocked) {
                                $locType = ($lockedWarehouseId ?? null) ? 'warehouse' : (($lockedBranchId ?? null) ? 'branch' : '');
                            }
                            if ($locType === '' && ($selectedBranchId || $selectedWarehouseId)) {
                                $locType = $selectedWarehouseId ? 'warehouse' : 'branch';
                            }
                        @endphp
                        <div class="min-w-[180px]">
                            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Tipe Lokasi') }}</label>
                            <select name="location_type" id="pl_location_type" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm {{ $branchSelectDisabled ? 'bg-slate-100 cursor-not-allowed' : '' }}" {{ $branchSelectDisabled ? 'disabled' : '' }}>
                                <option value="" {{ $locType === '' ? 'selected' : '' }}>{{ __('Semua') }}</option>
                                <option value="branch" {{ $locType === 'branch' ? 'selected' : '' }}>{{ __('Cabang') }}</option>
                                <option value="warehouse" {{ $locType === 'warehouse' ? 'selected' : '' }}>{{ __('Gudang') }}</option>
                            </select>
                            @if($branchSelectDisabled)
                                <input type="hidden" name="location_type" value="{{ $locType }}">
                            @endif
                        </div>
                        <div id="pl_location_wrapper" class="min-w-[180px]" style="{{ $locType === '' ? 'display:none' : '' }}">
                            <label class="block text-sm font-medium text-slate-700 mb-1" id="pl_location_label">{{ $locType === 'warehouse' ? __('Gudang') : __('Cabang') }}</label>
                            <div class="filter-pl-warehouse" style="{{ $locType !== 'warehouse' ? 'display:none' : '' }}">
                                <select name="warehouse_id" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm {{ $warehouseSelectDisabled ? 'bg-slate-100 cursor-not-allowed' : '' }}" {{ $warehouseSelectDisabled ? 'disabled' : '' }}>
                                    <option value="">{{ __('Semua') }}</option>
                                    @foreach ($warehouses ?? [] as $w)
                                        <option value="{{ $w->id }}" {{ (string)$selectedWarehouseId === (string)$w->id ? 'selected' : '' }}>{{ $w->name }}</option>
                                    @endforeach
                                </select>
                                @if($warehouseSelectDisabled && ($lockedWarehouseId ?? null))
                                    <input type="hidden" name="warehouse_id" value="{{ $lockedWarehouseId }}">
                                @endif
                            </div>
                            <div class="filter-pl-branch" style="{{ $locType !== 'branch' ? 'display:none' : '' }}">
                                <select name="branch_id" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm {{ $branchSelectDisabled ? 'bg-slate-100 cursor-not-allowed' : '' }}" {{ $branchSelectDisabled ? 'disabled' : '' }}>
                                    <option value="">{{ __('Semua') }}</option>
                                    @foreach ($branches ?? [] as $b)
                                        <option value="{{ $b->id }}" {{ (string)$selectedBranchId === (string)$b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                                    @endforeach
                                </select>
                                @if($branchSelectDisabled && ($lockedBranchId ?? null))
                                    <input type="hidden" name="branch_id" value="{{ $lockedBranchId }}">
                                @endif
                            </div>
                        </div>
                    @endif
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Dari Tanggal') }}</label>
                        <input type="date" name="date_from" value="{{ request('date_from') }}" class="rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Sampai Tanggal') }}</label>
                        <input type="date" name="date_to" value="{{ request('date_to') }}" class="rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Tampilan') }}</label>
                        <select name="pov" class="rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="card" {{ request('pov', 'card') === 'card' ? 'selected' : '' }}>{{ __('Card') }}</option>
                            <option value="table" {{ request('pov') === 'table' ? 'selected' : '' }}>{{ __('Tabel') }}</option>
                        </select>
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
            @if(($pov ?? 'card') === 'table')
            {{-- POV Tabel Detail --}}
            <div class="card-modern overflow-hidden">
                <div class="p-4 border-b border-gray-100 flex items-center justify-between gap-4 flex-wrap">
                    <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" id="toggle-detail-transaksi" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm font-medium text-slate-700">{{ __('Tampilkan detail transaksi') }}</span>
                    </label>
                </div>
                <div id="profit-loss-table-wrapper" class="overflow-x-auto overflow-y-auto transition-all duration-200" style="max-height: 70vh;">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Keterangan') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Total Transaksi') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Total Modal') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Laba') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            {{-- 1. Penjualan --}}
                            <tr class="bg-indigo-50/50">
                                <td colspan="4" class="px-4 py-2 font-semibold text-slate-800">{{ __('Laba Bersih Penjualan') }}</td>
                            </tr>
                            @forelse($sales ?? [] as $sale)
                            @php
                                $saleTotal = (float) $sale->total_paid ?: (float) $sale->total;
                                $saleHpp = (float) $sale->total_hpp;
                                $saleProfit = $saleTotal - $saleHpp;
                            @endphp
                            <tr class="profit-loss-detail-row">
                                <td class="px-4 py-2 pl-6 text-slate-600">{{ $sale->invoice_number }} ({{ $sale->sale_date?->format('d/m/Y') }})</td>
                                <td class="px-4 py-2 text-right text-slate-800">{{ number_format($saleTotal, 0, ',', '.') }}</td>
                                <td class="px-4 py-2 text-right text-red-600">{{ number_format($saleHpp, 0, ',', '.') }}</td>
                                <td class="px-4 py-2 text-right font-medium {{ $saleProfit >= 0 ? 'text-emerald-600' : 'text-red-600' }}">{{ number_format($saleProfit, 0, ',', '.') }}</td>
                            </tr>
                            @empty
                            <tr class="profit-loss-detail-row">
                                <td class="px-4 py-2 pl-6 text-slate-400 italic" colspan="4">{{ __('Tidak ada data penjualan') }}</td>
                            </tr>
                            @endforelse
                            <tr class="border-t border-slate-200 profit-loss-subtotal">
                                <td class="px-4 py-2 pl-6 text-slate-700 font-medium">{{ __('Subtotal Penjualan') }}</td>
                                <td class="px-4 py-2 text-right font-medium text-slate-800">{{ number_format($totalSales, 0, ',', '.') }}</td>
                                <td class="px-4 py-2 text-right font-medium text-red-600">{{ number_format($totalSalesHpp, 0, ',', '.') }}</td>
                                <td class="px-4 py-2 text-right font-semibold {{ $totalSalesProfit >= 0 ? 'text-emerald-600' : 'text-red-600' }}">{{ number_format($totalSalesProfit, 0, ',', '.') }}</td>
                            </tr>

                            {{-- 2. Penyewaan --}}
                            <tr class="bg-indigo-50/50 border-t-2 border-slate-200">
                                <td colspan="4" class="px-4 py-2 font-semibold text-slate-800">{{ __('Laba Bersih Penyewaan') }}</td>
                            </tr>
                            @forelse($rentals ?? [] as $rental)
                            @php $rentalTotal = (float) $rental->total; @endphp
                            <tr class="profit-loss-detail-row">
                                <td class="px-4 py-2 pl-6 text-slate-600">{{ $rental->invoice_number }} ({{ $rental->pickup_date?->format('d/m/Y') }})</td>
                                <td class="px-4 py-2 text-right text-slate-800">{{ number_format($rentalTotal, 0, ',', '.') }}</td>
                                <td class="px-4 py-2 text-right">-</td>
                                <td class="px-4 py-2 text-right font-medium text-emerald-600">{{ number_format($rentalTotal, 0, ',', '.') }}</td>
                            </tr>
                            @empty
                            <tr class="profit-loss-detail-row">
                                <td class="px-4 py-2 pl-6 text-slate-400 italic" colspan="4">{{ __('Tidak ada data penyewaan') }}</td>
                            </tr>
                            @endforelse
                            <tr class="border-t border-slate-200 profit-loss-subtotal">
                                <td class="px-4 py-2 pl-6 text-slate-700 font-medium">{{ __('Subtotal Penyewaan') }}</td>
                                <td class="px-4 py-2 text-right font-medium text-slate-800">{{ number_format($totalRentalIncome ?? 0, 0, ',', '.') }}</td>
                                <td class="px-4 py-2 text-right">-</td>
                                <td class="px-4 py-2 text-right font-semibold text-emerald-600">{{ number_format($totalRentalIncome ?? 0, 0, ',', '.') }}</td>
                            </tr>

                            {{-- 3. Service --}}
                            <tr class="bg-indigo-50/50 border-t-2 border-slate-200">
                                <td colspan="4" class="px-4 py-2 font-semibold text-slate-800">{{ __('Laba Bersih Service') }}</td>
                            </tr>
                            @forelse($services ?? [] as $service)
                            @php
                                $svcTotal = (float) $service->total_service_price;
                                $svcMaterial = (float) $service->materials_total_price;
                                $svcProfit = $svcTotal - $svcMaterial;
                            @endphp
                            <tr class="profit-loss-detail-row">
                                <td class="px-4 py-2 pl-6 text-slate-600">{{ $service->invoice_number }} ({{ ($service->exit_date ?? $service->entry_date)?->format('d/m/Y') }})</td>
                                <td class="px-4 py-2 text-right text-slate-800">{{ number_format($svcTotal, 0, ',', '.') }}</td>
                                <td class="px-4 py-2 text-right text-red-600">{{ number_format($svcMaterial, 0, ',', '.') }}</td>
                                <td class="px-4 py-2 text-right font-medium {{ $svcProfit >= 0 ? 'text-emerald-600' : 'text-red-600' }}">{{ number_format($svcProfit, 0, ',', '.') }}</td>
                            </tr>
                            @empty
                            <tr class="profit-loss-detail-row">
                                <td class="px-4 py-2 pl-6 text-slate-400 italic" colspan="4">{{ __('Tidak ada data service') }}</td>
                            </tr>
                            @endforelse
                            <tr class="border-t border-slate-200 profit-loss-subtotal">
                                <td class="px-4 py-2 pl-6 text-slate-700 font-medium">{{ __('Subtotal Service') }}</td>
                                <td class="px-4 py-2 text-right font-medium text-slate-800">{{ number_format($totalServiceRevenue, 0, ',', '.') }}</td>
                                <td class="px-4 py-2 text-right font-medium text-red-600">{{ number_format($totalServiceMaterialCost ?? 0, 0, ',', '.') }}</td>
                                <td class="px-4 py-2 text-right font-semibold {{ $totalServiceProfit >= 0 ? 'text-emerald-600' : 'text-red-600' }}">{{ number_format($totalServiceProfit, 0, ',', '.') }}</td>
                            </tr>

                            {{-- 4. Pemasukan Distribusi --}}
                            <tr class="bg-indigo-50/50 border-t-2 border-slate-200">
                                <td colspan="4" class="px-4 py-2 font-semibold text-slate-800">{{ __('Pemasukan Distribusi') }}</td>
                            </tr>
                            @forelse($incomeDistributionDetails ?? [] as $inc)
                            <tr class="profit-loss-detail-row">
                                <td class="px-4 py-2 pl-6 text-slate-600">{{ $inc->description }} ({{ $inc->transaction_date?->format('d/m/Y') }})</td>
                                <td class="px-4 py-2 text-right font-medium text-emerald-600" colspan="3">{{ number_format($inc->amount, 0, ',', '.') }}</td>
                            </tr>
                            @empty
                            <tr class="profit-loss-detail-row">
                                <td class="px-4 py-2 pl-6 text-slate-400 italic" colspan="4">{{ __('Tidak ada pemasukan distribusi') }}</td>
                            </tr>
                            @endforelse
                            <tr class="border-t border-slate-200 profit-loss-subtotal">
                                <td class="px-4 py-2 pl-6 text-slate-700 font-medium">{{ __('Subtotal Pemasukan Distribusi') }}</td>
                                <td class="px-4 py-2 text-right font-medium text-emerald-600" colspan="3">{{ number_format($totalDistributionIncome ?? 0, 0, ',', '.') }}</td>
                            </tr>

                            {{-- 5. Pemasukan Lainnya --}}
                            <tr class="bg-indigo-50/50 border-t-2 border-slate-200">
                                <td colspan="4" class="px-4 py-2 font-semibold text-slate-800">{{ __('Pemasukan Lainnya') }}</td>
                            </tr>
                            @forelse($incomeOtherDetails ?? [] as $inc)
                            <tr class="profit-loss-detail-row">
                                <td class="px-4 py-2 pl-6 text-slate-600">{{ $inc->description }} ({{ $inc->transaction_date?->format('d/m/Y') }})</td>
                                <td class="px-4 py-2 text-right font-medium text-emerald-600" colspan="3">{{ number_format($inc->amount, 0, ',', '.') }}</td>
                            </tr>
                            @empty
                            <tr class="profit-loss-detail-row">
                                <td class="px-4 py-2 pl-6 text-slate-400 italic" colspan="4">{{ __('Tidak ada pemasukan lainnya') }}</td>
                            </tr>
                            @endforelse
                            <tr class="border-t border-slate-200 profit-loss-subtotal">
                                <td class="px-4 py-2 pl-6 text-slate-700 font-medium">{{ __('Subtotal Pemasukan Lainnya') }}</td>
                                <td class="px-4 py-2 text-right font-medium text-emerald-600" colspan="3">{{ number_format($totalOtherIncomeOnly ?? 0, 0, ',', '.') }}</td>
                            </tr>

                            {{-- 6. Pengeluaran --}}
                            <tr class="bg-red-50/50 border-t-2 border-slate-200">
                                <td colspan="4" class="px-4 py-2 font-semibold text-slate-800">{{ __('Pengeluaran') }}</td>
                            </tr>
                            @forelse($expenseDetails ?? [] as $exp)
                            <tr class="profit-loss-detail-row">
                                <td class="px-4 py-2 pl-6 text-slate-600">{{ $exp->description }} ({{ $exp->transaction_date?->format('d/m/Y') }})</td>
                                <td class="px-4 py-2 text-right">-</td>
                                <td class="px-4 py-2 text-right">-</td>
                                <td class="px-4 py-2 text-right font-medium text-red-600">-{{ number_format($exp->amount, 0, ',', '.') }}</td>
                            </tr>
                            @empty
                            <tr class="profit-loss-detail-row">
                                <td class="px-4 py-2 pl-6 text-slate-400 italic" colspan="4">{{ __('Tidak ada pengeluaran') }}</td>
                            </tr>
                            @endforelse
                            <tr class="border-t border-slate-200 profit-loss-subtotal">
                                <td class="px-4 py-2 pl-6 text-slate-700 font-medium">{{ __('Subtotal Pengeluaran') }}</td>
                                <td class="px-4 py-2 text-right">-</td>
                                <td class="px-4 py-2 text-right">-</td>
                                <td class="px-4 py-2 text-right font-medium text-red-600">-{{ number_format($totalExpense, 0, ',', '.') }}</td>
                            </tr>

                            {{-- 7. Beban Barang Rusak Cadangan --}}
                            <tr class="bg-red-50/50 border-t-2 border-slate-200">
                                <td colspan="4" class="px-4 py-2 font-semibold text-slate-800">{{ __('Beban Barang Rusak Cadangan') }}</td>
                            </tr>
                            @forelse($damagedGoodsDetails ?? [] as $dg)
                            <tr class="profit-loss-detail-row">
                                <td class="px-4 py-2 pl-6 text-slate-600">{{ $dg->serial_number }} - {{ $dg->productUnit?->product?->brand ?? '-' }} {{ $dg->productUnit?->product?->series ?? '' }} ({{ $dg->recorded_date?->format('d/m/Y') }})</td>
                                <td class="px-4 py-2 text-right">-</td>
                                <td class="px-4 py-2 text-right text-red-600">{{ number_format($dg->harga_hpp, 0, ',', '.') }}</td>
                                <td class="px-4 py-2 text-right font-medium text-red-600">-{{ number_format($dg->harga_hpp, 0, ',', '.') }}</td>
                            </tr>
                            @empty
                            <tr class="profit-loss-detail-row">
                                <td class="px-4 py-2 pl-6 text-slate-400 italic" colspan="4">{{ __('Tidak ada beban barang rusak') }}</td>
                            </tr>
                            @endforelse
                            <tr class="border-t border-slate-200 profit-loss-subtotal">
                                <td class="px-4 py-2 pl-6 text-slate-700 font-medium">{{ __('Subtotal Beban Barang Rusak Cadangan') }}</td>
                                <td class="px-4 py-2 text-right">-</td>
                                <td class="px-4 py-2 text-right font-medium text-red-600">{{ number_format($totalDamagedGoodsExpense ?? 0, 0, ',', '.') }}</td>
                                <td class="px-4 py-2 text-right font-medium text-red-600">-{{ number_format($totalDamagedGoodsExpense ?? 0, 0, ',', '.') }}</td>
                            </tr>

                            {{-- Laba Keseluruhan --}}
                            <tr class="bg-slate-100 border-t-2 border-slate-300">
                                <td class="px-4 py-3 font-bold text-slate-900" colspan="3">{{ __('Laba Keseluruhan') }}</td>
                                <td class="px-4 py-3 text-right font-bold {{ $netProfit >= 0 ? 'text-emerald-600' : 'text-red-600' }}">{{ number_format($netProfit, 0, ',', '.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-2 bg-slate-50 border-t text-xs text-slate-500">
                    {{ __('Periode') }}: {{ $dateFrom }} s/d {{ $dateTo }}
                    @if($locationLabel)
                        &middot; {{ $locationLabel }}
                    @endif
                </div>
            </div>
            @else
            {{-- POV Card --}}
            <div class="card-modern p-6">
                <h3 class="text-sm font-semibold text-slate-700 mb-3">{{ __('Ringkasan') }}</h3>
                <dl class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                    <div>
                        <dt class="text-slate-500">{{ __('Periode') }}</dt>
                        <dd class="font-semibold text-slate-800">{{ $dateFrom }} s/d {{ $dateTo }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">{{ __('Laba Keseluruhan') }}</dt>
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
                            <dt class="text-slate-600">{{ __('Biaya Material yang dibeli') }}</dt>
                            <dd class="font-semibold text-red-600">-{{ number_format($totalServiceMaterialCost ?? 0, 0, ',', '.') }}</dd>
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

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="card-modern p-6">
                    <h3 class="text-sm font-semibold text-slate-700 mb-3">{{ __('Penyewaan Laptop') }}</h3>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-600">{{ __('Total Pendapatan Sewa') }}</span>
                        <span class="font-semibold text-emerald-600">+{{ number_format($totalRentalIncome ?? 0, 0, ',', '.') }}</span>
                    </div>
                </div>
                <div class="card-modern p-6">
                    <h3 class="text-sm font-semibold text-slate-700 mb-3">{{ __('Pemasukan Distribusi') }}</h3>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-600">{{ __('Total Pemasukan Distribusi') }}</span>
                        <span class="font-semibold text-emerald-600">+{{ number_format($totalDistributionIncome ?? 0, 0, ',', '.') }}</span>
                    </div>
                </div>
                <div class="card-modern p-6">
                    <h3 class="text-sm font-semibold text-slate-700 mb-3">{{ __('Pemasukan Lainnya') }}</h3>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-600">{{ __('Total Pemasukan Lainnya') }}</span>
                        <span class="font-semibold text-emerald-600">+{{ number_format($totalOtherIncomeOnly ?? 0, 0, ',', '.') }}</span>
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="card-modern p-6">
                        <h3 class="text-sm font-semibold text-slate-700 mb-3">{{ __('Pengeluaran (Semua Jenis)') }}</h3>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-600">{{ __('Total Pengeluaran') }}</span>
                            <span class="font-semibold text-red-600">-{{ number_format($totalExpense, 0, ',', '.') }}</span>
                        </div>
                    </div>
                    <div class="card-modern p-6">
                        <h3 class="text-sm font-semibold text-slate-700 mb-3">{{ __('Beban Barang Rusak Cadangan') }}</h3>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-600">{{ __('Total Beban Barang Rusak') }}</span>
                            <span class="font-semibold text-red-600">-{{ number_format($totalDamagedGoodsExpense ?? 0, 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>

                <div class="card-modern p-6">
                    <h3 class="text-sm font-semibold text-slate-700 mb-3">{{ __('Rincian Beban Barang Rusak Cadangan') }}</h3>
                    <div class="overflow-x-auto mb-4">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead>
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Tanggal') }}</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Serial') }}</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Produk') }}</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Beban HPP') }}</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                            @forelse ($damagedGoodsDetails ?? [] as $dg)
                                <tr>
                                    <td class="px-4 py-2">{{ $dg->recorded_date?->format('d/m/Y') ?? '-' }}</td>
                                    <td class="px-4 py-2">{{ $dg->serial_number ?? '-' }}</td>
                                    <td class="px-4 py-2">{{ ($dg->productUnit?->product?->brand ?? '') . ' ' . ($dg->productUnit?->product?->series ?? '') ?: '-' }}</td>
                                    <td class="px-4 py-2 text-right text-red-600">-{{ number_format($dg->harga_hpp, 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-slate-500">{{ __('Tidak ada beban barang rusak untuk periode ini.') }}</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
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
            @endif
        </div>
    </div>

    @if(($pov ?? 'card') === 'table')
    <script>
        (function() {
            const checkbox = document.getElementById('toggle-detail-transaksi');
            const wrapper = document.getElementById('profit-loss-table-wrapper');
            const detailRows = document.querySelectorAll('.profit-loss-detail-row');
            const defaultShow = localStorage.getItem('profitLossShowDetail') === 'true';

            function toggleDetails(show) {
                detailRows.forEach(row => {
                    row.style.display = show ? '' : 'none';
                });
                wrapper.style.maxHeight = show ? '70vh' : 'none';
                wrapper.style.overflowY = show ? 'auto' : 'visible';
                if (checkbox) checkbox.checked = show;
                localStorage.setItem('profitLossShowDetail', show ? 'true' : 'false');
            }

            if (checkbox) {
                checkbox.checked = defaultShow;
                toggleDetails(defaultShow);
                checkbox.addEventListener('change', () => toggleDetails(checkbox.checked));
            }
        })();
    </script>
    @endif

    @if(($canFilterLocation ?? false) || ($filterLocked ?? false))
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const locType = document.getElementById('pl_location_type');
            const wrapper = document.getElementById('pl_location_wrapper');
            const label = document.getElementById('pl_location_label');
            const whBlock = document.querySelector('.filter-pl-warehouse');
            const brBlock = document.querySelector('.filter-pl-branch');
            const whSelect = whBlock?.querySelector('select[name="warehouse_id"]');
            const brSelect = brBlock?.querySelector('select[name="branch_id"]');
            if (locType && !locType.disabled) {
                function toggle() {
                    const v = locType.value;
                    if (!v) {
                        wrapper.style.display = 'none';
                        if (whSelect) { whSelect.value = ''; whSelect.disabled = true; }
                        if (brSelect) { brSelect.value = ''; brSelect.disabled = true; }
                        return;
                    }
                    wrapper.style.display = '';
                    if (v === 'warehouse') {
                        if (label) label.textContent = '{{ __("Gudang") }}';
                        if (whBlock) whBlock.style.display = '';
                        if (brBlock) brBlock.style.display = 'none';
                        if (whSelect) { whSelect.disabled = false; }
                        if (brSelect) { brSelect.value = ''; brSelect.disabled = true; }
                    } else {
                        if (label) label.textContent = '{{ __("Cabang") }}';
                        if (whBlock) whBlock.style.display = 'none';
                        if (brBlock) brBlock.style.display = '';
                        if (whSelect) { whSelect.value = ''; whSelect.disabled = true; }
                        if (brSelect) { brSelect.disabled = false; }
                    }
                }
                locType.addEventListener('change', toggle);
                toggle();
            }
        });
    </script>
    @endpush
    @endif
</x-app-layout>

