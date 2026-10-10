@extends('layouts.admin')

@section('title', 'Đơn hàng')

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Đơn hàng',
        'subtitle' => 'Xử lý đơn theo từng bước: xác nhận → chuẩn bị → giao vận chuyển → đã giao.',
    ])

    @php($tabs = ['' => 'Tất cả'] + \App\Models\Order::STATUS_LABELS)
    @php($filters = array_filter(['q' => $search, 'payment_status' => $paymentStatus, 'payment_method' => $paymentMethod]))

    <nav aria-label="Lọc theo trạng thái" class="mb-3 flex flex-wrap gap-1.5">
        @foreach ($tabs as $value => $label)
            @php($count = $value === '' ? $statusCounts->sum() : ($statusCounts[$value] ?? 0))
            <a href="{{ route('admin.orders.index', array_filter(['status' => $value]) + $filters) }}"
               @if ((string) $status === (string) $value) aria-current="page" @endif
               @class([
                   'inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold',
                   'border-brand bg-brand text-white' => (string) $status === (string) $value,
                   'border-gray-200 bg-white text-gray-600 hover:border-brand hover:text-brand' => (string) $status !== (string) $value,
               ])>
                {{ $label }} <span class="opacity-75">{{ $count }}</span>
            </a>
        @endforeach
    </nav>

    <form method="GET" action="{{ route('admin.orders.index') }}" role="search" class="mb-4 flex flex-wrap items-center gap-2">
        @if ($status)
            <input type="hidden" name="status" value="{{ $status }}">
        @endif
        <label for="order-search" class="sr-only">Tìm đơn hàng</label>
        <input id="order-search" type="search" name="q" value="{{ $search }}" placeholder="Mã đơn, tên, SĐT, email…" class="field min-h-10 sm:w-64">
        <label for="payment-status-filter" class="sr-only">Trạng thái thanh toán</label>
        <select id="payment-status-filter" name="payment_status" class="field min-h-10 sm:w-auto" x-data x-on:change="$el.form.requestSubmit()">
            <option value="">Mọi trạng thái thanh toán</option>
            @foreach (\App\Models\Order::PAYMENT_STATUS_LABELS as $value => $label)
                <option value="{{ $value }}" @selected($paymentStatus === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <label for="payment-method-filter" class="sr-only">Hình thức thanh toán</label>
        <select id="payment-method-filter" name="payment_method" class="field min-h-10 sm:w-auto" x-data x-on:change="$el.form.requestSubmit()">
            <option value="">Mọi hình thức thanh toán</option>
            @foreach (\App\Models\Order::PAYMENT_METHOD_LABELS as $value => $label)
                <option value="{{ $value }}" @selected($paymentMethod === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <x-admin.action type="submit" variant="secondary" icon="search">Lọc</x-admin.action>
        @if ($filters)
            <a href="{{ route('admin.orders.index', array_filter(['status' => $status])) }}" class="px-2 text-sm font-medium text-gray-500 hover:text-gray-900 hover:underline">Xoá lọc</a>
        @endif
    </form>

    <x-admin-table :paginator="$orders" :sorting="$sorting"
        :sortable="['Mã đơn' => 'order_number', 'Khách hàng' => 'customer', 'Ngày đặt' => 'placed_at', 'Tổng tiền' => 'grand_total', 'Trạng thái' => 'status']"
        :header="['Mã đơn', 'Khách hàng', 'Ngày đặt', 'Tổng tiền', 'Thanh toán', 'Trạng thái', 'Thao tác']">
        @forelse ($orders as $order)
            @php($nextStep = collect($order->nextStaffStatuses())->first(fn ($next) => $next !== 'cancelled'))
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900">
                    <a href="{{ route('admin.orders.show', $order) }}" class="hover:underline">{{ $order->order_number }}</a>
                    <span class="block text-xs font-normal text-gray-400">{{ $order->items_count }} dòng hàng</span>
                </td>
                <td class="px-4 py-3 text-gray-600">
                    {{ $order->customer_name }}
                    <span class="block text-xs text-gray-400">{{ $order->customer_phone }}</span>
                </td>
                <td class="px-4 py-3 text-gray-600">{{ $order->placed_at->format('d/m/Y H:i') }}</td>
                <td class="px-4 py-3 font-medium text-gray-900"><x-money :amount="$order->grand_total" /></td>
                <td class="px-4 py-3 text-xs text-gray-600">
                    {{ $order->payment_method === 'cod' ? 'COD' : 'Chuyển khoản' }}
                    <span class="block text-gray-400">{{ $order->paymentStatusLabel() }}</span>
                </td>
                <td class="px-4 py-3"><x-admin.order-status :status="$order->status" /></td>
                <td class="px-4 py-3 text-right"><div class="admin-row-actions">
                    <a href="{{ route('admin.orders.show', $order) }}" class="admin-row-action"><x-icon name="eye" class="size-3.5" /> Xem</a>
                    @if ($nextStep)
                        <form method="POST" action="{{ route('admin.orders.status', $order) }}" class="inline"
                              x-on:submit.prevent="$dispatch('admin-confirm', { form: $el, message: {{ Js::from('Chuyển đơn '.$order->order_number.' sang "'.\App\Models\Order::STATUS_LABELS[$nextStep].'"?') }} })">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="{{ $nextStep }}">
                            <button type="submit" class="admin-row-action admin-row-confirm"><x-icon name="check" class="size-3.5" /> {{ \App\Models\Order::TRANSITION_LABELS[$nextStep] }}</button>
                        </form>
                    @endif
                </div></td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                    {{ $filters || $status ? 'Không có đơn hàng nào khớp bộ lọc.' : 'Chưa có đơn hàng nào.' }}
                </td>
            </tr>
        @endforelse
    </x-admin-table>
@endsection
