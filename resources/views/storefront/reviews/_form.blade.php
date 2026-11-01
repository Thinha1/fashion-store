{{-- Rating form for one delivered order line; lives inside the "Đánh giá" dialog of /tai-khoan/danh-gia. --}}
<form method="POST" action="{{ route('reviews.store', [$order, $item]) }}" class="review-form">
    @csrf
    <input type="hidden" name="order_item" value="{{ $item->id }}">
    <fieldset class="star-input">
        <legend class="caption">Chất lượng sản phẩm</legend>
        @for ($star = 5; $star >= 1; $star--)
            <input type="radio" name="rating" id="rating-{{ $item->id }}-{{ $star }}" value="{{ $star }}" @checked(old('order_item') == $item->id && (int) old('rating') === $star) required>
            <label for="rating-{{ $item->id }}-{{ $star }}" title="{{ $star }} sao"><x-icon name="star" /><span class="visually-hidden">{{ $star }} sao</span></label>
        @endfor
    </fieldset>
    <label for="comment-{{ $item->id }}" class="caption">Nhận xét (không bắt buộc)</label>
    <textarea id="comment-{{ $item->id }}" name="comment" rows="4" maxlength="1000" class="field" placeholder="Chất vải, form dáng, đúng size không…">{{ old('order_item') == $item->id ? old('comment') : '' }}</textarea>
    @if (old('order_item') == $item->id)
        <x-input-error :messages="$errors->get('rating')" />
        <x-input-error :messages="$errors->get('comment')" />
    @endif
    <button type="submit" class="btn btn-primary">Gửi đánh giá</button>
</form>
