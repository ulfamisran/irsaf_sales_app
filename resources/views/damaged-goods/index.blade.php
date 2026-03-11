<x-app-layout>
    <x-slot name="title">{{ __('Barang Rusak Cadangan') }}</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Barang Rusak Cadangan') }}</h2>
            <x-icon-btn-add :href="route('damaged-goods.create')" :label="__('Catat Barang Rusak')" />
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
                <form method="GET" action="{{ route('damaged-goods.index') }}" class="flex flex-wrap gap-4 items-end">
                    @if(($canFilterLocation ?? false) || ($filterLocked ?? false))
                        @php
                            $locType = request('location_type') ?? '';
                            if ($filterLocked) {
                                $locType = ($lockedWarehouseId ?? null) ? 'warehouse' : (($lockedBranchId ?? null) ? 'branch' : '');
                            }
                            $selectedBranchId = request('branch_id');
                            $selectedWarehouseId = request('warehouse_id');
                            if ($filterLocked) {
                                $selectedBranchId = $lockedBranchId ?? null;
                                $selectedWarehouseId = $lockedWarehouseId ?? null;
                            }
                            if ($locType === '' && ($selectedBranchId || $selectedWarehouseId)) {
                                $locType = $selectedWarehouseId ? 'warehouse' : 'branch';
                            }
                        @endphp
                        @if($filterLocked ?? false)
                            <div class="min-w-[200px]">
                                <x-locked-location label="{{ __('Lokasi') }}" :value="$locationLabel ?? ''" />
                                @if($locType === 'warehouse')
                                    <input type="hidden" name="warehouse_id" value="{{ $selectedWarehouseId }}">
                                @else
                                    <input type="hidden" name="branch_id" value="{{ $selectedBranchId }}">
                                @endif
                            </div>
                        @else
                            <div class="min-w-[160px]">
                                <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Tipe Lokasi') }}</label>
                                <select name="location_type" id="dg_location_type" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="" {{ $locType === '' ? 'selected' : '' }}>{{ __('Semua') }}</option>
                                    <option value="warehouse" {{ $locType === 'warehouse' ? 'selected' : '' }}>{{ __('Gudang') }}</option>
                                    <option value="branch" {{ $locType === 'branch' ? 'selected' : '' }}>{{ __('Cabang') }}</option>
                                </select>
                            </div>
                            <div id="dg_location_wrapper" class="min-w-[180px]" style="{{ $locType === '' ? 'display:none' : '' }}">
                                <label class="block text-sm font-medium text-slate-700 mb-1" id="dg_location_label">{{ $locType === 'warehouse' ? __('Gudang') : __('Cabang') }}</label>
                                <div class="filter-dg-warehouse" style="{{ $locType !== 'warehouse' ? 'display:none' : '' }}">
                                    <select name="warehouse_id" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">{{ __('Semua') }}</option>
                                        @foreach ($warehouses ?? [] as $w)
                                            <option value="{{ $w->id }}" {{ (string)($selectedWarehouseId ?? '') === (string)$w->id ? 'selected' : '' }}>{{ $w->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="filter-dg-branch" style="{{ $locType !== 'branch' ? 'display:none' : '' }}">
                                    <select name="branch_id" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">{{ __('Semua') }}</option>
                                        @foreach ($branches ?? [] as $b)
                                            <option value="{{ $b->id }}" {{ (string)($selectedBranchId ?? '') === (string)$b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @endif
                    @endif
                    <div class="flex gap-2">
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            {{ __('Filter') }}
                        </button>
                        <a href="{{ route('damaged-goods.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">
                            {{ __('Reset') }}
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card-modern p-6 mb-6">
            <p class="text-sm text-slate-600">{{ __('Total Beban HPP Barang Rusak') }}</p>
            <p class="text-2xl font-bold text-rose-600">{{ number_format($totalHpp ?? 0, 0, ',', '.') }}</p>
        </div>

        <div class="card-modern overflow-hidden">
            <div class="p-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Tanggal') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Kategori') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Produk') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('No. Serial') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Lokasi') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Deskripsi Kerusakan') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('HPP') }}</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">{{ __('Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($damagedGoods as $dg)
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-4 py-3">{{ $dg->recorded_date->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">{{ $dg->productUnit?->product?->category?->name ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    {{ ($dg->productUnit?->product?->sku ?? '') . ' - ' . ($dg->productUnit?->product?->brand ?? '') . ' ' . ($dg->productUnit?->product?->series ?? '') }}
                                </td>
                                <td class="px-4 py-3 font-mono text-sm">{{ $dg->serial_number }}</td>
                                <td class="px-4 py-3">
                                    @if ($dg->productUnit?->location_type === 'warehouse')
                                        {{ __('Gudang') }}: {{ $dg->productUnit?->warehouse?->name ?? '-' }}
                                    @else
                                        {{ __('Cabang') }}: {{ $dg->productUnit?->branch?->name ?? '-' }}
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm max-w-xs truncate" title="{{ $dg->damage_description }}">{{ Str::limit($dg->damage_description, 50) }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-rose-600">{{ number_format($dg->harga_hpp, 0, ',', '.') }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('damaged-goods.reactivate-form', $dg) }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-md bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700">
                                        {{ __('Aktifkan Kembali') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-12 text-center text-slate-500">{{ __('Tidak ada data barang rusak.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="mt-4">{{ $damagedGoods->links() }}</div>
            </div>
        </div>
    </div>

    @if($canFilterLocation ?? false)
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const locType = document.getElementById('dg_location_type');
            const wrapper = document.getElementById('dg_location_wrapper');
            const label = document.getElementById('dg_location_label');
            const whBlock = document.querySelector('.filter-dg-warehouse');
            const brBlock = document.querySelector('.filter-dg-branch');
            const whSelect = whBlock?.querySelector('select[name="warehouse_id"]');
            const brSelect = brBlock?.querySelector('select[name="branch_id"]');
            if (locType) {
                function toggle() {
                    const v = locType.value;
                    if (!v) {
                        wrapper.style.display = 'none';
                        if (whSelect) whSelect.value = '';
                        if (brSelect) brSelect.value = '';
                        return;
                    }
                    wrapper.style.display = '';
                    if (v === 'warehouse') {
                        if (label) label.textContent = '{{ __("Gudang") }}';
                        if (whBlock) whBlock.style.display = '';
                        if (brBlock) brBlock.style.display = 'none';
                        if (brSelect) brSelect.value = '';
                    } else {
                        if (label) label.textContent = '{{ __("Cabang") }}';
                        if (whBlock) whBlock.style.display = 'none';
                        if (brBlock) brBlock.style.display = '';
                        if (whSelect) whSelect.value = '';
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
