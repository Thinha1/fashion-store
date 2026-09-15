@extends('layouts.admin')

@section('title', 'Danh mục')

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Danh mục',
        'subtitle' => 'Sắp xếp sản phẩm theo nhóm và danh mục con.',
        'actionUrl' => route('admin.categories.create'),
        'actionLabel' => 'Thêm danh mục',
    ])

    <x-excel-tools resource="categories" />

    <x-admin-table :paginator="$categories" :sorting="$sorting"
        :sortable="['Tên danh mục' => 'name', 'Danh mục cha' => 'parent', 'Thứ tự' => 'sort_order', 'Sản phẩm' => 'products_count', 'Danh mục con' => 'children_count', 'Trạng thái' => 'is_active']"
        :header="['Tên danh mục', 'Danh mục cha', 'Thứ tự', 'Sản phẩm', 'Danh mục con', 'Trạng thái', 'Thao tác']">
        @forelse ($categories as $category)
            <tr>
                <td class="px-4 py-3">
                    <span class="font-medium text-gray-900">{{ $category->name }}</span>
                </td>
                <td class="px-4 py-3 text-gray-600">{{ $category->parent?->name ?? '—' }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $category->sort_order }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $category->products_count }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $category->children_count }}</td>
                <td class="px-4 py-3">
                    <x-admin.status :value="$category->is_active" />
                </td>
                <td class="px-4 py-3 text-right"><div class="admin-row-actions">
                    <a href="{{ route('admin.categories.edit', $category) }}" class="admin-row-action"><x-icon name="edit" class="size-3.5" /> Sửa</a>
                    <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" class="inline"
                          x-on:submit.prevent="$dispatch('admin-confirm', { form: $el, message: 'Xóa danh mục này?' })">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="admin-row-action"><x-icon name="delete" class="size-3.5" /> Xóa</button>
                    </form>
                </div></td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="px-4 py-8 text-center text-gray-500">Chưa có danh mục nào.</td>
            </tr>
        @endforelse
    </x-admin-table>
@endsection
