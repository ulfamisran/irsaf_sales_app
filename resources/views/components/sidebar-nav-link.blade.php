@props(['active'])

@php
$classes = ($active ?? false)
    ? 'flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-lg text-white bg-gradient-to-r from-indigo-600/90 to-indigo-700/90 shadow-sm border-l-4 border-indigo-400'
    : 'flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-lg text-gray-300 hover:bg-white/5 hover:text-white transition-all duration-200 border-l-4 border-transparent';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
