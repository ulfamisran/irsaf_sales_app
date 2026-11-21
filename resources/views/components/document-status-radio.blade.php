@props([
    'module',
    'name' => 'status',
    'value' => null,
    'label' => null,
    'hint' => null,
])
@php
    $current = old($name, $value ?? 'open');
    $options = \App\Support\DocumentStatusPresenter::radioOptions($module);
    $showDefaultHint = $hint === null;
@endphp
<div {{ $attributes->merge(['class' => 'rounded-lg border border-indigo-200 bg-indigo-50/50 p-4']) }}>
    <x-input-label :value="$label ?: __('Status Dokumen')" class="font-semibold" />
    @if ($showDefaultHint)
        <p class="text-xs text-slate-600 mt-1 mb-3">{{ __('Open: simpan dulu sebagai draft. Release: finalisasi transaksi (stok/kas sesuai modul).') }}</p>
    @elseif (is_string($hint) && $hint !== '')
        <p class="text-xs text-slate-600 mt-1 mb-3">{{ $hint }}</p>
    @endif
    {{ $slot }}
    <div class="flex flex-wrap gap-6">
        @foreach ($options as $option)
            <label class="inline-flex items-center cursor-pointer">
                <input
                    type="radio"
                    name="{{ $name }}"
                    id="{{ $name }}_{{ $option['value'] }}"
                    value="{{ $option['value'] }}"
                    @checked((string) $current === (string) $option['value'])
                    class="rounded-full border-gray-300 text-indigo-600 focus:ring-indigo-500"
                >
                <span class="ml-2 text-sm font-medium text-gray-700">{{ $option['label'] }}</span>
            </label>
        @endforeach
    </div>
</div>
