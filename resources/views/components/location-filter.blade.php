@props([
    'filterLocked' => false,
    'locationType' => null,
    'locationId' => null,
    'locationLabel' => null,
    'branches' => collect(),
    'warehouses' => collect(),
    'routeName' => null,
])

<div class="min-w-[200px]">
    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Lokasi') }}</label>
    @if($filterLocked && $locationLabel)
        <x-locked-location :value="$locationLabel" />
        <input type="hidden" name="location_type" value="{{ $locationType }}">
        <input type="hidden" name="location_id" value="{{ $locationId }}">
    @else
        <div x-data="{ locFilter: '{{ old('location_type', $locationType ?? '') }}' }" class="space-y-2">
            <select name="location_type" x-model="locFilter" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="">{{ __('Semua Lokasi') }}</option>
                <option value="cabang">{{ __('Cabang') }}</option>
                <option value="gudang">{{ __('Gudang') }}</option>
            </select>
            <template x-if="locFilter === 'cabang'">
                <select name="location_id" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <option value="">{{ __('Pilih Cabang') }}</option>
                    @foreach ($branches as $b)
                        <option value="{{ $b->id }}" {{ (($locationType ?? '') === 'cabang' && $locationId == $b->id) ? 'selected' : '' }}>{{ $b->name }}</option>
                    @endforeach
                </select>
            </template>
            <template x-if="locFilter === 'gudang'">
                <select name="location_id" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <option value="">{{ __('Pilih Gudang') }}</option>
                    @foreach ($warehouses as $w)
                        <option value="{{ $w->id }}" {{ (($locationType ?? '') === 'gudang' && $locationId == $w->id) ? 'selected' : '' }}>{{ $w->name }}</option>
                    @endforeach
                </select>
            </template>
            <template x-if="!locFilter">
                <input type="hidden" name="location_id" value="">
            </template>
        </div>
    @endif
</div>
