@extends('layouts.admin')

@section('title', 'Sản phẩm')

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Sản phẩm',
        'actions' => '<a href="'.route('admin.products.create').'" class="inline-flex items-center rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">+ Thêm sản phẩm</a>',
    ])

    <x-admin-table :header="['Tên', 'Danh mục', 'Thương hiệu', 'Biến thể', 'Trạng thái', 'Cấp đặc biệt', 'Thao tác']">
        @forelse ($products as $product)
            <tr>
                <td class="px-4 py-3">
                    <a href="{{ route('admin.products.show', $product) }}" class="font-medium text-gray-900 hover:underline">{{ $product->name }}</a>
                    <span class="ml-2 text-xs text-gray-400">/{{ $product->slug }}</span>
                </td>
                <td class="px-4 py-3 text-gray-600">{{ $product->category?->name ?? '—' }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $product->brand?->name ?? '—' }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $product->variants_count }}</td>
                <td class="px-4 py-3">
                    @php
                        $statusStyles = [
                            'draft' => 'bg-yellow-50 text-yellow-700',
                            'active' => 'bg-green-50 text-green-700',
                            'archived' => 'bg-gray-100 text-gray-500',
                        ];
                        $statusLabels = [
                            'draft' => 'Bản nháp',
                            'active' => 'Hoạt động',
                            'archived' => 'Lưu trữ',
                        ];
                        $status = $product->status;
                    @endphp
                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $statusStyles[$status] ?? 'bg-gray-100 text-gray-500' }}">
                        {{ $statusLabels[$status] ?? $status }}
                    </span>
                </td>
                <td class="px-4 py-3">{{ $product->is_featured ? '★' : '' }}</td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('admin.products.edit', $product) }}" class="text-sm text-gray-700 hover:underline">Sửa</a>
                    <form method="POST" action="{{ route('admin.products.destroy', $product) }}" class="inline"
                          onsubmit="return confirm('Xóa sản phẩm này?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="ml-3 text-sm text-red-600 hover:underline">Xóa</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="px-4 py-8 text-center text-gray-500">Chưa có sản phẩm nào.</td>
            </tr>
        @endforelse
    </x-admin-table>

    <div class="mt-4">{{ $products->links() }}</div>
@endsection
