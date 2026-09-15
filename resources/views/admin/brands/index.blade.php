@extends('layouts.admin')

@section('title', 'Thương hiệu')

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Thương hiệu',
        'subtitle' => 'Quản lý thương hiệu và thông tin nhận diện của sản phẩm.',
        'actions' => '<a href="'.route('admin.brands.create').'" class="btn btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> Thêm thương hiệu</a>',
    ])
    <x-excel-tools resource="brands" />

    <x-admin-table :paginator="$brands" :header="['Tên', 'Quốc gia', 'Sản phẩm', 'Trạng thái', 'Thao tác']">
        @forelse ($brands as $brand)
            <tr>
                <td class="px-4 py-3 font-medium">
                    <div class="flex items-center gap-2">
                        @if ($brand->logo_path)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('s3')->url($brand->logo_path) }}" alt="" class="h-8 w-8 rounded object-cover">
                        @endif
                        <div>
                            <a href="{{ route('admin.brands.show', $brand) }}" class="text-gray-900 hover:underline">{{ $brand->name }}</a>
                            <span class="ml-2 text-xs text-gray-400">/{{ $brand->slug }}</span>
                        </div>
                    </div>
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

@endsection
