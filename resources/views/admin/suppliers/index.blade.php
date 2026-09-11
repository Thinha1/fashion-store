@extends('layouts.admin')

@section('title', 'Nhà cung cấp')

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Nhà cung cấp',
        'actions' => '<a href="'.route('admin.suppliers.create').'" class="inline-flex items-center rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">+ Thêm nhà cung cấp</a>',
    ])

    <x-admin-table :header="['Tên', 'Điện thoại', 'Email', 'MST', 'Phiếu nhập', 'Trạng thái', 'Thao tác']">
        @forelse ($suppliers as $supplier)
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900">{{ $supplier->name }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $supplier->phone }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $supplier->email ?? '—' }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $supplier->tax_code ?? '—' }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $supplier->goods_receipts_count }}</td>
                <td class="px-4 py-3">
                    @if ($supplier->is_active)
                        <span class="inline-flex rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Hoạt động</span>
                    @else
                        <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">Tạm dừng</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('admin.suppliers.edit', $supplier) }}" class="text-sm text-gray-700 hover:underline">Sửa</a>
                    <form method="POST" action="{{ route('admin.suppliers.destroy', $supplier) }}" class="inline"
                          onsubmit="return confirm('Xóa nhà cung cấp này?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="ml-3 text-sm text-red-600 hover:underline">Xóa</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="px-4 py-8 text-center text-gray-500">Chưa có nhà cung cấp nào.</td>
            </tr>
        @endforelse
    </x-admin-table>

    <div class="mt-4">{{ $suppliers->links() }}</div>
@endsection
