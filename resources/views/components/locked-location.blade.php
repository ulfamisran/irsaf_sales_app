@props([
    'value' => '',
    'label' => null,
])

@php
    $value = $value ?? '';
@endphp

<div {{ $attributes->merge(['class' => ''])->only('class') }}>
    @if($label)
        <label class="block text-sm font-medium text-slate-700 mb-1">{{ $label }}</label>
    @endif
    <div class="flex items-center gap-2 py-2 px-3 rounded-md border border-amber-300/80 bg-[#FFFACD] text-amber-900">
        <svg class="w-4 h-4 text-amber-700 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
        </svg>
        <span class="text-sm font-medium text-amber-900">{{ $value ?: '-' }}</span>
    </div>
    {{ $slot }}
</div>
