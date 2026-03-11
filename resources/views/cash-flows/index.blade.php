<x-app-layout>
    <x-slot name="title">{{ __('Laporan Arus Kas') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Laporan Arus Kas') }}</h2>
    </x-slot>

    <div class="max-w-7xl mx-auto">
        <div class="card-modern overflow-hidden mb-6">
            <div class="p-4 border-b border-gray-100">
                <form method="GET" action="{{ route('cash-flows.index') }}" class="flex flex-wrap gap-4 items-end">
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
                            <select name="location_type" id="cf_location_type" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm {{ $branchSelectDisabled ? 'bg-slate-100 cursor-not-allowed' : '' }}" {{ $branchSelectDisabled ? 'disabled' : '' }}>
                                <option value="" {{ $locType === '' ? 'selected' : '' }}>{{ __('Semua') }}</option>
                                <option value="branch" {{ $locType === 'branch' ? 'selected' : '' }}>{{ __('Cabang') }}</option>
                                <option value="warehouse" {{ $locType === 'warehouse' ? 'selected' : '' }}>{{ __('Gudang') }}</option>
                            </select>
                            @if($branchSelectDisabled)
                                <input type="hidden" name="location_type" value="{{ $locType }}">
                            @endif
                        </div>
                        <div id="cf_location_wrapper" class="min-w-[180px]" style="{{ $locType === '' ? 'display:none' : '' }}">
                            <label class="block text-sm font-medium text-slate-700 mb-1" id="cf_location_label">{{ $locType === 'warehouse' ? __('Gudang') : __('Cabang') }}</label>
                            <div class="filter-cf-warehouse" style="{{ $locType !== 'warehouse' ? 'display:none' : '' }}">
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
                            <div class="filter-cf-branch" style="{{ $locType !== 'branch' ? 'display:none' : '' }}">
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
                    @if(count($paymentMethods ?? []) > 0)
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Metode Pembayaran') }}</label>
                            <select name="payment_method_id" class="rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">{{ __('Semua') }}</option>
                                @foreach ($paymentMethods ?? [] as $pm)
                                    <option value="{{ $pm->id }}" {{ request('payment_method_id') == $pm->id ? 'selected' : '' }}>
                                        {{ $pm->display_label }}
                                    </option>
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
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Urutan') }}</label>
                        <select name="order" class="rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="bawah_ke_atas" {{ request('order', 'bawah_ke_atas') === 'bawah_ke_atas' ? 'selected' : '' }}>{{ __('Bawah ke atas') }} ({{ __('Saldo awal di bawah, saldo akhir di atas') }})</option>
                            <option value="atas_ke_bawah" {{ request('order') === 'atas_ke_bawah' ? 'selected' : '' }}>{{ __('Atas ke bawah') }} ({{ __('Saldo awal di atas, saldo akhir di bawah') }})</option>
                        </select>
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
                <table class="min-w-full divide-y divide-gray-200 text-xs">
                    <thead>
                        <tr>
                            <th class="px-2 py-2 text-left text-[10px] font-medium text-slate-500 uppercase">{{ __('Tanggal') }}</th>
                            <th class="px-2 py-2 text-left text-[10px] font-medium text-slate-500 uppercase">{{ __('Tipe') }}</th>
                            <th class="px-2 py-2 text-left text-[10px] font-medium text-slate-500 uppercase">{{ __('Lokasi') }}</th>
                            <th class="px-2 py-2 text-left text-[10px] font-medium text-slate-500 uppercase">{{ __('Sumber Dana') }}</th>
                            <th class="px-2 py-2 text-left text-[10px] font-medium text-slate-500 uppercase">{{ __('Kategori') }}</th>
                            <th class="px-2 py-2 text-left text-[10px] font-medium text-slate-500 uppercase">{{ __('Deskripsi') }}</th>
                            <th class="px-2 py-2 text-left text-[10px] font-medium text-slate-500 uppercase">{{ __('Referensi') }}</th>
                            <th class="px-2 py-2 text-right text-[10px] font-medium text-slate-500 uppercase">{{ __('Pemasukan') }}</th>
                            <th class="px-2 py-2 text-right text-[10px] font-medium text-slate-500 uppercase">{{ __('Pengeluaran') }}</th>
                            <th class="px-2 py-2 text-right text-[10px] font-medium text-slate-500 uppercase">{{ __('Saldo') }}</th>
                            <th class="px-2 py-2 text-left text-[10px] font-medium text-slate-500 uppercase">{{ __('User') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @if(($orderDirection ?? 'bawah_ke_atas') === 'atas_ke_bawah' && $cashFlows->isNotEmpty())
                        <tr class="bg-slate-50 border-b border-slate-200">
                            <td colspan="7" class="px-2 py-2 text-right font-semibold text-slate-700">{{ __('Saldo Awal') }}</td>
                            <td class="px-2 py-2 text-right">-</td>
                            <td class="px-2 py-2 text-right">-</td>
                            <td class="px-2 py-2 text-right font-semibold text-slate-700">0</td>
                            <td class="px-2 py-2"></td>
                        </tr>
                        @endif
                        @forelse ($cashFlows as $cf)
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-2 py-2">{{ $cf->transaction_date->format('d/m/Y') }}</td>
                                <td class="px-2 py-2">
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-medium {{ $cf->type === 'IN' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800' }}">
                                        {{ $cf->type }}
                                    </span>
                                </td>
                                <td class="px-2 py-2">
                                    @if ($cf->warehouse_id)
                                        {{ __('Gudang') }}: {{ $cf->warehouse?->name ?? '-' }}
                                    @else
                                        {{ __('Cabang') }}: {{ $cf->branch?->name ?? '-' }}
                                    @endif
                                </td>
                                <td class="px-2 py-2">{{ $cf->paymentMethod?->display_label ?? '-' }}</td>
                                <td class="px-2 py-2">
                                    @if ($cf->type === 'OUT')
                                        {{ $cf->expenseCategory?->name ?? '-' }}
                                    @else
                                        {{ $cf->incomeCategory?->name ?? '-' }}
                                    @endif
                                </td>
                                <td class="px-2 py-2">{{ $cf->description }}</td>
                                <td class="px-2 py-2">
                                    @if ($cf->reference_type && $cf->reference_id)
                                        {{ $cf->reference_type }} #{{ $cf->reference_id }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-2 py-2 text-right font-medium text-emerald-600">
                                    {{ $cf->type === 'IN' ? number_format($cf->amount, 0, ',', '.') : '-' }}
                                </td>
                                <td class="px-2 py-2 text-right font-medium text-red-600">
                                    {{ $cf->type === 'OUT' ? number_format($cf->amount, 0, ',', '.') : '-' }}
                                </td>
                                <td class="px-2 py-2 text-right font-medium {{ ($cf->running_balance ?? 0) >= 0 ? 'text-slate-700' : 'text-red-600' }}">
                                    {{ number_format($cf->running_balance ?? 0, 0, ',', '.') }}
                                </td>
                                <td class="px-2 py-2">{{ $cf->user?->name }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="px-2 py-8 text-center text-slate-500">{{ __('Tidak ada data.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($cashFlows->isNotEmpty() && ($orderDirection ?? 'bawah_ke_atas') === 'bawah_ke_atas')
                    <tfoot class="bg-slate-50 border-t-2 border-slate-200">
                        <tr>
                            <td colspan="7" class="px-2 py-2 text-right font-semibold text-slate-700">{{ __('Saldo Awal') }}</td>
                            <td class="px-2 py-2 text-right">-</td>
                            <td class="px-2 py-2 text-right">-</td>
                            <td class="px-2 py-2 text-right font-semibold text-slate-700">0</td>
                            <td class="px-2 py-2"></td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
                @if ($cashFlows->count() >= 1000)
                    <p class="mt-3 text-sm text-amber-600">{{ __('Menampilkan 1000 transaksi terakhir. Saldo dihitung dari transaksi terlama dalam rentang tersebut.') }}</p>
                @endif
            </div>
        </div>
    </div>

    @if(($canFilterLocation ?? false) || ($filterLocked ?? false))
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const locType = document.getElementById('cf_location_type');
            const wrapper = document.getElementById('cf_location_wrapper');
            const label = document.getElementById('cf_location_label');
            const whBlock = document.querySelector('.filter-cf-warehouse');
            const brBlock = document.querySelector('.filter-cf-branch');
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
