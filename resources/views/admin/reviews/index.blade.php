@extends('layouts.admin')

@section('title', 'Đánh giá sản phẩm')

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Đánh giá sản phẩm',
        'subtitle' => 'Đánh giá của khách đã nhận hàng chỉ hiện trên trang sản phẩm sau khi được duyệt. Ẩn những đánh giá vi phạm; nội dung không sửa được.',
    ])

    @if ($pendingCount > 0 && $status !== \App\Models\Review::STATUS_PENDING)
        <a href="{{ route('admin.reviews.index', ['trang-thai' => \App\Models\Review::STATUS_PENDING]) }}" class="mb-4 inline-flex items-center gap-2 text-sm font-medium text-amber-700 hover:underline">
            <x-icon name="warning" class="size-4" /> {{ $pendingCount }} đánh giá đang chờ duyệt
        </a>
    @endif

    @php($active = array_filter(['q' => $search, 'trang-thai' => $status, 'sao' => $rating]))

    <form method="GET" action="{{ route('admin.reviews.index') }}" role="search" class="mb-4 flex flex-wrap items-center gap-2">
        <label for="review-q" class="sr-only">Tìm đánh giá</label>
        <input id="review-q" type="search" name="q" value="{{ $search }}" placeholder="Sản phẩm, khách hàng hoặc nội dung" class="field min-h-10 sm:w-64">
        <label for="review-status" class="sr-only">Trạng thái</label>
        <select id="review-status" name="trang-thai" class="field min-h-10 sm:w-auto" x-data x-on:change="$el.form.requestSubmit()">
            <option value="">Mọi trạng thái</option>
            @foreach (\App\Models\Review::STATUS_LABELS as $value => $label)
                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <label for="review-rating" class="sr-only">Số sao</label>
        <select id="review-rating" name="sao" class="field min-h-10 sm:w-auto" x-data x-on:change="$el.form.requestSubmit()">
            <option value="">Mọi số sao</option>
            @for ($stars = 5; $stars >= 1; $stars--)
                <option value="{{ $stars }}" @selected($rating === $stars)>{{ $stars }} sao</option>
            @endfor
        </select>
        <x-admin.action type="submit" variant="secondary" icon="search">Lọc</x-admin.action>
        @if ($active)
            <a href="{{ route('admin.reviews.index') }}" class="px-2 text-sm font-medium text-gray-500 hover:text-gray-900 hover:underline">Xoá lọc</a>
        @endif
    </form>

    <x-admin-table :paginator="$reviews" :sorting="$sorting"
        :sortable="['Số sao' => 'rating', 'Ngày gửi' => 'created_at']"
        :header="['Sản phẩm', 'Khách hàng', 'Số sao', 'Nhận xét', 'Trạng thái', 'Ngày gửi', 'Thao tác']">
        @forelse ($reviews as $review)
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900">
                    {{ $review->product?->name ?? '—' }}
                    @if ($review->orderItem)
                        <span class="block text-xs font-normal text-gray-400">{{ $review->orderItem->color_name }}, size {{ $review->orderItem->size_name }}</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-gray-600">
                    {{ $review->user?->name }}
                    <span class="block text-xs text-gray-400">{{ $review->user?->email }}</span>
                </td>
                <td class="px-4 py-3 text-gray-900">{{ $review->rating }}/5</td>
                <td class="max-w-xs px-4 py-3 text-gray-600">{{ $review->comment ? \Illuminate\Support\Str::limit($review->comment, 160) : '—' }}</td>
                <td class="px-4 py-3 text-gray-600">
                    {{ $review->statusLabel() }}
                    @if ($review->updatedBy)
                        <span class="block text-xs text-gray-400">bởi {{ $review->updatedBy->name }}</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-gray-600">{{ $review->created_at->format('d/m/Y') }}</td>
                <td class="px-4 py-3 text-right">
                    <div class="admin-row-actions">
                        @if ($review->status !== \App\Models\Review::STATUS_PUBLISHED)
                            <form method="POST" action="{{ route('admin.reviews.update', $review) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="{{ \App\Models\Review::STATUS_PUBLISHED }}">
                                @if ($review->status === \App\Models\Review::STATUS_PENDING)
                                    <button type="submit" class="admin-row-action"><x-icon name="check" class="size-3.5" /> Duyệt</button>
                                @else
                                    <button type="submit" class="admin-row-action"><x-icon name="eye" class="size-3.5" /> Hiện lại</button>
                                @endif
                            </form>
                        @endif
                        @if ($review->status !== \App\Models\Review::STATUS_HIDDEN)
                            <form method="POST" action="{{ route('admin.reviews.update', $review) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="{{ \App\Models\Review::STATUS_HIDDEN }}">
                                <button type="submit" class="admin-row-action"><x-icon name="eye-slash" class="size-3.5" /> Ẩn</button>
                            </form>
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="px-4 py-8 text-center text-gray-500">{{ $active ? 'Không có đánh giá nào khớp bộ lọc.' : 'Chưa có đánh giá nào.' }}</td>
            </tr>
        @endforelse
    </x-admin-table>
@endsection
