@extends('layouts.admin')

@section('title', 'Bảng size')

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Bảng size',
        'actionUrl' => route('admin.size-charts.create'),
        'actionLabel' => 'Thêm bảng size',
    ])

    <x-admin-table :paginator="$charts" :header="['Tên', 'Áp dụng cho', 'Size', 'Gợi ý size', 'SP chọn riêng', 'Thao tác']">
        @forelse ($charts as $chart)
            <tr>
                <td class="px-4 py-3 font-medium"><a href="{{ route('admin.size-charts.edit', $chart) }}" class="text-gray-900 hover:underline">{{ $chart->name }}</a></td>
                <td class="px-4 py-3 text-gray-600">{{ $chart->coverageLabel() }}</td>
                <td class="px-4 py-3 text-gray-600">{{ collect($chart->rows)->pluck('size')->implode(', ') }}</td>
                <td class="px-4 py-3"><x-admin.status :value="$chart->canSuggest()" active-label="Có" inactive-label="Không" /></td>
                <td class="px-4 py-3 text-gray-600">{{ $chart->products_count }}</td>
                <td class="px-4 py-3 text-right"><div class="admin-row-actions">
                    <a href="{{ route('admin.size-charts.edit', $chart) }}" class="admin-row-action"><x-icon name="edit" class="size-3.5" /> Sửa</a>
                    <form method="POST" action="{{ route('admin.size-charts.destroy', $chart) }}" class="inline"
                          x-on:submit.prevent="$dispatch('admin-confirm', { form: $el, message: 'Xóa bảng size này? Sản phẩm đang chọn riêng bảng này sẽ chuyển về chọn tự động.' })">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="admin-row-action"><x-icon name="delete" class="size-3.5" /> Xóa</button>
                    </form>
                </div></td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="px-4 py-8 text-center text-gray-500">Chưa có bảng size nào. Trang sản phẩm sẽ không hiện mục "Bảng size".</td>
            </tr>
        @endforelse
    </x-admin-table>
@endsection
