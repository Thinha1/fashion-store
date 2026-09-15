@props(['product'])

<form method="POST" action="{{ route('admin.products.featured', $product) }}"
      x-data="featuredProduct(@js($product->is_featured))" x-on:submit.prevent="save($el)">
    @csrf
    @method('PATCH')
    <input type="hidden" name="is_featured" value="{{ $product->is_featured ? '0' : '1' }}" :value="featured ? '0' : '1'">
    <button type="submit" class="inline-flex size-11 items-center justify-center rounded-xl transition-colors hover:bg-amber-50 disabled:cursor-wait disabled:opacity-50"
            :disabled="busy" :aria-busy="busy"
            aria-label="Nổi bật: {{ $product->name }}"
            aria-pressed="{{ $product->is_featured ? 'true' : 'false' }}" :aria-pressed="featured"
            title="{{ $product->is_featured ? 'Bỏ nổi bật' : 'Đánh dấu nổi bật' }}" :title="featured ? 'Bỏ nổi bật' : 'Đánh dấu nổi bật'">
        <x-icon name="star" class="size-5 {{ $product->is_featured ? 'text-amber-600' : 'text-gray-400' }}"
                x-bind:class="{ 'text-amber-600': featured, 'text-gray-400': !featured }" />
    </button>
    <p x-cloak x-show="error" x-text="error" role="alert" class="mt-1 max-w-40 whitespace-normal text-xs leading-5 text-red-600"></p>
    <span class="sr-only" role="status" x-text="message"></span>
</form>
