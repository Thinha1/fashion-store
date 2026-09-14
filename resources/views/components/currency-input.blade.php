@props(['name', 'id' => null, 'value' => null, 'required' => false])

@php($id = $id ?? $name)
@php($rawValue = $value !== null && $value !== '' ? (string) (int) round((float) $value) : '')
@php($displayValue = $rawValue !== '' ? number_format((float) $rawValue, 0, '.', ',') : '')

{{-- Input người dùng gõ chỉ để hiển thị (có dấu phẩy phân cách mỗi 3 số,
     x-mask của Alpine tự định dạng khi gõ); giá trị thật submit qua input
     hidden cùng tên field gốc, luôn là số nguyên không dấu phân cách. --}}
<div x-data="{ display: @js($displayValue) }">
    <input type="text"
           id="{{ $id }}"
           inputmode="numeric"
           autocomplete="off"
           placeholder="0"
           x-model="display"
           x-mask:dynamic="$money($input, ',', 0)"
           @if ($required) required @endif
           {{ $attributes->merge(['class' => 'block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-500']) }}
    >
    <input type="hidden" name="{{ $name }}" value="{{ $rawValue }}" :value="display.toString().replaceAll(',', '')">
</div>
