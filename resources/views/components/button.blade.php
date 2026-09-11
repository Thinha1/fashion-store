@props(['variant' => 'primary'])

@php
    $variants = [
        'primary' => 'bg-gray-900 text-white hover:bg-gray-700 focus-visible:outline-gray-900',
        'secondary' => 'bg-white text-gray-900 border border-gray-300 hover:bg-gray-50 focus-visible:outline-gray-400',
    ];
@endphp

<button
    {{ $attributes->merge([
        'type' => 'submit',
        'class' => 'inline-flex items-center justify-center gap-2 rounded-md px-4 py-2 text-sm font-medium transition disabled:cursor-not-allowed disabled:opacity-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 '.$variants[$variant],
    ]) }}
>
    {{ $slot }}
</button>
