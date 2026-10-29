@extends('layouts.admin')

@section('title', $customer->name)

@section('content')
    @include('admin.partials.page-header', [
        'title' => $customer->name,
        'subtitle' => 'Đăng ký ngày '.$customer->created_at->format('d/m/Y'),
    ])

    @php($canOpenOrders = auth()->user()->hasPermission('orders.view'))

    <div class="admin-detail-grid">
        <div class="space-y-6">
            <dl class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="admin-panel p-4"><dt class="text-xs text-gray-500">Số đơn</dt><dd class="mt-1 text-xl font-semibold text-gray-900">{{ $stats['orders'] }}</dd></div>
                <div class="admin-panel p-4"><dt class="text-xs text-gray-500">Đã chi (đơn đã giao, đã thanh toán)</dt><dd class="mt-1 text-xl font-semibold text-gray-900"><x-money :amount="$stats['spent']" /></dd></div>
                <div class="admin-panel p-4"><dt class="text-xs text-gray-500">Trung bình mỗi đơn</dt><dd class="mt-1 text-xl font-semibold text-gray-900"><x-money :amount="$stats['average']" /></dd></div>
                <div class="admin-panel p-4"><dt class="text-xs text-gray-500">Đơn gần nhất</dt><dd class="mt-1 text-xl font-semibold text-gray-900">{{ $stats['last_order_at'] ? \Illuminate\Support\Carbon::parse($stats['last_order_at'])->format('d/m/Y') : '—' }}</dd></div>
            </dl>

            <x-admin.panel title="Lịch sử đơn hàng" icon="orders">
                <x-admin-table :paginator="$orders" :header="['Mã đơn', 'Ngày đặt', 'Tổng tiền', 'Thanh toán', 'Trạng thái']">
                    @forelse ($orders as $order)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900">
                                @if ($canOpenOrders)
                                    <a href="{{ route('admin.orders.show', $order) }}" class="hover:underline">{{ $order->order_number }}</a>
                                @else
                                    {{ $order->order_number }}
                                @endif
                                <span class="block text-xs font-normal text-gray-400">{{ $order->items_count }} dòng hàng</span>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $order->placed_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3 font-medium text-gray-900"><x-money :amount="$order->grand_total" /></td>
                            <td class="px-4 py-3 text-xs text-gray-600">
                                {{ $order->payment_method === 'cod' ? 'COD' : 'Chuyển khoản' }}
                                <span class="block text-gray-400">{{ $order->paymentStatusLabel() }}</span>
                            </td>
                            <td class="px-4 py-3"><x-admin.order-status :status="$order->status" /></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-gray-500">Khách chưa có đơn hàng nào.</td>
                        </tr>
                    @endforelse
                </x-admin-table>
            </x-admin.panel>
        </div>

        <div class="space-y-6">
            <x-admin.panel title="Thông tin liên hệ" icon="user">
                <dl class="space-y-3 text-sm">
                    <div><dt class="text-xs text-gray-500">Email</dt><dd class="break-all text-gray-900">{{ $customer->email }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Số điện thoại</dt><dd class="text-gray-900">{{ $customer->phone ?: '—' }}</dd></div>
                </dl>
            </x-admin.panel>

            <x-admin.panel title="Sổ địa chỉ ({{ $addresses->count() }})" icon="home">
                @forelse ($addresses as $address)
                    <div @class(['text-sm', 'mt-4 border-t border-gray-100 pt-4' => ! $loop->first])>
                        <p class="font-medium text-gray-900">
                            {{ $address->label ?: 'Địa chỉ' }}
                            @if ($address->is_default)
                                <span class="ml-1 rounded bg-brand-soft px-1.5 py-0.5 text-xs font-normal text-brand">Mặc định</span>
                            @endif
                        </p>
                        <p class="text-gray-600">{{ $address->recipient_name }} · {{ $address->phone }}</p>
                        <p class="text-gray-600">{{ $address->fullAddress() }}</p>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">Khách chưa lưu địa chỉ nào.</p>
                @endforelse
            </x-admin.panel>
        </div>
    </div>
@endsection
