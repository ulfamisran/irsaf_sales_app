@props([
    'module',
    'name' => 'status',
    'value' => null,
    'includePaymentFilters' => false,
    'label' => null,
])
@php
    $value = $value ?? request($name);
    $options = \App\Support\DocumentStatusPresenter::filterOptions($module, (bool) $includePaymentFilters);
@endphp
<div {{ $attributes->only('class')->merge(['class' => 'min-w-[160px]']) }}>
    <label class="block text-sm font-medium text-slate-700 mb-1">{{ $label ?: __('Status Dokumen') }}</label>
    <select name="{{ $name }}" {{ $attributes->except('class')->merge(['class' => 'w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm']) }}>
        <option value="">{{ __('Semua') }}</option>
        @foreach ($options as $option)
            <option value="{{ $option['value'] }}" @selected((string) $value === (string) $option['value'])>{{ $option['label'] }}</option>
        @endforeach
    </select>
</div>
