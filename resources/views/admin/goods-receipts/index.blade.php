@extends('layouts.admin')

@section('title', 'Nhập hàng')

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Nhập hàng',
        'subtitle' => 'Theo dõi phiếu nhập. Chỉ phiếu đã xác nhận mới làm tăng tồn kho.',
        'actionUrl' => route('admin.goods-receipts.create'),
        'actionLabel' => 'Thêm phiếu nhập',
    ])

    <x-excel-tools resource="goods-receipts" />

    <x-admin-table :paginator="$receipts" :sorting="$sorting"
        :sortable="['Số phiếu' => 'receipt_number', 'Nhà cung cấp' => 'supplier', 'Dòng hàng' => 'items_count', 'Tổng chi phí' => 'total_cost', 'Trạng thái' => 'status', 'Xác nhận bởi' => 'confirmed_by']"
        :header="['Số phiếu', 'Nhà cung cấp', 'Dòng hàng', 'Tổng chi phí', 'Trạng thái', 'Xác nhận bởi', 'Thao tác']">
        @forelse ($receipts as $receipt)
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900">
                    <a href="{{ route('admin.goods-receipts.show', $receipt) }}" class="hover:underline">{{ $receipt->receipt_number }}</a>
                </td>
                <td class="px-4 py-3 text-gray-600">{{ $receipt->supplier?->name ?? '—' }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $receipt->items_count }}</td>
                <td class="px-4 py-3 text-gray-600">{{ number_format((float) $receipt->total_cost, 0) }} ₫</td>
                <td class="px-4 py-3">
                    <x-admin.status :value="$receipt->status" />
                </td>
                <td class="px-4 py-3 text-gray-600">
                    {{ $receipt->confirmedBy?->name ?? '—' }}
                    @if ($receipt->confirmed_at)
                        <span class="text-xs text-gray-400">({{ $receipt->confirmed_at->format('d/m/Y H:i') }})</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-right"><div class="admin-row-actions">
                    @if ($receipt->status === 'draft')
                        <a href="{{ route('admin.goods-receipts.edit', $receipt) }}" class="admin-row-action"><x-icon name="edit" class="size-3.5" /> Sửa</a>
                        <form method="POST" action="{{ route('admin.goods-receipts.confirm', $receipt) }}" class="inline"
                              x-on:submit.prevent="$dispatch('admin-confirm', { form: $el, message: 'Xác nhận phiếu nhập này? Tồn kho sẽ được cập nhật.' })">
                            @csrf
                            <button type="submit" class="admin-row-action admin-row-confirm"><x-icon name="check" class="size-3.5" /> Xác nhận</button>
                        </form>
                    @endif
                </div></td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="px-4 py-8 text-center text-gray-500">Chưa có phiếu nhập nào.</td>
            </tr>
        @endforelse
    </x-admin-table>
@endsection
