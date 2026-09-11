@extends('layouts.admin')

@section('title', $receipt->receipt_number)

@section('content')
    @include('admin.partials.page-header', [
        'title' => $receipt->receipt_number,
        'subtitle' => 'ID '.$receipt->id,
        'actions' => $receipt->status === 'draft'
            ? '<a href="'.route('admin.goods-receipts.edit', $receipt).'" class="text-sm text-gray-700 hover:underline">Sửa</a>'
              : '',
    ])

    @if ($receipt->status === 'draft')
        <form method="POST" action="{{ route('admin.goods-receipts.confirm', $receipt) }}" class="mb-6 inline-block"
              onsubmit="return confirm('Xác nhận phiếu nhập này? Tồn kho sẽ được cập nhật.')">
            @csrf
            <x-button type="submit">Xác nhận phiếu nhập</x-button>
        </form>
    @endif

    <dl class="grid max-w-3xl grid-cols-1 gap-4 sm:grid-cols-3">
        <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Nhà cung cấp</dt>
            <dd class="mt-1 text-sm text-gray-900">{{ $receipt->supplier?->name ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Tổng chi phí</dt>
            <dd class="mt-1 text-sm text-gray-900">{{ number_format((float) $receipt->total_cost, 0) }} ₫</dd>
        </div>
        <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Trạng thái</dt>
            <dd class="mt-1 text-sm">
                @if ($receipt->status === 'draft')
                    <span class="inline-flex rounded-full bg-yellow-50 px-2 py-0.5 text-xs font-medium text-yellow-700">Bản nháp</span>
                @elseif ($receipt->status === 'confirmed')
                    <span class="inline-flex rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Đã xác nhận</span>
                @else
                    <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">{{ $receipt->status }}</span>
                @endif
            </dd>
        </div>
        @if ($receipt->confirmed_at)
            <div class="sm:col-span-3">
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Xác nhận bởi</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    {{ $receipt->confirmedBy?->name ?? '—' }}
                    <span class="text-xs text-gray-400">({{ $receipt->confirmed_at->format('d/m/Y H:i') }})</span>
                </dd>
            </div>
        @endif
        @if ($receipt->notes)
            <div class="sm:col-span-3">
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Ghi chú</dt>
                <dd class="mt-1 whitespace-pre-line text-sm text-gray-900">{{ $receipt->notes }}</dd>
            </div>
        @endif
    </dl>

    @if ($receipt->items->count())
        <div class="mt-8 max-w-3xl">
            <h2 class="text-sm font-semibold text-gray-700">Dòng hàng ({{ $receipt->items->count() }})</h2>
            <x-admin-table :header="['Sản phẩm', 'Size / Màu', 'SKU', 'SL', 'Giá nhập', 'Thành tiền']" class="mt-3">
                @foreach ($receipt->items as $item)
                    <tr>
                        <td class="px-4 py-3">{{ $item->productVariant?->product?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $item->productVariant?->size }} / {{ $item->productVariant?->color }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $item->productVariant?->sku }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $item->quantity }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ number_format((float) $item->cost_price, 0) }} ₫</td>
                        <td class="px-4 py-3 text-gray-600">{{ number_format((float) $item->subtotal, 0) }} ₫</td>
                    </tr>
                @endforeach
            </x-admin-table>
        </div>
    @endif

    <div class="mt-8">
        <a href="{{ route('admin.goods-receipts.index') }}" class="text-sm text-gray-600 hover:underline">&larr; Danh sách phiếu nhập</a>
    </div>
@endsection
