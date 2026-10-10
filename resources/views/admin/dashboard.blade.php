@extends('layouts.admin')
@section('title', 'Tổng quan')
@section('content')
    @php
        $user = auth()->user();
        $canWorkOrders = $user->hasPermission('orders.manage');
        $bucketNoun = ['hour' => 'giờ', 'day' => 'ngày', 'month' => 'tháng'][$period->granularity()];
        $salesIsEmpty = collect($salesSeries)->every(fn ($point) => $point['sales'] === 0);
        $maxProductQuantity = max(1, collect($topProducts)->max('quantity') ?? 0);
        $maxStatusCount = max(1, collect($statusBreakdown)->max('count') ?? 0);
        $pipelineColour = array_combine(\App\Services\Reports\SalesDashboard::PIPELINE, ['var(--viz-pipeline-1)', 'var(--viz-pipeline-2)', 'var(--viz-pipeline-3)', 'var(--viz-pipeline-4)', 'var(--viz-pipeline-5)']);
        $kpis = [
            ['title' => 'Tổng doanh thu', 'value' => (float) $totalRevenue, 'money' => true, 'change' => $changes['revenue'] ?? null, 'comparable' => true, 'hero' => true],
            ['title' => 'Tổng đơn hàng', 'value' => $totalOrders, 'money' => false, 'unit' => 'đơn', 'change' => $changes['orders'] ?? null, 'comparable' => true, 'hero' => false],
            ['title' => 'Giá trị đơn trung bình', 'value' => $totals['averageOrderValue'], 'money' => true, 'change' => $changes['averageOrderValue'] ?? null, 'comparable' => true, 'hero' => false],
            ['title' => 'Tổng sản phẩm', 'value' => $totalProducts, 'money' => false, 'unit' => 'sản phẩm', 'change' => null, 'comparable' => false, 'hero' => false],
        ];
    @endphp

    <div class="viz-root space-y-6">
        <div class="page-header !mb-0">
            <div>
                <h1>Tổng quan</h1>
                <p class="mt-1 text-sm text-gray-500">Số liệu {{ mb_strtolower($period->label()) }}{{ $period->start ? ' (từ '.$period->start->format('d/m/Y').')' : '' }} · cập nhật lúc {{ now()->format('H:i') }}</p>
            </div>
        </div>

        {{-- One filter row above everything it scopes. --}}
        <nav aria-label="Khoảng thời gian" class="flex flex-wrap items-center gap-2">
            <x-icon name="calendar" class="size-4 text-gray-400" />
            @foreach (\App\Services\Reports\DashboardPeriod::PRESETS as $key => $label)
                <a href="{{ route('admin.dashboard', ['khoang' => $key]) }}"
                   @if ($period->key === $key) aria-current="page" @endif
                   @class([
                       'inline-flex min-h-9 items-center gap-1.5 rounded-full border px-3.5 text-xs font-semibold',
                       'border-gray-900 bg-gray-900 text-white' => $period->key === $key,
                       'border-gray-200 bg-white text-gray-600 hover:border-gray-300 hover:text-gray-900' => $period->key !== $key,
                   ])>
                    @if ($period->key === $key)<x-icon name="check" class="size-3" />@endif
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        {{-- What is waiting for someone right now (not tied to the period). --}}
        @php
            $actions = array_filter([
                $actionCounts['pendingOrders'] > 0 ? ['count' => $actionCounts['pendingOrders'], 'label' => 'đơn chờ xác nhận', 'icon' => 'warning', 'tone' => 'warning', 'href' => $canWorkOrders ? route('admin.orders.index', ['status' => 'pending']) : null] : null,
                $actionCounts['pendingPaymentReviews'] > 0 ? ['count' => $actionCounts['pendingPaymentReviews'], 'label' => 'chứng từ chờ duyệt', 'icon' => 'warning', 'tone' => 'warning', 'href' => $user->hasPermission('payments.manage') ? route('admin.payments.index') : null] : null,
                $actionCounts['outOfStock'] > 0 ? ['count' => $actionCounts['outOfStock'], 'label' => 'biến thể đã hết hàng', 'icon' => 'circle-x', 'tone' => 'critical', 'href' => '#sap-het-hang'] : null,
                $actionCounts['lowStock'] - $actionCounts['outOfStock'] > 0 ? ['count' => $actionCounts['lowStock'] - $actionCounts['outOfStock'], 'label' => 'biến thể sắp hết', 'icon' => 'warning', 'tone' => 'warning', 'href' => '#sap-het-hang'] : null,
            ]);
        @endphp
        <section aria-label="Cần xử lý" class="flex flex-wrap gap-3">
            @forelse ($actions as $action)
                <a @if ($action['href']) href="{{ $action['href'] }}" @endif
                   class="inline-flex min-h-11 items-center gap-2.5 rounded-xl border border-gray-200 bg-white px-4 text-sm text-gray-700 {{ $action['href'] ? 'hover:border-gray-300 hover:text-gray-900' : '' }}">
                    <x-icon :name="$action['icon']" class="size-4" style="color: var(--viz-status-{{ $action['tone'] }})" />
                    <span><strong class="font-semibold text-gray-900">{{ $action['count'] }}</strong> {{ $action['label'] }}</span>
                    @if ($action['href'])<x-icon name="arrow" class="size-3 text-gray-400" />@endif
                </a>
            @empty
                <p class="inline-flex items-center gap-2 text-sm text-gray-500"><x-icon name="check" class="size-4 text-green-700" /> Không có việc nào đang chờ xử lý.</p>
            @endforelse
        </section>

        {{-- KPI row: one hero figure, the rest as stat tiles. --}}
        <section aria-label="Số liệu chính" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($kpis as $kpi)
                @php($kpiValue = number_format($kpi['value'], 0, ',', '.'))
                @php($kpiUnit = $kpi['money'] ? '₫' : $kpi['unit'])
                <article class="viz-kpi flex min-w-0 flex-col rounded-xl border border-gray-200 bg-white p-5">
                    <h2 class="text-sm font-medium text-gray-600">{{ $kpi['title'] }}</h2>
                    {{-- The figure shrinks to fit the card: --kpi-fit is its width in characters, the small unit counting as half. --}}
                    <p class="mt-3 flex flex-wrap items-baseline gap-x-1.5" style="--kpi-fit: {{ mb_strlen($kpiValue) + 0.5 + mb_strlen($kpiUnit) * 0.5 }}; --kpi-max: {{ $kpi['hero'] ? '3rem' : '1.875rem' }}">
                        <span class="viz-kpi-value font-semibold tracking-tight text-gray-900">{{ $kpiValue }}</span>
                        <span class="text-sm text-gray-500">{{ $kpiUnit }}</span>
                    </p>
                    @if ($period->previous() && $kpi['comparable'])
                        <p class="mt-2 flex flex-wrap items-center gap-x-1.5 text-xs">
                            @if ($kpi['change'] === null)
                                <span class="text-gray-500">Kỳ trước chưa có số liệu để so sánh</span>
                            @else
                                <span class="inline-flex items-center gap-1 font-semibold" style="color: {{ $kpi['change'] > 0 ? 'var(--viz-delta-up)' : ($kpi['change'] < 0 ? 'var(--viz-delta-down)' : '#5c7080') }}">
                                    @if ($kpi['change'] != 0)<x-icon :name="$kpi['change'] > 0 ? 'trend-up' : 'trend-down'" class="size-3" />@endif
                                    {{ ($kpi['change'] > 0 ? '+' : '').number_format($kpi['change'], 1, ',', '.') }}%
                                </span>
                                <span class="text-gray-500">{{ $period->comparisonLabel() }}</span>
                            @endif
                        </p>
                    @endif
                </article>
            @endforeach
        </section>

        {{-- Sales over time: line + wash, crosshair tooltip, table twin. --}}
        <section class="admin-panel" aria-labelledby="sales-chart-title"
                 x-data="salesChart({ points: {{ Js::from($salesSeries) }} })">
            <div class="admin-panel-heading">
                <div>
                    <h2 id="sales-chart-title"><x-icon name="chart" class="size-4 text-brand" /> Doanh số theo {{ $bucketNoun }}</h2>
                    <p>Tổng tiền các đơn đã đặt, không tính đơn huỷ · {{ mb_strtolower($period->label()) }}</p>
                </div>
                <div class="inline-flex rounded-lg border border-gray-200 p-0.5 text-xs font-semibold" role="group" aria-label="Cách hiển thị">
                    <button type="button" class="inline-flex min-h-8 items-center gap-1.5 rounded-md px-3" :class="view === 'chart' ? 'bg-gray-900 text-white' : 'text-gray-600 hover:text-gray-900'"
                            :aria-pressed="view === 'chart'" x-on:click="view = 'chart'; $nextTick(() => draw())"><x-icon name="chart" class="size-3" /> Biểu đồ</button>
                    <button type="button" class="inline-flex min-h-8 items-center gap-1.5 rounded-md px-3" :class="view === 'table' ? 'bg-gray-900 text-white' : 'text-gray-600 hover:text-gray-900'"
                            :aria-pressed="view === 'table'" x-on:click="view = 'table'"><x-icon name="table" class="size-3" /> Bảng</button>
                </div>
            </div>
            <div class="admin-panel-body">
                <div x-show="view === 'chart'">
                    <div x-ref="frame" class="viz-chart-frame" style="height: 260px" tabindex="0" role="slider"
                         aria-label="Biểu đồ doanh số theo {{ $bucketNoun }}. Dùng phím mũi tên trái phải để xem từng mốc; bấm nút Bảng để xem toàn bộ số liệu."
                         aria-valuemin="0" :aria-valuemax="points.length - 1" :aria-valuenow="active ?? points.length - 1"
                         :aria-valuetext="activePoint ? activePoint.longLabel + ': ' + formatVnd(activePoint.sales) + ', ' + activePoint.orders + ' đơn' : ''"
                         x-on:pointermove="pointerMove($event)" x-on:pointerleave="clear()"
                         x-on:focus="moveTo(points.length - 1)" x-on:blur="clear()" x-on:keydown="keydown($event)">
                        <svg x-ref="svg" class="block" aria-hidden="true"></svg>
                        @if ($salesIsEmpty)
                            <p class="absolute inset-x-0 top-1/2 -translate-y-1/2 text-center text-sm text-gray-500">Chưa có đơn hàng nào trong khoảng thời gian này.</p>
                        @endif
                        <div class="viz-tooltip" x-show="activePoint" x-cloak :style="tooltipStyle" aria-hidden="true">
                            <template x-if="activePoint">
                                <div>
                                    <p class="text-base font-semibold text-gray-900" x-text="formatVnd(activePoint.sales)"></p>
                                    <p class="mt-0.5 flex items-center gap-1.5 text-xs text-gray-500"><span class="viz-key-line"></span> <span x-text="activePoint.longLabel"></span></p>
                                    <p class="text-xs text-gray-500" x-text="activePoint.orders + ' đơn'"></p>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
                <div x-show="view === 'table'" x-cloak class="max-h-80 overflow-y-auto">
                    <table class="w-full text-sm">
                        <caption class="sr-only">Doanh số theo {{ $bucketNoun }}</caption>
                        <thead class="sticky top-0 bg-white text-xs text-gray-500">
                            <tr><th scope="col" class="py-2 text-left font-medium">Mốc thời gian</th><th scope="col" class="py-2 text-right font-medium">Số đơn</th><th scope="col" class="py-2 text-right font-medium">Doanh số</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 tabular-nums">
                            @foreach ($salesSeries as $point)
                                <tr><td class="py-1.5 text-gray-700">{{ $point['longLabel'] }}</td><td class="py-1.5 text-right text-gray-700">{{ $point['orders'] }}</td><td class="py-1.5 text-right text-gray-900">{{ number_format($point['sales'], 0, ',', '.') }} ₫</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            {{-- Best sellers: one series, one hue, value column at the bar end. --}}
            <x-admin.panel title="Sản phẩm bán chạy" icon="shirt" :description="'Theo số lượng bán · '.mb_strtolower($period->label())">
                @if ($topProducts === [])
                    <p class="py-6 text-center text-sm text-gray-500">Chưa có sản phẩm nào được bán trong khoảng này.</p>
                @else
                    <ol class="space-y-3.5">
                        @foreach ($topProducts as $product)
                            <li>
                                <x-admin.bar-row aria-label="{{ $product['name'] }}: {{ $product['quantity'] }} sản phẩm, doanh số {{ number_format($product['sales'], 0, ',', '.') }} đồng">
                                <p class="truncate text-sm text-gray-900">{{ $product['name'] }}</p>
                                <div class="mt-1.5 flex items-center gap-3">
                                    <div class="viz-bar-track flex-1"><div class="viz-bar" style="width: {{ round($product['quantity'] / $maxProductQuantity * 100, 2) }}%; background: var(--viz-series-1)"></div></div>
                                    <span class="w-14 shrink-0 text-right text-sm font-semibold text-gray-900 tabular-nums">{{ $product['quantity'] }}</span>
                                </div>
                                <div class="viz-bar-tip viz-tooltip !relative !transform-none !min-w-0">
                                    <p class="text-sm font-semibold text-gray-900">{{ number_format($product['sales'], 0, ',', '.') }} ₫</p>
                                    <p class="text-xs text-gray-500">Doanh số · {{ $product['quantity'] }} sản phẩm</p>
                                </div>
                                </x-admin.bar-row>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-admin.panel>

            {{-- Order pipeline: ordinal ramp (darker = further along); cancelled / returned in grey. --}}
            <x-admin.panel title="Đơn hàng theo trạng thái" icon="orders" :description="'Đơn đặt trong '.mb_strtolower($period->label()).', theo trạng thái hiện tại'">
                <ol class="space-y-3.5">
                    @foreach ($statusBreakdown as $row)
                        @if (! $row['inPipeline'] && $loop->index === count(\App\Services\Reports\SalesDashboard::PIPELINE))
                            <li class="border-t border-gray-100" aria-hidden="true"></li>
                        @endif
                        @php($share = $totalOrders > 0 ? round($row['count'] / $totalOrders * 100) : 0)
                        <li>
                            <x-admin.bar-row :href="$canWorkOrders ? route('admin.orders.index', ['status' => $row['status']]) : null"
                                aria-label="{{ $row['label'] }}: {{ $row['count'] }} đơn, {{ $share }}% tổng số đơn">
                                <p class="flex items-center justify-between text-sm"><span class="text-gray-900">{{ $row['label'] }}</span></p>
                                <div class="mt-1.5 flex items-center gap-3">
                                    <div class="viz-bar-track flex-1">
                                        @if ($row['count'] > 0)
                                            <div class="viz-bar" style="width: {{ round($row['count'] / $maxStatusCount * 100, 2) }}%; background: {{ $row['inPipeline'] ? $pipelineColour[$row['status']] : 'var(--viz-deemphasis)' }}"></div>
                                        @endif
                                    </div>
                                    <span class="w-14 shrink-0 text-right text-sm font-semibold text-gray-900 tabular-nums">{{ $row['count'] }}</span>
                                </div>
                                <div class="viz-bar-tip viz-tooltip !relative !transform-none !min-w-0">
                                    <p class="text-sm font-semibold text-gray-900">{{ $share }}%</p>
                                    <p class="text-xs text-gray-500">tổng số đơn trong khoảng này</p>
                                </div>
                            </x-admin.bar-row>
                        </li>
                    @endforeach
                </ol>
            </x-admin.panel>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <x-admin.panel id="sap-het-hang" title="Sắp hết hàng" icon="box" description="Biến thể đang bán có tồn kho bằng hoặc dưới ngưỡng cảnh báo">
                @if ($lowStockVariants->isEmpty())
                    <p class="flex items-center justify-center gap-2 py-6 text-sm text-gray-500"><x-icon name="check" class="size-4 text-green-700" /> Tồn kho đang ổn, không có biến thể nào dưới ngưỡng.</p>
                @else
                    <table class="w-full text-sm">
                        <thead class="text-xs text-gray-500">
                            <tr><th scope="col" class="pb-2 text-left font-medium">Sản phẩm</th><th scope="col" class="pb-2 text-right font-medium">Tồn / ngưỡng</th><th scope="col" class="pb-2 pl-4 text-left font-medium">Trạng thái</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($lowStockVariants as $variant)
                                <tr>
                                    <td class="py-2.5 pr-3">
                                        @if ($user->hasPermission('products.manage') && $variant->product)
                                            <a href="{{ route('admin.products.edit', $variant->product_id) }}" class="font-medium text-gray-900 hover:underline">{{ $variant->product->name }}</a>
                                        @else
                                            <span class="font-medium text-gray-900">{{ $variant->product?->name }}</span>
                                        @endif
                                        <span class="block text-xs text-gray-500">{{ $variant->color }} · {{ $variant->size }} · {{ $variant->sku }}</span>
                                    </td>
                                    <td class="py-2.5 text-right text-gray-900 tabular-nums">{{ $variant->stock_quantity }} <span class="text-gray-400">/ {{ $variant->low_stock_threshold }}</span></td>
                                    <td class="py-2.5 pl-4">
                                        @if ($variant->stock_quantity <= 0)
                                            <span class="inline-flex items-center gap-1.5 whitespace-nowrap text-xs font-medium text-gray-900"><x-icon name="circle-x" class="size-3.5" style="color: var(--viz-status-critical)" /> Hết hàng</span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 whitespace-nowrap text-xs font-medium text-gray-900"><x-icon name="warning" class="size-3.5" style="color: var(--viz-status-warning)" /> Sắp hết</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if ($actionCounts['lowStock'] > $lowStockVariants->count())
                        <p class="mt-3 text-xs text-gray-500">Đang hiện {{ $lowStockVariants->count() }} / {{ $actionCounts['lowStock'] }} biến thể, ít hàng nhất trước.</p>
                    @endif
                @endif
            </x-admin.panel>

            <x-admin.panel title="Đơn hàng mới nhất" icon="clock">
                @if ($latestOrders->isEmpty())
                    <p class="py-6 text-center text-sm text-gray-500">Chưa có đơn hàng nào.</p>
                @else
                    <table class="w-full text-sm">
                        <thead class="text-xs text-gray-500">
                            <tr><th scope="col" class="pb-2 text-left font-medium">Đơn</th><th scope="col" class="pb-2 text-right font-medium">Tổng tiền</th><th scope="col" class="pb-2 pl-4 text-left font-medium">Trạng thái</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($latestOrders as $order)
                                <tr>
                                    <td class="py-2.5 pr-3">
                                        @if ($canWorkOrders)
                                            <a href="{{ route('admin.orders.show', $order) }}" class="font-medium text-gray-900 hover:underline">{{ $order->order_number }}</a>
                                        @else
                                            <span class="font-medium text-gray-900">{{ $order->order_number }}</span>
                                        @endif
                                        <span class="block text-xs text-gray-500">{{ $order->customer_name }} · {{ $order->placed_at->format('H:i d/m') }}</span>
                                    </td>
                                    <td class="py-2.5 text-right text-gray-900 tabular-nums">{{ number_format((float) $order->grand_total, 0, ',', '.') }} ₫</td>
                                    <td class="py-2.5 pl-4"><x-admin.order-status :status="$order->status" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if ($canWorkOrders)
                        <a href="{{ route('admin.orders.index') }}" class="mt-3 inline-flex items-center gap-1.5 text-xs font-semibold text-brand hover:underline">Xem tất cả đơn hàng <x-icon class="size-3" /></a>
                    @endif
                @endif
            </x-admin.panel>
        </div>
    </div>
@endsection
