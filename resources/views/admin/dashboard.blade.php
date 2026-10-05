@extends('layouts.admin')
@section('title', 'Tổng quan')
@section('content')
    @php
        $fmt = fn ($n) => number_format((float) $n, 0, ',', '.');
        $tasks = collect([
            ['warn' => true, 'icon' => 'box', 'count' => $lowStockCount, 'title' => $lowStockCount.' sản phẩm sắp hết hàng', 'note' => 'Có biến thể tồn kho bằng hoặc dưới ngưỡng cảnh báo.', 'url' => route('admin.products.index', ['show' => 'low']), 'go' => 'Xem danh sách'],
            ['warn' => true, 'icon' => 'close', 'count' => $outOfStockCount, 'title' => $outOfStockCount.' sản phẩm hết hàng nhưng đang bán', 'note' => 'Khách vẫn thấy sản phẩm nhưng không mua được.', 'url' => route('admin.products.index', ['show' => 'out']), 'go' => 'Xem danh sách'],
            ['warn' => false, 'icon' => 'image', 'count' => $withoutImagesCount, 'title' => $withoutImagesCount.' sản phẩm chưa có ảnh', 'note' => $withoutImages->implode(', '), 'url' => route('admin.products.index', ['show' => 'no_image']), 'go' => 'Thêm ảnh'],
        ])->filter(fn ($task) => $task['count'] > 0)->values();
    @endphp

    <div class="page-header">
        <div>
            <h1>Tổng quan</h1>
            <p class="mt-1 text-sm text-gray-600">{{ $tasks->isEmpty() ? 'Không có việc nào cần xử lý lúc này.' : 'Có '.$tasks->count().' việc cần xem.' }}</p>
        </div>
    </div>

    <div class="admin-split">
        <section class="admin-panel" aria-labelledby="todo-title">
            <div class="admin-panel-heading">
                <h2 id="todo-title">Cần xử lý</h2>
                @if ($tasks->isNotEmpty())<span class="admin-status admin-status-warning"><span aria-hidden="true"></span>{{ $tasks->count() }} việc</span>@endif
            </div>
            @forelse ($tasks as $task)
                <div @class(['admin-todo', 'admin-todo-warn' => $task['warn']])>
                    <span class="admin-todo-icon"><x-icon :name="$task['icon']" class="size-4" /></span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold">{{ $task['title'] }}</p>
                        @if ($task['note'])<p class="mt-0.5 truncate text-xs text-gray-600">{{ $task['note'] }}</p>@endif
                    </div>
                    <a href="{{ $task['url'] }}" class="admin-todo-go">{{ $task['go'] }} →</a>
                </div>
            @empty
                <div class="px-5 py-12 text-center">
                    <span class="admin-todo-icon mx-auto"><x-icon name="check" class="size-4" /></span>
                    <p class="mt-3 text-sm font-semibold">Mọi thứ đều ổn</p>
                    <p class="mt-1 text-xs text-gray-600">Kho đủ hàng và mọi sản phẩm đang bán đều có ảnh.</p>
                </div>
            @endforelse
        </section>

        <div>
        <p class="mb-2 text-xs font-semibold tracking-wide text-gray-600 uppercase">Toàn thời gian</p>
        <dl class="grid gap-3 sm:grid-cols-3 xl:grid-cols-1" aria-label="Số liệu tổng quan">
            <div class="admin-kpi"><dt>Tổng doanh thu</dt><dd>{{ $fmt($totalRevenue) }} ₫</dd><dd>Đơn đã giao và đã thanh toán, gồm phí vận chuyển.</dd></div>
            <div class="admin-kpi"><dt>Tổng đơn hàng</dt><dd>{{ $fmt($totalOrders) }}</dd><dd>{{ $totalOrders === 0 ? 'Chưa có đơn hàng nào.' : 'Gồm cả đơn đã huỷ và trả hàng.' }}</dd></div>
            <div class="admin-kpi"><dt>Tổng sản phẩm</dt><dd>{{ $fmt($totalProducts) }}</dd><dd>{{ $fmt($activeProducts) }} đang bán · {{ $fmt($categoryCount) }} danh mục</dd></div>
        </dl>
        </div>
    </div>
@endsection
