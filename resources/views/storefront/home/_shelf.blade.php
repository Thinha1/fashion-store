{{-- One home-page shelf: four products in view, the arrows slide through
     them and the forward arrow fetches the next four once the end is
     reached. Without script the row still scrolls by touch and the link
     leads to the full catalogue. --}}
@if ($page->isNotEmpty())
    <section class="section" aria-labelledby="{{ $id }}-title">
        <div class="container" x-data="shelfCarousel({ next: {{ Js::from($page->hasMorePages() ? route('home.shelf', array_filter(['shelf' => $shelf, 'page' => 2, 'except' => implode(',', $except ?? [])])) : null) }} })">
            <div class="section-head opens">
                <h2 id="{{ $id }}-title">{{ $title }}</h2>
                <div class="carousel-controls">
                    <a href="{{ $moreUrl }}" class="text-link">{{ $moreLabel }}</a>
                    <div class="carousel-arrows" x-cloak>
                        <button type="button" class="carousel-btn" x-on:click="prev()" :disabled="atStart" aria-label="Sản phẩm trước"><x-icon name="chevron-left" /></button>
                        <button type="button" class="carousel-btn" x-on:click="next()" :disabled="atEnd && !nextUrl" :aria-busy="Boolean(loading).toString()" aria-label="Sản phẩm tiếp theo"><x-icon name="chevron-right" /></button>
                    </div>
                </div>
            </div>
            <ul class="carousel" x-ref="track" x-on:scroll.debounce.80ms="update()" tabindex="0" aria-label="{{ $title }}, cuộn ngang để xem thêm">
                @include('storefront.home._shelf-items', ['products' => $page->items()])
            </ul>
            <p class="visually-hidden" aria-live="polite" x-text="status"></p>
        </div>
    </section>
@endif
