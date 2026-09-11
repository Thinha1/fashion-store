@props(['type' => 'success'])

@php
    $variants = [
        'success' => 'bg-green-50 text-green-800 border-green-200',
        'error' => 'bg-red-50 text-red-800 border-red-200',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-md border px-4 py-3 text-sm '.$variants[$type]]) }}>
    {{ $slot }}
</div>
