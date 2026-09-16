@extends('layouts.admin')

@section('title', 'Thương hiệu')

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Thương hiệu',
        'subtitle' => 'Quản lý thương hiệu và thông tin nhận diện của sản phẩm.',
        'actionUrl' => route('admin.brands.create'),
        'actionLabel' => 'Thêm thương hiệu',
    ])
    <x-excel-tools resource="brands" />

    <x-admin-table :paginator="$brands" :sorting="$sorting"
        :sortable="['Tên' => 'name', 'Quốc gia' => 'country', 'Sản phẩm' => 'products_count', 'Trạng thái' => 'is_active']"
        :header="['Tên', 'Quốc gia', 'Sản phẩm', 'Trạng thái', 'Thao tác']">
        @forelse ($brands as $brand)
            <tr>
                <td class="px-4 py-3 font-medium">
                    <div class="flex items-center gap-2">
                        @if ($brand->logo_path)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk(config('filesystems.image_disk'))->url($brand->logo_path) }}" alt="" class="h-8 w-8 rounded object-cover">
                        @endif
                        <div>
                            <a href="{{ route('admin.brands.show', $brand) }}" class="text-gray-900 hover:underline">{{ $brand->name }}</a>
                        </div>
                    </div>
                </td>
                <td class="px-4 py-3 text-gray-600">{{ $brand->country ?? '—' }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $brand->products_count }}</td>
                <td class="px-4 py-3">
                    <x-admin.status :value="$brand->is_active" />
                </td>
                <td class="px-4 py-3 text-right"><div class="admin-row-actions">
                    <a href="{{ route('admin.brands.edit', $brand) }}" class="admin-row-action"><x-icon name="edit" class="size-3.5" /> Sửa</a>
                    <form method="POST" action="{{ route('admin.brands.destroy', $brand) }}" class="inline"
                          x-on:submit.prevent="$dispatch('admin-confirm', { form: $el, message: 'Xóa thương hiệu này?' })">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="admin-row-action"><x-icon name="delete" class="size-3.5" /> Xóa</button>
                    </form>
                </div></td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="px-4 py-8 text-center text-gray-500">Chưa có thương hiệu nào.</td>
            </tr>
        @endforelse
    </x-admin-table>

@endsection
