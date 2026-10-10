@extends('layouts.admin')

@section('title', 'Duyệt thanh toán')

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Duyệt thanh toán',
        'subtitle' => 'Chứng từ chuyển khoản khách gửi khi webhook SePay chưa tự khớp được. Chứng từ gửi sớm nhất ở trên cùng.',
    ])

    <x-admin-table :paginator="$orders"
        :header="['Mã đơn', 'Khách hàng', 'Số tiền đơn', 'Mã giao dịch', 'Gửi lúc', 'Thao tác']">
        @forelse ($orders as $order)
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900">
                    <a href="{{ route('admin.payments.show', $order) }}" class="hover:underline">{{ $order->order_number }}</a>
                </td>
                <td class="px-4 py-3 text-gray-600">
                    {{ $order->customer_name }}
                    <span class="block text-xs text-gray-400">{{ $order->customer_phone }}</span>
                </td>
                <td class="px-4 py-3 font-medium text-gray-900"><x-money :amount="$order->grand_total" /></td>
                <td class="px-4 py-3 text-gray-600">{{ $order->transaction_code }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $order->payment_proof_submitted_at?->format('d/m/Y H:i') }}</td>
                <td class="px-4 py-3 text-right"><div class="admin-row-actions">
                    <a href="{{ route('admin.payments.show', $order) }}" class="admin-row-action"><x-icon name="eye" class="size-3.5" /> Xem & duyệt</a>
                </div></td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="px-4 py-8 text-center text-gray-500">Không có chứng từ nào chờ duyệt.</td>
            </tr>
        @endforelse
    </x-admin-table>
@endsection
