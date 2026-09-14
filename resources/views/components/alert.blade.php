@props(['type' => 'success'])

@php
    $variants = [
        'success' => 'bg-green-50 text-green-800 border-green-200',
        'error' => 'bg-red-50 text-red-800 border-red-200',
    ];
@endphp

<div role="{{ $type === 'error' ? 'alert' : 'status' }}" {{ $attributes->merge(['class' => 'flex items-start gap-3 rounded-xl border px-4 py-3 text-sm '.($variants[$type] ?? $variants['success'])]) }}>
    <x-icon :name="$type === 'error' ? 'info' : 'check'" class="mt-0.5 size-4 shrink-0" />
    <div>{{ $slot }}</div>
</div>
