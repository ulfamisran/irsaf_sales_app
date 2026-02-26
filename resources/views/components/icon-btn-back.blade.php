@props(['href', 'label' => __('Kembali')])

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 px-4 py-2 rounded-lg font-medium text-sm text-slate-600 hover:text-slate-800 bg-slate-100 hover:bg-slate-200 transition-colors']) }}>
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
    </svg>
    {{ $label }}
</a>
