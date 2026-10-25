<x-app-layout>
    <x-slot name="title">{{ __('Data Utang') }}</x-slot>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Data Utang') }}</h2>
            <div class="flex gap-2">
                <a href="{{ route('debts.export', request()->query()) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700">
                    {{ __('Download Excel') }}
                </a>
                <a href="{{ route('debts.export-pdf', request()->query()) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium hover:bg-rose-700">
                    {{ __('Download PDF') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto">
        <div class="card-modern overflow-hidden mb-6">
            <div class="p-4 border-b border-gray-100">
                <form method="GET" action="{{ route('debts.index') }}" class="flex flex-wrap gap-4 items-end">
                    <div class="min-w-[220px] flex-1">
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Cari') }}</label>
                        <input type="text" name="q" value="{{ $search ?? request('q') }}" placeholder="{{ __('No. Invoice / Distributor') }}" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
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
                        <div class="min-w-[160px]">
                            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Tipe Lokasi') }}</label>
                            <select name="location_type" id="debt_location_type" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 {{ ($filterLocked ?? false) ? 'bg-slate-100 cursor-not-allowed' : '' }}" {{ ($filterLocked ?? false) ? 'disabled' : '' }}>
                                <option value="" {{ $locType === '' ? 'selected' : '' }}>{{ __('Semua') }}</option>
                                <option value="warehouse" {{ $locType === 'warehouse' ? 'selected' : '' }}>{{ __('Gudang') }}</option>
                                <option value="branch" {{ $locType === 'branch' ? 'selected' : '' }}>{{ __('Cabang') }}</option>
                            </select>
                            @if($filterLocked ?? false)
                                <input type="hidden" name="location_type" value="{{ $locType }}">
                            @endif
                        </div>
                        <div id="debt_location_wrapper" class="min-w-[180px]" style="{{ $locType === '' ? 'display:none' : '' }}">
                            <label class="block text-sm font-medium text-slate-700 mb-1" id="debt_location_label">{{ $locType === 'warehouse' ? __('Gudang') : __('Cabang') }}</label>
                            <div class="filter-debt-warehouse" style="{{ $locType !== 'warehouse' ? 'display:none' : '' }}">
                                <select name="warehouse_id" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 {{ $warehouseSelectDisabled ? 'bg-slate-100 cursor-not-allowed' : '' }}" {{ $warehouseSelectDisabled ? 'disabled' : '' }}>
                                    <option value="">{{ __('Semua') }}</option>
                                    @foreach ($warehouses ?? [] as $w)
                                        <option value="{{ $w->id }}" {{ (string)$selectedWarehouseId === (string)$w->id ? 'selected' : '' }}>{{ $w->name }}</option>
                                    @endforeach
                                </select>
                                @if($warehouseSelectDisabled && ($lockedWarehouseId ?? null))
                                    <input type="hidden" name="warehouse_id" value="{{ $lockedWarehouseId }}">
                                @endif
                            </div>
                            <div class="filter-debt-branch" style="{{ $locType !== 'branch' ? 'display:none' : '' }}">
                                <select name="branch_id" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 {{ $branchSelectDisabled ? 'bg-slate-100 cursor-not-allowed' : '' }}" {{ $branchSelectDisabled ? 'disabled' : '' }}>
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
                        <input type="date" name="date_from" value="{{ $dateFrom ?? request('date_from') }}" class="rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Sampai Tanggal') }}</label>
                        <input type="date" name="date_to" value="{{ $dateTo ?? request('date_to') }}" class="rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Status Utang') }}</label>
                        <select name="status" class="rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="outstanding" {{ ($statusFilter ?? 'outstanding') === 'outstanding' ? 'selected' : '' }}>{{ __('Outstanding (Belum Lunas)') }}</option>
                            <option value="lunas" {{ ($statusFilter ?? '') === 'lunas' ? 'selected' : '' }}>{{ __('Lunas') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Lingkup Utang') }}</label>
                        <select name="lingkup" class="rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="" {{ ($lingkupFilter ?? '') === '' ? 'selected' : '' }}>{{ __('Semua') }}</option>
                            <option value="eksternal" {{ ($lingkupFilter ?? '') === 'eksternal' ? 'selected' : '' }}>{{ __('Utang Eksternal') }}</option>
                            <option value="internal" {{ ($lingkupFilter ?? '') === 'internal' ? 'selected' : '' }}>{{ __('Utang Internal') }}</option>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            {{ __('Filter') }}
                        </button>
                        <a href="{{ route('debts.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">
                            {{ __('Reset') }}
                        </a>
                    </div>
                </form>
            </div>
        </div>

        @if (session('error'))
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-red-800">{{ session('error') }}</div>
        @endif
        @if (session('success'))
            <div class="mb-4 rounded-lg border border-green-200 bg-green-50 p-4 text-green-800">{{ session('success') }}</div>
        @endif

        @if (($statusFilter ?? 'outstanding') === 'outstanding')
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="card-modern p-5">
                    <p class="text-sm text-slate-600">{{ __('Total Utang Belum Terbayar') }}</p>
                    <p class="text-2xl font-bold text-red-600 mt-1">{{ number_format($totalUnpaid ?? 0, 0, ',', '.') }}</p>
                </div>
                <div class="card-modern p-5 border border-amber-200 bg-amber-50/40">
                    <p class="text-sm text-amber-800">{{ __('Utang Eksternal') }}</p>
                    <p class="text-xs text-amber-700 mt-0.5">{{ __('Vendor lingkup eksternal') }}</p>
                    <p class="text-2xl font-bold text-amber-800 mt-1">{{ number_format($totalUnpaidEksternal ?? 0, 0, ',', '.') }}</p>
                </div>
                <div class="card-modern p-5 border border-sky-200 bg-sky-50/40">
                    <p class="text-sm text-sky-800">{{ __('Utang Internal') }}</p>
                    <p class="text-xs text-sky-700 mt-0.5">{{ __('Vendor lingkup internal') }}</p>
                    <p class="text-2xl font-bold text-sky-800 mt-1">{{ number_format($totalUnpaidInternal ?? 0, 0, ',', '.') }}</p>
                </div>
            </div>
        @endif

        <div class="card-modern overflow-hidden">
            <div class="p-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('No. Invoice') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Lokasi') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Distributor') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Lingkup') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Tanggal') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Jatuh Tempo') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Nilai Invoice') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Utang Terakui') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Terbayar') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Sisa Utang Terakui') }}</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">{{ __('Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($rows as $row)
                            <tr class="hover:bg-slate-50/50 {{ $row->is_due_soon ? 'bg-red-50' : '' }}">
                                <td class="px-4 py-3 font-medium">
                                    <a href="{{ route('purchases.show', ['purchase' => $row->purchase, 'view' => 'detail']) }}" class="text-indigo-600 hover:underline">{{ $row->purchase->invoice_number }}</a>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($row->purchase->warehouse_id)
                                        {{ __('Gudang') }}: {{ $row->purchase->warehouse?->name ?? '-' }}
                                    @else
                                        {{ __('Cabang') }}: {{ $row->purchase->branch?->name ?? '-' }}
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $row->purchase->distributor?->name ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    @if (!empty($row->is_internal))
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-sky-100 text-sky-800">{{ __('Internal') }}</span>
                                    @else
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">{{ __('Eksternal') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $row->purchase->purchase_date->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 {{ $row->is_due_soon ? 'font-semibold text-red-600' : '' }}">
                                    {{ $row->due_date?->format('d/m/Y') ?? '-' }}
                                    @if ($row->is_due_soon && $row->days_until_due !== null)
                                        <span class="text-xs">({{ $row->days_until_due }} hari)</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">{{ number_format($row->invoice_total ?? $row->purchase->total, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right text-emerald-700 font-medium">{{ number_format($row->recognized ?? 0, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row->purchase->total_paid ?? 0, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-red-600">{{ number_format($row->remaining, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-center">
                                    <a href="{{ route('purchases.show', ['purchase' => $row->purchase, 'from' => 'debts', 'view' => 'detail']) }}#riwayat-pembayaran"
                                       class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                                        </svg>
                                        {{ __('Bayar') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="px-4 py-12 text-center text-slate-500">{{ __('Tidak ada data utang.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="mt-4">{{ $purchases->links() }}</div>
            </div>
        </div>
    </div>

    @if(($canFilterLocation ?? false) || ($filterLocked ?? false))
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const locType = document.getElementById('debt_location_type');
            const wrapper = document.getElementById('debt_location_wrapper');
            const label = document.getElementById('debt_location_label');
            const whBlock = document.querySelector('.filter-debt-warehouse');
            const brBlock = document.querySelector('.filter-debt-branch');
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
