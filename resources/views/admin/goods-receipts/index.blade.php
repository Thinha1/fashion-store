@extends('layouts.admin')

@section('title', 'Nhập hàng')

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Nhập hàng',
        'actions' => '<a href="'.route('admin.goods-receipts.create').'" class="inline-flex items-center rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">+ Thêm phiếu nhập</a>',
    ])

    <x-admin-table :header="['Số phiếu', 'Nhà cung cấp', 'Dòng hàng', 'Tổng chi phí', 'Trạng thái', 'Xác nhận bởi', 'Thao tác']">
        @forelse ($receipts as $receipt)
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900">
                    <a href="{{ route('admin.goods-receipts.show', $receipt) }}" class="hover:underline">{{ $receipt->receipt_number }}</a>
                </td>
                <td class="px-4 py-3 text-gray-600">{{ $receipt->supplier?->name ?? '—' }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $receipt->items_count }}</td>
                <td class="px-4 py-3 text-gray-600">{{ number_format((float) $receipt->total_cost, 0) }} ₫</td>
                <td class="px-4 py-3">
                    @if ($receipt->status === 'draft')
                        <span class="inline-flex rounded-full bg-yellow-50 px-2 py-0.5 text-xs font-medium text-yellow-700">Bản nháp</span>
                    @elseif ($receipt->status === 'confirmed')
                        <span class="inline-flex rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Đã xác nhận</span>
                    @else
                        <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">{{ $receipt->status }}</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-gray-600">
                    {{ $receipt->confirmedBy?->name ?? '—' }}
                    @if ($receipt->confirmed_at)
                        <span class="text-xs text-gray-400">({{ $receipt->confirmed_at->format('d/m/Y H:i') }})</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-right">
                    @if ($receipt->status === 'draft')
                        <a href="{{ route('admin.goods-receipts.edit', $receipt) }}" class="text-sm text-gray-700 hover:underline">Sửa</a>
                        <form method="POST" action="{{ route('admin.goods-receipts.confirm', $receipt) }}" class="inline"
                              onsubmit="return confirm('Xác nhận phiếu nhập này? Tồn kho sẽ được cập nhật.')">
                            @csrf
                            <button type="submit" class="ml-2 text-sm text-green-600 hover:underline">Xác nhận</button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="px-4 py-8 text-center text-gray-500">Chưa có phiếu nhập nào.</td>
            </tr>
        @endforelse
    </x-admin-table>

    <div class="mt-4">{{ $receipts->links() }}</div>
@endsection
