@props(['for', 'type' => 'text', 'label', 'required' => false, 'value' => null])

@php($type = $type === 'checkbox' ? 'checkbox' : $type)

<div>
    @if ($type === 'checkbox')
        <label class="flex items-center gap-2 text-sm text-gray-700">
            <input
                type="checkbox"
                name="{{ $name }}"
                value="1"
                @checked($value ?? old($name, true))
                {{ $attributes->where('class', null)->merge(['class' => 'rounded border-gray-300 text-gray-900 focus:ring-gray-500']) }}
            >
            {{ $slot->isEmpty() ? $label : $slot }}
        </label>
    @else
        <x-label for="{{ $for }}">{{ $label }}</x-label>
        <x-input
            :id="$for"
            :type="$type"
            :name="$name"
            :value="old($name, $value)"
            :required="$required"
            class="mt-1"
            {{ $attributes->except('name') }}
        />
    @endif
    <x-input-error :messages="$errors->get($name)" />
</div>
