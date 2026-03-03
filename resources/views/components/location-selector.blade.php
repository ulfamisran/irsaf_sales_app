@props([
    'branches' => collect(),
    'warehouses' => collect(),
    'canChoose' => true,
    'defaultPlacementType' => null,
    'defaultBranchId' => null,
    'defaultWarehouseId' => null,
    'oldPlacementType' => null,
    'oldBranchId' => null,
    'oldWarehouseId' => null,
])

@php
    $placementType = old('placement_type', $oldPlacementType ?? $defaultPlacementType ?? 'cabang');
    $branchId = old('branch_id', $oldBranchId ?? $defaultBranchId);
    $warehouseId = old('warehouse_id', $oldWarehouseId ?? $defaultWarehouseId);
@endphp

<div {{ $attributes->merge(['class' => '']) }} x-data="{ locType: '{{ $placementType }}' }">
    <x-input-label :value="__('Lokasi')" />
    @if($canChoose)
        <div class="mt-2 flex gap-6">
            <label class="inline-flex items-center cursor-pointer">
                <input type="radio" name="placement_type" value="cabang" x-model="locType"
                    class="rounded-full border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <span class="ml-2 text-sm font-medium text-gray-700">{{ __('Cabang') }}</span>
            </label>
            <label class="inline-flex items-center cursor-pointer">
                <input type="radio" name="placement_type" value="gudang" x-model="locType"
                    class="rounded-full border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <span class="ml-2 text-sm font-medium text-gray-700">{{ __('Gudang') }}</span>
            </label>
        </div>
        <template x-if="locType === 'cabang'">
            <div class="mt-3">
                <x-input-label for="branch_id" :value="__('Cabang')" />
                <select id="branch_id" name="branch_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" {{ $placementType === 'cabang' ? 'required' : '' }}>
                    <option value="">{{ __('Pilih Cabang') }}</option>
                    @foreach ($branches as $b)
                        <option value="{{ $b->id }}" {{ $branchId == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('branch_id')" class="mt-2" />
            </div>
        </template>
        <template x-if="locType === 'gudang'">
            <div class="mt-3">
                <x-input-label for="warehouse_id" :value="__('Gudang')" />
                <select id="warehouse_id" name="warehouse_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" {{ $placementType === 'gudang' ? 'required' : '' }}>
                    <option value="">{{ __('Pilih Gudang') }}</option>
                    @foreach ($warehouses as $w)
                        <option value="{{ $w->id }}" {{ $warehouseId == $w->id ? 'selected' : '' }}>{{ $w->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('warehouse_id')" class="mt-2" />
            </div>
        </template>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const form = document.querySelector('form');
                if (!form) return;
                const locRadios = form.querySelectorAll('input[name="placement_type"]');
                function syncLocation() {
                    const val = form.querySelector('input[name="placement_type"]:checked')?.value;
                    const branchSelect = form.querySelector('select[name="branch_id"]');
                    const whSelect = form.querySelector('select[name="warehouse_id"]');
                    if (branchSelect) {
                        branchSelect.required = val === 'cabang';
                        if (val === 'gudang') branchSelect.value = '';
                    }
                    if (whSelect) {
                        whSelect.required = val === 'gudang';
                        if (val === 'cabang') whSelect.value = '';
                    }
                }
                locRadios.forEach(r => r.addEventListener('change', syncLocation));
                syncLocation();
            });
        </script>
    @else
        <div class="mt-2">
            @if($placementType === 'gudang' && $warehouses->isNotEmpty())
                <x-locked-location :value="__('Gudang') . ': ' . $warehouses->first()->name" />
                <input type="hidden" name="placement_type" value="gudang">
                <input type="hidden" name="warehouse_id" value="{{ $warehouseId }}">
            @elseif($placementType === 'cabang' && $branches->isNotEmpty())
                <x-locked-location :value="__('Cabang') . ': ' . $branches->first()->name" />
                <input type="hidden" name="placement_type" value="cabang">
                <input type="hidden" name="branch_id" value="{{ $branchId }}">
            @else
                <div class="flex items-center gap-2 py-2 px-3 rounded-md border border-amber-300/80 bg-[#FFFACD]">
                    <svg class="w-4 h-4 text-amber-700 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                    <span class="text-sm font-medium text-amber-900">{{ __('Anda belum memiliki lokasi cabang/gudang yang ditetapkan.') }}</span>
                </div>
            @endif
        </div>
    @endif
</div>
