@extends('layouts.admin')

@section('title', 'Danh mục')

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Danh mục',
        'subtitle' => 'Sắp xếp sản phẩm theo nhóm và danh mục con.',
        'actions' => '<a href="'.route('admin.categories.create').'" class="inline-flex items-center rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">+ Thêm danh mục</a>',
    ])

    <x-excel-tools resource="categories" />

    <x-admin-table :paginator="$categories" :sorting="$sorting"
        :sortable="['Tên danh mục' => 'name', 'Danh mục cha' => 'parent', 'Thứ tự' => 'sort_order', 'Sản phẩm' => 'products_count', 'Danh mục con' => 'children_count', 'Trạng thái' => 'is_active']"
        :header="['Tên danh mục', 'Danh mục cha', 'Thứ tự', 'Sản phẩm', 'Danh mục con', 'Trạng thái', 'Thao tác']">
        @forelse ($categories as $category)
            <tr>
                <td class="px-4 py-3">
                    <span class="font-medium text-gray-900">{{ $category->name }}</span>
                    <span class="ml-2 text-xs text-gray-400">/{{ $category->slug }}</span>
                </td>
                <td class="px-4 py-3 text-gray-600">{{ $category->parent?->name ?? '—' }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $category->sort_order }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $category->products_count }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $category->children_count }}</td>
                <td class="px-4 py-3">
                    @if ($category->is_active)
                        <span class="inline-flex rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Hoạt động</span>
                    @else
                        <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">Tạm dừng</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('admin.categories.edit', $category) }}" class="text-sm text-gray-700 hover:underline">Sửa</a>
                    <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" class="inline"
                          onsubmit="return confirm('Xóa danh mục này?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="ml-3 text-sm text-red-600 hover:underline">Xóa</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="px-4 py-8 text-center text-gray-500">Chưa có danh mục nào.</td>
            </tr>
        @endforelse
    </x-admin-table>
@endsection
