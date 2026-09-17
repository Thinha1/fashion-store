@props(['name', 'id' => null, 'value' => null, 'required' => false, 'unit' => '₫', 'unitExpression' => null])

@php
    $id = $id ?? $name;
    $rawValue = $value !== null && $value !== '' ? (string) $value : '';
    $displayValue = $rawValue !== '' && is_numeric($rawValue)
        ? rtrim(rtrim(number_format((float) $rawValue, 2, ',', '.'), '0'), ',')
        : $rawValue;
@endphp

{{-- Without JavaScript, only the visible raw input has a name. Once Alpine starts,
     transfer that name synchronously to the canonical input: exactly one value is submitted. --}}
<div class="currency-input relative" x-data="currencyInput({{ Js::from($displayValue) }})"
     x-effect="$refs.display.setCustomValidity(valid ? '' : 'Số tiền không hợp lệ.')">
    <input type="text"
           id="{{ $id }}"
           name="{{ $name }}" x-ref="display"
           value="{{ $rawValue }}"
           inputmode="decimal"
           autocomplete="off"
           placeholder="0"
           x-model="display"
           x-mask:dynamic="$money($input, ',', '.', 2)"
           pattern="[0-9.,]+"
           @required($required)
           {{ $attributes->merge(['class' => 'field pr-10 tabular-nums']) }}
    >
    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-gray-400" aria-hidden="true"
          @if ($unitExpression) x-text="{{ $unitExpression }}" @endif>{{ $unit }}</span>
    <input type="hidden" x-ref="canonical" value="{{ $rawValue }}"
           :value="canonicalValue">
</div>
