@props(['id', 'name', 'label', 'options' => [], 'value' => null, 'placeholder' => 'Chọn…', 'required' => false])

<div class="image-select" x-data="imageSelect" x-on:click.outside="open = false" x-on:keydown="keydown($event)">
    <label for="{{ $id }}-native" x-bind:for="ready ? @js($id) : @js($id.'-native')" class="block text-sm font-medium text-gray-700">{{ $label }}</label>
    <select id="{{ $id }}-native" name="{{ $name }}" x-ref="native" x-show="!ready" class="field" @required($required)
        x-on:change="value = $event.target.value"
        x-on:invalid="if (ready) { $event.preventDefault(); invalid = true; $refs.trigger.focus(); }">
        <option value="">{{ $placeholder }}</option>
        @foreach ($options as $option)
            <option value="{{ $option['value'] }}" data-image="{{ $option['image'] ?? '' }}" @selected((string) $value === (string) $option['value'])>{{ $option['label'] }}</option>
        @endforeach
    </select>
    <button id="{{ $id }}" type="button" x-ref="trigger" x-cloak x-show="ready" class="image-select-trigger"
        role="combobox" aria-haspopup="listbox" aria-controls="{{ $id }}-options" aria-label="{{ $label }}"
        x-bind:aria-expanded="open" x-bind:aria-invalid="invalid" @if($required) aria-required="true" @endif
        x-bind:aria-activedescendant="open ? @js($id.'-option-') + activeIndex : null"
        x-on:click="open ? open = false : show()">
        <span class="min-w-0 flex-1 truncate text-left" x-text="selected?.label"></span>
        <span class="image-select-logo" x-show="value">
            <x-icon name="tag" class="size-4 text-gray-400" />
            <template x-if="selected?.image && !brokenImages[value]">
                <img x-bind:src="selected.image" alt="" x-on:error="brokenImages[value] = true">
            </template>
        </span>
        <x-icon name="chevron-down" class="size-3 text-gray-400" />
    </button>
    <ul id="{{ $id }}-options" x-ref="list" role="listbox" aria-label="{{ $label }}" x-cloak x-show="open"
        class="image-select-options" x-bind:class="{ 'is-upward': upward }" x-bind:style="{ maxHeight: maxHeight + 'px' }">
        <template x-for="(option, index) in options" x-bind:key="option.value">
            <li x-bind:id="@js($id.'-option-') + index" role="option" x-bind:aria-selected="value === option.value"
                x-bind:class="{ 'is-highlighted': index === activeIndex }" x-on:mousemove="activeIndex = index"
                x-on:mousedown.prevent x-on:click="choose(index)">
                <span class="min-w-0 flex-1 break-words" x-text="option.label"></span>
                <x-icon name="check" class="size-3 text-brand" x-show="value === option.value && value" />
                <span class="image-select-logo" x-show="option.value">
                    <x-icon name="tag" class="size-4 text-gray-400" />
                    <template x-if="option.image && !brokenImages[option.value]">
                        <img x-bind:src="option.image" alt="" loading="lazy" x-on:error="brokenImages[option.value] = true">
                    </template>
                </span>
            </li>
        </template>
    </ul>
    <p x-cloak x-show="invalid" role="alert" class="mt-1 text-sm text-red-600">Vui lòng chọn {{ mb_strtolower($label) }}.</p>
</div>
