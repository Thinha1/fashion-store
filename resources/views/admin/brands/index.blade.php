@extends('layouts.admin')

@section('title', 'Thương hiệu')

@section('content')
    <x-admin-table :header="['Tên', 'Quốc gia', 'Sản phẩm', 'Trạng thái', 'Thao tác']">
        @forelse ($brands as $brand)
            <tr>
                <td class="px-4 py-3 font-medium">
                    <a href="{{ route('admin.brands.show', $brand) }}" class="text-gray-900 hover:underline">{{ $brand->name }}</a>
                    <span class="ml-2 text-xs text-gray-400">/{{ $brand->slug }}</span>
                </td>
                <td class="px-4 py-3 text-gray-600">{{ $brand->country ?? '—' }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $brand->products_count }}</td>
                <td class="px-4 py-3">
                    @if ($brand->is_active)
                        <span class="inline-flex rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Đang hoạt động</span>
                    @else
                        <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">Ngừng hoạt động</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('admin.brands.edit', $brand) }}" class="text-sm text-gray-700 hover:underline">Sửa</a>
                    <form method="POST" action="{{ route('admin.brands.destroy', $brand) }}" class="inline"
                          onsubmit="return confirm('Xóa thương hiệu này?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="ml-3 text-sm text-red-600 hover:underline">Xóa</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="px-4 py-8 text-center text-gray-500">Chưa có thương hiệu nào.</td>
            </tr>
        @endforelse
    </x-admin-table>

    <div class="mt-4">
        {{ $brands->links() }}
    </div>

    <div class="mt-6">
        <a href="{{ route('admin.brands.create') }}" class="inline-flex items-center rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">
            + Thêm thương hiệu
        </a>
    </div>
@endsection
