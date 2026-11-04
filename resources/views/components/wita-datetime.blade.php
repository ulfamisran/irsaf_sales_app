@props(['value' => null])

@php
    $text = '-';
    if ($value) {
        $text = \Illuminate\Support\Carbon::parse($value)
            ->timezone('Asia/Makassar')
            ->format('d/m/Y H:i');
    }
@endphp

<span {{ $attributes->merge(['class' => 'whitespace-nowrap']) }}>{{ $text }}</span>
