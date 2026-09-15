@props(['images' => []])
@if (count($images))
    <div class="mb-3 flex flex-wrap gap-2">
        @foreach ($images as $image)
            <figure class="relative w-24 overflow-hidden rounded-lg border border-gray-200 bg-white" x-show="!removedImages.includes({{ $image->id }})" data-saved-image="{{ $image->id }}">
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('s3')->url($image->path) }}" alt="{{ $image->alt_text }}" class="aspect-square w-full object-cover">
                <button type="button" x-on:click="removeImage({{ $image->id }})" class="admin-image-remove" aria-label="Xóa ảnh {{ $image->alt_text }}" title="Xóa ảnh"><x-icon name="delete" class="size-3.5" /></button>
                @if ($image->is_primary)
                    <figcaption class="px-2 py-1 text-xs text-green-700">Ảnh chính</figcaption>
                @endif
            </figure>
        @endforeach
    </div>
@endif
