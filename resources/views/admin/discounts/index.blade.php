@extends('layouts.admin')

@section('title', 'Giảm giá biến thể')

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Giảm giá biến thể',
        'subtitle' => 'Thiết lập mức giảm và thời gian áp dụng cho từng biến thể sản phẩm.',
        'actionUrl' => route('admin.discounts.create'),
        'actionLabel' => 'Thêm giảm giá',
    ])

    <x-excel-tools resource="discounts" />

    <x-admin-table :paginator="$discounts" :sorting="$sorting"
        :sortable="['Biến thể' => 'variant', 'Loại' => 'discount_type', 'Giá trị' => 'discount_value', 'Bắt đầu' => 'starts_at', 'Kết thúc' => 'ends_at', 'Trạng thái' => 'is_active']"
        :header="['Biến thể', 'Loại', 'Giá trị', 'Bắt đầu', 'Kết thúc', 'Trạng thái', 'Thao tác']">
        @forelse ($discounts as $discount)
            <tr>
                <td class="px-4 py-3">
                    @if ($discount->productVariant)
                        <span class="font-medium text-gray-900">{{ $discount->productVariant->product?->name }}</span>
                        <span class="ml-2 text-xs text-gray-500">{{ $discount->productVariant->size }} / {{ $discount->productVariant->color }}</span>
                    @else
                        <span class="text-gray-400">—</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-gray-600">
                    {{ $discount->discount_type === 'percent' ? 'Phần trăm' : 'Cố định' }}
                </td>
                <td class="px-4 py-3 text-gray-600">
                    {{ $discount->discount_value }}{{ $discount->discount_type === 'percent' ? '%' : ' ₫' }}
                </td>
                <td class="px-4 py-3 text-gray-600">{{ $discount->starts_at?->format('d/m/Y') }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $discount->ends_at?->format('d/m/Y') }}</td>
                <td class="px-4 py-3">
                    <x-admin.status :value="$discount->is_active" />
                </td>
                <td class="px-4 py-3 text-right"><div class="admin-row-actions">
                    <a href="{{ route('admin.discounts.edit', $discount) }}" class="admin-row-action"><x-icon name="edit" class="size-3.5" /> Sửa</a>
                    <form method="POST" action="{{ route('admin.discounts.destroy', $discount) }}" class="inline"
                          x-on:submit.prevent="$dispatch('admin-confirm', { form: $el, message: 'Xóa giảm giá này?' })">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="admin-row-action"><x-icon name="delete" class="size-3.5" /> Xóa</button>
                    </form>
                </div></td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="px-4 py-8 text-center text-gray-500">Chưa có giảm giá nào.</td>
            </tr>
        @endforelse
    </x-admin-table>
@endsection
