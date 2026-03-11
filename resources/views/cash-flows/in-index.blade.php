<x-app-layout>
    <x-slot name="title">{{ __('Dana Masuk') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Pemasukan Lainnya') }}</h2>
    </x-slot>

    <div class="max-w-7xl mx-auto">
        <div class="card-modern overflow-hidden mb-6">
            <div class="p-4 border-b border-gray-100">
                <form method="GET" action="{{ route('cash-flows.in.index') }}" class="flex flex-wrap gap-4 items-end">
                    @if(($canFilterLocation ?? false) || ($filterLocked ?? false))
                        @php
                            $branchSelectDisabled = $filterLocked ?? false;
                            $warehouseSelectDisabled = $filterLocked ?? false;
                            $selectedBranchId = $filterLocked && ($lockedBranchId ?? null) ? $lockedBranchId : request('branch_id');
                            $selectedWarehouseId = $filterLocked && ($lockedWarehouseId ?? null) ? $lockedWarehouseId : request('warehouse_id');
                            $locType = request('location_type') ?? '';
                            if ($filterLocked) {
                                $locType = $lockedWarehouseId ? 'warehouse' : ($lockedBranchId ? 'branch' : '');
                            }
                            if ($locType === '' && ($selectedBranchId || $selectedWarehouseId)) {
                                $locType = $selectedWarehouseId ? 'warehouse' : 'branch';
                            }
                        @endphp
                        <div class="min-w-[180px]">
                            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Tipe Lokasi') }}</label>
                            <select name="location_type" id="in_location_type" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm {{ $branchSelectDisabled ? 'bg-slate-100 cursor-not-allowed' : '' }}" {{ $branchSelectDisabled ? 'disabled' : '' }}>
                                <option value="" {{ $locType === '' ? 'selected' : '' }}>{{ __('Semua') }}</option>
                                <option value="branch" {{ $locType === 'branch' ? 'selected' : '' }}>{{ __('Cabang') }}</option>
                                <option value="warehouse" {{ $locType === 'warehouse' ? 'selected' : '' }}>{{ __('Gudang') }}</option>
                            </select>
                            @if($branchSelectDisabled)
                                <input type="hidden" name="location_type" value="{{ $locType }}">
                            @endif
                        </div>
                        <div id="in_location_wrapper" class="min-w-[180px]" style="{{ $locType === '' ? 'display:none' : '' }}">
                            <label class="block text-sm font-medium text-slate-700 mb-1" id="in_location_label">{{ $locType === 'warehouse' ? __('Gudang') : __('Cabang') }}</label>
                            <div class="filter-in-warehouse" style="{{ $locType !== 'warehouse' ? 'display:none' : '' }}">
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
                            <div class="filter-in-branch" style="{{ $locType !== 'branch' ? 'display:none' : '' }}">
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
                    <div class="min-w-[180px]">
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Kategori Pemasukan') }}</label>
                        <select name="income_category_id" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="">{{ __('Semua') }}</option>
                            @foreach ($incomeCategories as $cat)
                                <option value="{{ $cat->id }}" {{ request('income_category_id') == $cat->id ? 'selected' : '' }}>
                                    {{ $cat->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="min-w-[160px]">
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Dari Tanggal') }}</label>
                        <input type="date" name="date_from" value="{{ request('date_from') }}" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    </div>
                    <div class="min-w-[160px]">
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Sampai Tanggal') }}</label>
                        <input type="date" name="date_to" value="{{ request('date_to') }}" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            {{ __('Filter') }}
                        </button>
                        <a href="{{ route('cash-flows.in.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">
                            {{ __('Reset') }}
                        </a>
                        <a href="{{ route('cash-flows.in.create') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700">
                            {{ __('Tambah Pemasukan') }}
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div class="card-modern p-6">
                <p class="text-sm text-slate-600">{{ __('Total Pemasukan Lainnya') }}</p>
                <p class="text-xl font-semibold text-emerald-600">+{{ number_format($totalIn, 0, ',', '.') }}</p>
            </div>
        </div>

        @if (($paymentMethods ?? collect())->count())
            <div class="card-modern p-6 mb-6">
                <p class="text-sm text-slate-600 mb-3 font-semibold">{{ __('Rincian Metode Pembayaran') }}</p>
                @php
                    $colorSets = [
                        ['border' => 'border-emerald-200', 'bg' => 'bg-emerald-50', 'text' => 'text-emerald-700'],
                        ['border' => 'border-indigo-200', 'bg' => 'bg-indigo-50', 'text' => 'text-indigo-700'],
                        ['border' => 'border-amber-200', 'bg' => 'bg-amber-50', 'text' => 'text-amber-700'],
                        ['border' => 'border-rose-200', 'bg' => 'bg-rose-50', 'text' => 'text-rose-700'],
                        ['border' => 'border-sky-200', 'bg' => 'bg-sky-50', 'text' => 'text-sky-700'],
                        ['border' => 'border-violet-200', 'bg' => 'bg-violet-50', 'text' => 'text-violet-700'],
                    ];
                @endphp
                <div class="overflow-x-auto">
                    <div class="flex gap-3 min-w-max">
                        @foreach ($paymentMethods as $pm)
                            @php
                                $pmTotal = (float) data_get($paymentMethodTotals ?? [], $pm->id, 0);
                                $color = $colorSets[$loop->index % count($colorSets)];
                            @endphp
                            <div class="rounded-lg border {{ $color['border'] }} {{ $color['bg'] }} p-3 min-w-[180px]">
                                <p class="text-xs text-slate-500">{{ $pm->display_label }}</p>
                                <p class="text-lg font-semibold {{ $color['text'] }}">+{{ number_format($pmTotal, 0, ',', '.') }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <div class="card-modern overflow-hidden">
            <div class="p-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Tanggal') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Lokasi') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Kategori') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Deskripsi') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Jumlah') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('User') }}</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                    @forelse ($incomes as $inc)
                        <tr class="hover:bg-slate-50/50">
                            <td class="px-4 py-3">{{ $inc->transaction_date->format('d/m/Y') }}</td>
                            <td class="px-4 py-3">
                                @if ($inc->warehouse_id)
                                    {{ __('Gudang') }}: {{ $inc->warehouse?->name ?? '-' }}
                                @else
                                    {{ __('Cabang') }}: {{ $inc->branch?->name ?? '-' }}
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $inc->incomeCategory?->name ?? '-' }}</td>
                            <td class="px-4 py-3">{{ $inc->description }}</td>
                            <td class="px-4 py-3 text-right font-medium text-emerald-600">
                                +{{ number_format($inc->amount, 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-3">{{ $inc->user?->name }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-slate-500">{{ __('Tidak ada data pemasukan lainnya.') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
                <div class="mt-4">{{ $incomes->links() }}</div>
            </div>
        </div>
    </div>

    @if(($canFilterLocation ?? false) || ($filterLocked ?? false))
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const locType = document.getElementById('in_location_type');
            const wrapper = document.getElementById('in_location_wrapper');
            const label = document.getElementById('in_location_label');
            const whBlock = document.querySelector('.filter-in-warehouse');
            const brBlock = document.querySelector('.filter-in-branch');
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

