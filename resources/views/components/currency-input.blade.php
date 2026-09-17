@props(['name', 'id' => null, 'value' => null, 'required' => false, 'unit' => '₫', 'unitExpression' => null])

@php
    $id = $id ?? $name;
    $rawValue = $value !== null && $value !== '' ? (string) $value : '';
    $displayValue = $rawValue !== '' && is_numeric($rawValue)
        ? rtrim(rtrim(number_format((float) $rawValue, 2, ',', '.'), '0'), ',')
        : $rawValue;
@endphp

{{-- Keep the named, unformatted input usable without JavaScript. Alpine switches
     submission to the hidden canonical value and formats only the visible field. --}}
<div class="currency-input relative" x-data="{ display: {{ Js::from($displayValue) }} }">
    <input type="text"
           id="{{ $id }}"
           name="{{ $name }}" x-bind:name="null"
           value="{{ $rawValue }}"
           inputmode="decimal"
           autocomplete="off"
           placeholder="0"
           x-model="display"
           x-mask:dynamic="$money($input, ',', '.', 2)"
           pattern="[0-9.,]+"
           @if ($required) required @endif
           {{ $attributes->merge(['class' => 'field pr-10 tabular-nums']) }}
    >
    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-gray-400" aria-hidden="true"
          @if ($unitExpression) x-text="{{ $unitExpression }}" @endif>{{ $unit }}</span>
    <input type="hidden" name="{{ $name }}" value="{{ $rawValue }}" disabled x-bind:disabled="false"
           :value="display.toString().replaceAll('.', '').replace(',', '.')">
</div>
