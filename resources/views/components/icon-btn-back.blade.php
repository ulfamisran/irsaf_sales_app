@props(['href', 'label' => __('Kembali')])

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg font-medium text-sm text-slate-700 bg-slate-100 hover:bg-slate-200 transition-colors shrink-0 whitespace-nowrap no-underline']) }}>
    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
    </svg>
    <span>{{ $label }}</span>
</a>
