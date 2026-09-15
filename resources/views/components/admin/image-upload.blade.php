@props(['id', 'name', 'label', 'errorKey', 'previewAlt' => 'Ảnh sản phẩm mới chọn'])

<div x-data="imageUpload">
    <x-label :for="$id">{{ $label }}</x-label>
    <input id="{{ $id }}" x-ref="fileInput" type="file" name="{{ $name }}" multiple accept="image/jpeg,image/png,image/webp"
           class="mt-1 block w-full min-w-0 text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-gray-700"
           x-on:change="selectFiles($event.target.files)">
    <x-input-error :messages="$errors->get($errorKey)" />
    <x-input-error :messages="collect($errors->get($errorKey.'.*'))->flatten()->all()" />
    <div class="mt-3 flex flex-wrap gap-2" x-show="previews.length" x-cloak>
        <template x-for="preview in previews" :key="preview.url">
            <figure class="relative w-24 min-w-0">
                <img :src="preview.url" alt="{{ $previewAlt }}" class="aspect-square w-full rounded-lg border border-gray-200 object-cover">
                <button type="button" x-on:click="removeFile(preview.fileIndex)" class="admin-image-remove" :aria-label="'Bỏ ảnh ' + preview.name" title="Bỏ ảnh"><x-icon name="delete" class="size-3.5" /></button>
                <figcaption class="mt-1 truncate text-xs text-gray-500" x-text="preview.name" :title="preview.name"></figcaption>
            </figure>
        </template>
    </div>
</div>
