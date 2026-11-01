{{-- Published reviews of a product: the average, how many stars each got, then the comments. --}}
<section class="section reviews" id="danh-gia" aria-labelledby="reviews-title">
    <div class="container">
        <div class="section-head opens">
            <h2 id="reviews-title">Đánh giá của khách hàng</h2>
        </div>

        <div class="review-write">
            @if ($reviewableItems->isNotEmpty())
                <h3>Bạn đã nhận sản phẩm này</h3>
                <p class="caption">Chia sẻ trải nghiệm để giúp người mua sau chọn đúng size và kiểu dáng.</p>
                <a href="{{ route('reviews.index') }}" class="btn btn-primary">Đánh giá sản phẩm đã mua</a>
            @elseif (auth()->guest())
                <p class="caption"><a href="{{ route('login') }}" class="text-link">Đăng nhập</a> để đánh giá sản phẩm bạn đã mua.</p>
            @else
                <p class="caption">Bạn có thể đánh giá sau khi nhận được sản phẩm này.</p>
            @endif
        </div>

        @if ($reviewSummary['count'] === 0)
            <p class="caption">Sản phẩm chưa có đánh giá nào. Khách đã nhận hàng có thể đánh giá trong trang đơn hàng của mình.</p>
        @else
            <div class="reviews-layout">
                <div class="reviews-summary">
                    <p class="reviews-average">{{ number_format($reviewSummary['average'], 1, ',', '.') }}<span> / 5</span></p>
                    <x-storefront.stars :rating="$reviewSummary['average']" />
                    <p class="caption">{{ $reviewSummary['count'] }} đánh giá</p>
                    <dl class="reviews-bars">
                        @foreach ($reviewSummary['breakdown'] as $stars => $count)
                            <div>
                                <dt>{{ $stars }} sao</dt>
                                <dd><span class="reviews-bar"><span style="width: {{ round($count / $reviewSummary['count'] * 100) }}%"></span></span> {{ $count }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>

                <ul class="reviews-list" role="list">
                    @foreach ($reviews as $review)
                        <li class="review">
                            <div class="review-head">
                                <x-storefront.stars :rating="$review->rating" :label="$review->rating.' trên 5 sao'" />
                                <span class="choice-title">{{ \App\Support\ReviewerName::mask($review->user?->name) }}</span>
                                <time class="caption" datetime="{{ $review->created_at->toIso8601String() }}">{{ $review->created_at->format('d/m/Y') }}</time>
                            </div>
                            @if ($review->orderItem)
                                <p class="caption">Phân loại: {{ $review->orderItem->color_name }}, size {{ $review->orderItem->size_name }}</p>
                            @endif
                            @if ($review->comment)
                                <p class="review-comment">{{ $review->comment }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
            {{ $reviews->fragment('danh-gia')->links() }}
        @endif
    </div>
</section>
