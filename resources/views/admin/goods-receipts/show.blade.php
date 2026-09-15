@extends('layouts.admin')

@section('title', $receipt->receipt_number)

@section('content')
    @include('admin.partials.page-header', [
        'title' => $receipt->receipt_number,
        'subtitle' => 'Chi tiết hàng nhập và thông tin xác nhận.',
        'actionUrl' => $receipt->status === 'draft' ? route('admin.goods-receipts.edit', $receipt) : null,
        'actionLabel' => 'Chỉnh sửa',
        'actionIcon' => 'edit',
    ])

    @if ($receipt->status === 'draft')
        <form method="POST" action="{{ route('admin.goods-receipts.confirm', $receipt) }}" class="mb-6 inline-block"
              x-on:submit.prevent="$dispatch('admin-confirm', { form: $el, message: 'Xác nhận phiếu nhập này? Tồn kho sẽ được cập nhật.' })">
            @csrf
            <x-admin.action type="submit" icon="check">Xác nhận phiếu nhập</x-admin.action>
        </form>
    @endif

    <x-admin.panel title="Thông tin phiếu nhập" icon="box"><dl class="admin-facts sm:grid-cols-3">
        <div>
            <dt class="text-xs text-gray-500">Nhà cung cấp</dt>
            <dd class="mt-1 text-sm text-gray-900">{{ $receipt->supplier?->name ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-xs text-gray-500">Tổng chi phí</dt>
            <dd class="mt-1 text-sm text-gray-900">{{ number_format((float) $receipt->total_cost, 0) }} ₫</dd>
        </div>
        <div>
            <dt class="text-xs text-gray-500">Trạng thái</dt>
            <dd class="mt-1 text-sm">
                <x-admin.status :value="$receipt->status" />
            </dd>
        </div>
        @if ($receipt->confirmed_at)
            <div class="sm:col-span-3">
                <dt class="text-xs text-gray-500">Xác nhận bởi</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    {{ $receipt->confirmedBy?->name ?? '—' }}
                    <span class="text-xs text-gray-400">({{ $receipt->confirmed_at->format('d/m/Y H:i') }})</span>
                </dd>
            </div>
        @endif
        @if ($receipt->notes)
            <div class="sm:col-span-3">
                <dt class="text-xs text-gray-500">Ghi chú</dt>
                <dd class="mt-1 whitespace-pre-line text-sm text-gray-900">{{ $receipt->notes }}</dd>
            </div>
        @endif
    </dl></x-admin.panel>

    @if ($receipt->items->count())
        <div class="mt-6">
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
