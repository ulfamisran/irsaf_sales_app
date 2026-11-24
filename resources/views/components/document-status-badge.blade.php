@props(['status' => null])
@php
    $presenter = \App\Support\DocumentStatusPresenter::class;
@endphp
<span {{ $attributes->merge(['class' => $presenter::badgeClasses($status)]) }}>
    {{ $presenter::label($status) }}
</span>
