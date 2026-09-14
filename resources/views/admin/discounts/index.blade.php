@extends('layouts.admin')

@section('title', 'Giảm giá biến thể')

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Giảm giá biến thể',
        'subtitle' => 'Thiết lập mức giảm và thời gian áp dụng cho từng biến thể sản phẩm.',
        'actions' => '<a href="'.route('admin.discounts.create').'" class="inline-flex items-center rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">+ Thêm giảm giá</a>',
    ])

    <x-excel-tools resource="discounts" />

    <x-admin-table :header="['Biến thể', 'Loại', 'Giá trị', 'Bắt đầu', 'Kết thúc', 'Trạng thái', 'Thao tác']">
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
                    @if ($discount->is_active)
                        <span class="inline-flex rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Hoạt động</span>
                    @else
                        <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">Tạm dừng</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('admin.discounts.edit', $discount) }}" class="text-sm text-gray-700 hover:underline">Sửa</a>
                    <form method="POST" action="{{ route('admin.discounts.destroy', $discount) }}" class="inline"
                          onsubmit="return confirm('Xóa giảm giá này?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="ml-3 text-sm text-red-600 hover:underline">Xóa</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="px-4 py-8 text-center text-gray-500">Chưa có giảm giá nào.</td>
            </tr>
        @endforelse
    </x-admin-table>

    <div class="mt-4">{{ $discounts->links() }}</div>
@endsection
