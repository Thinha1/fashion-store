@props(['href' => null, 'variant' => 'primary', 'icon' => null])
@php($style = in_array($variant, ['primary', 'secondary', 'danger', 'quiet'], true) ? $variant : 'primary')
@if ($href)
    <a href="{{ $href }}" {{ $attributes->class(['admin-action', 'admin-action-'.$style]) }}>
        @if ($icon)<x-icon :name="$icon" class="size-4" />@endif
        {{ $slot }}
    </a>
@else
    <button {{ $attributes->merge(['type' => 'submit'])->class(['admin-action', 'admin-action-'.$style]) }}>
        @if ($icon)<x-icon :name="$icon" class="size-4" />@endif
        {{ $slot }}
    </button>
@endif
