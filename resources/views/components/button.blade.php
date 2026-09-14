@props(['variant' => 'primary'])

@php
    $variants = [
        'primary' => 'btn-primary',
        'secondary' => 'btn-secondary',
    ];
@endphp

<button
    {{ $attributes->merge([
        'type' => 'submit',
        'class' => 'btn '.($variants[$variant] ?? $variants['primary']),
    ]) }}
>
    {{ $slot }}
</button>
