@props(['paginator'])

<div class="flex flex-wrap items-center justify-between gap-4 border-t border-gray-200 px-4 py-4 sm:px-5">
    <div class="flex flex-wrap items-center gap-x-5 gap-y-3">
        <p class="text-xs text-gray-500" role="status">
            Hiển thị <span class="font-semibold text-gray-900">{{ number_format($paginator->firstItem() ?? 0, 0, ',', '.') }}–{{ number_format($paginator->lastItem() ?? 0, 0, ',', '.') }}</span>
            / {{ number_format($paginator->total(), 0, ',', '.') }} bản ghi
        </p>
        <form method="GET" action="{{ $paginator->path() }}" class="flex items-center gap-2">
            @foreach (request()->query() as $key => $value)
                @if (! in_array($key, [$paginator->getPageName(), 'per_page'], true) && is_scalar($value))
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endif
            @endforeach
            <label class="flex items-center gap-2 text-xs text-gray-500">
                Mỗi trang
                <select name="per_page" onchange="this.form.requestSubmit()" class="min-h-11 rounded-lg border border-gray-200 bg-white px-2.5 text-sm text-gray-900">
                    @foreach (\App\Support\AdminPagination::PAGE_SIZES as $size)
                        <option value="{{ $size }}" @selected($paginator->perPage() === $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </label>
            <noscript><button type="submit" class="btn btn-secondary">Áp dụng</button></noscript>
        </form>
    </div>
    {{ $paginator->onEachSide(1)->links('components.pagination-links') }}
</div>
