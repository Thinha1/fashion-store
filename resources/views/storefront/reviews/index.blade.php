@extends('layouts.app')

@section('title', 'Sản phẩm đã mua — ' . config('app.name', 'Fashion Store'))

@section('content')
    <div class="container page narrow">
        <nav aria-label="Breadcrumb" class="crumbs">
            <ol>
                <li><a href="{{ route('profile.edit') }}">Tài khoản</a></li>
                <li aria-current="page">Sản phẩm đã mua</li>
            </ol>
        </nav>
        <header class="catalog-head">
            <div class="catalog-title">
                <h1>Sản phẩm đã mua</h1>
                <p class="caption">Sản phẩm trong các đơn đã giao. Đánh giá giúp người mua sau chọn đúng size và kiểu dáng.</p>
            </div>
        </header>

        <nav class="review-tabs" aria-label="Lọc sản phẩm đã mua">
            <a href="{{ route('reviews.index') }}" @if ($tab === 'pending') aria-current="page" @endif>Chờ đánh giá ({{ $pendingCount }})</a>
            <a href="{{ route('reviews.index', ['tab' => 'da-danh-gia']) }}" @if ($tab === 'done') aria-current="page" @endif>Đã đánh giá ({{ $doneCount }})</a>
        </nav>

        @if ($tab === 'pending')
            @if ($items->isEmpty())
                <div class="catalog-empty">
                    <h2>Không có sản phẩm nào đang chờ đánh giá.</h2>
                    <p>Sản phẩm sẽ hiện ở đây khi đơn hàng của bạn đã giao thành công.</p>
                    <a href="{{ route('products.index') }}" class="btn btn-primary">Tiếp tục mua sắm</a>
                </div>
            @else
                <ul class="purchased-list" role="list">
                    @foreach ($items as $item)
                        @php($product = $item->productVariant?->product)
                        @php($image = $product?->images->first())
                        <li class="purchased" x-data="{ open() { this.$refs.dialog.showModal() } }" @if (old('order_item') == $item->id) x-init="$nextTick(() => open())" @endif>
                            @if ($image)
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk(config('filesystems.image_disk'))->url($image->path) }}" alt="" width="64" height="80" loading="lazy" class="purchased-img">
                            @else
                                <span class="purchased-img" aria-hidden="true"></span>
                            @endif
                            <div class="purchased-body">
                                <p class="cart-name">
                                    @if ($product)
                                        <a href="{{ route('products.show', $product) }}">{{ $item->product_name }}</a>
                                    @else
                                        {{ $item->product_name }}
                                    @endif
                                </p>
                                <p class="caption">{{ $item->color_name }}, size {{ $item->size_name }}</p>
                                <p class="caption">Đơn <a href="{{ route('orders.show', $item->order) }}">{{ $item->order->order_number }}</a>, {{ $item->order->placed_at->format('d/m/Y') }}</p>
                            </div>
                            <button type="button" class="btn btn-primary" x-on:click="open()">Đánh giá</button>

                            <dialog x-ref="dialog" class="review-dialog" aria-labelledby="review-dialog-{{ $item->id }}"
                                    x-on:click="if ($event.target === $refs.dialog) $refs.dialog.close()">
                                <div class="review-dialog-head">
                                    <h2 id="review-dialog-{{ $item->id }}">Đánh giá sản phẩm</h2>
                                    <button type="button" class="review-dialog-close" x-on:click="$refs.dialog.close()" aria-label="Đóng"><x-icon name="close" /></button>
                                </div>
                                <p class="caption">{{ $item->product_name }} — {{ $item->color_name }}, size {{ $item->size_name }}</p>
                                @include('storefront.reviews._form', ['order' => $item->order, 'item' => $item])
                            </dialog>
                        </li>
                    @endforeach
                </ul>
                {{ $items->links() }}
            @endif
        @else
            @if ($reviews->isEmpty())
                <div class="catalog-empty">
                    <h2>Bạn chưa đánh giá sản phẩm nào.</h2>
                    <a href="{{ route('reviews.index') }}" class="btn btn-primary">Xem sản phẩm chờ đánh giá</a>
                </div>
            @else
                <ul class="reviews-list" role="list">
                    @foreach ($reviews as $review)
                        <li class="review">
                            <p class="cart-name">
                                @if ($review->product)
                                    <a href="{{ route('products.show', $review->product) }}">{{ $review->orderItem?->product_name ?? $review->product->name }}</a>
                                @endif
                            </p>
                            <div class="review-head">
                                <x-storefront.stars :rating="$review->rating" :label="$review->rating.' trên 5 sao'" />
                                <time class="caption" datetime="{{ $review->created_at->toIso8601String() }}">{{ $review->created_at->format('d/m/Y') }}</time>
                                @if ($review->status === \App\Models\Review::STATUS_PENDING)
                                    <span class="status">Chờ duyệt</span>
                                @elseif ($review->status === \App\Models\Review::STATUS_HIDDEN)
                                    <span class="status is-muted">Cửa hàng đã ẩn</span>
                                @endif
                            </div>
                            @if ($review->comment)
                                <p class="review-comment">{{ $review->comment }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
                {{ $reviews->links() }}
            @endif
        @endif
    </div>
@endsection
