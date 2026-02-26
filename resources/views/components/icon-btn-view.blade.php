@props(['href', 'label' => __('Lihat')])

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 px-2.5 py-1.5 text-sm font-medium text-emerald-600 hover:text-emerald-800 transition-colors']) }}>
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
    </svg>
    {{ $label }}
</a>
