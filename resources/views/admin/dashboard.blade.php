@extends('layouts.admin')
@section('title', 'Tổng quan')
@section('content')
    <div class="page-header">
        <div>
            <h1>Báo cáo tổng quan</h1>
            <p class="mt-2 text-sm text-gray-500">Theo dõi doanh thu, đơn hàng và sản phẩm của cửa hàng.</p>
        </div>
        <span class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white px-4 py-2 text-xs font-medium text-gray-600">
            <x-icon name="calendar" class="size-4 text-brand" /> Toàn thời gian
        </span>
    </div>

    <section aria-label="Số liệu tổng quan" class="grid gap-5 md:grid-cols-3">
        @foreach ([
            ['title' => 'Tổng doanh thu', 'value' => $totalRevenue, 'unit' => '₫', 'icon' => 'revenue', 'note' => 'Tổng tiền đơn đã giao và đã thanh toán, gồm phí vận chuyển.', 'primary' => true],
            ['title' => 'Tổng đơn hàng', 'value' => $totalOrders, 'unit' => 'đơn', 'icon' => 'orders', 'note' => 'Tất cả đơn đã tạo, gồm cả đơn đã huỷ và trả hàng.', 'primary' => false],
            ['title' => 'Tổng sản phẩm', 'value' => $totalProducts, 'unit' => 'sản phẩm', 'icon' => 'shirt', 'note' => 'Số mẫu sản phẩm hiện có; không tính bản ghi đã xoá và các biến thể.', 'primary' => false],
        ] as $metric)
            <article @class([
                'flex min-w-0 flex-col rounded-2xl border p-6',
                'border-brand bg-brand text-white' => $metric['primary'],
                'border-gray-200 bg-white text-gray-900' => ! $metric['primary'],
            ])>
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold">{{ $metric['title'] }}</h2>
                    <span @class(['flex size-10 shrink-0 items-center justify-center rounded-xl', 'bg-white/15 text-white' => $metric['primary'], 'bg-brand-soft text-brand' => ! $metric['primary']])>
                        <x-icon :name="$metric['icon']" class="size-5" />
                    </span>
                </div>
                <p class="my-6 flex flex-wrap items-baseline gap-x-2 gap-y-1">
                    <span class="break-all text-4xl font-semibold tracking-tight tabular-nums">{{ number_format((float) $metric['value'], 0, ',', '.') }}</span>
                    <span @class(['text-sm', 'text-white/80' => $metric['primary'], 'text-gray-500' => ! $metric['primary']])>{{ $metric['unit'] }}</span>
                </p>
                <p @class(['mt-auto border-t pt-4 text-xs leading-5', 'border-white/20 text-white/80' => $metric['primary'], 'border-gray-100 text-gray-500' => ! $metric['primary']])>{{ $metric['note'] }}</p>
            </article>
        @endforeach
    </section>
@endsection
