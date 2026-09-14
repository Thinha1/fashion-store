@props(['id' => 'password', 'name' => 'password', 'autocomplete' => 'current-password'])

<div x-data="{ visible: false }" class="relative mt-1">
    <x-input :id="$id" :name="$name" type="password" x-bind:type="visible ? 'text' : 'password'" :autocomplete="$autocomplete" required class="pr-14" />
    <button type="button" x-on:click="visible = !visible" :aria-pressed="visible" aria-controls="{{ $id }}" :aria-label="visible ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'" class="absolute inset-y-0 right-1 flex w-11 items-center justify-center rounded-lg text-brand hover:bg-brand-soft">
        <i class="fa-solid fa-eye" :class="{ 'fa-eye': !visible, 'fa-eye-slash': visible }" aria-hidden="true"></i>
    </button>
</div>
