@props(['href', 'label' => __('Tambah')])

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 px-4 py-2.5 rounded-lg font-semibold text-sm text-white bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 shadow-md hover:shadow-lg transition-all duration-200']) }}>
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
    </svg>
    {{ $label }}
</a>
