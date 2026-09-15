@extends('layouts.admin')

@section('title', 'Nhà cung cấp')

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Nhà cung cấp',
        'subtitle' => 'Tra cứu thông tin liên hệ và đối tác cung ứng của cửa hàng.',
        'actionUrl' => route('admin.suppliers.create'),
        'actionLabel' => 'Thêm nhà cung cấp',
    ])

    <x-excel-tools resource="suppliers" />

    <x-admin-table :paginator="$suppliers" :sorting="$sorting"
        :sortable="['Tên nhà cung cấp' => 'name', 'Điện thoại' => 'phone', 'Email' => 'email', 'Mã số thuế' => 'tax_code', 'Phiếu nhập' => 'goods_receipts_count', 'Trạng thái' => 'is_active']"
        :header="['Tên nhà cung cấp', 'Điện thoại', 'Email', 'Mã số thuế', 'Phiếu nhập', 'Trạng thái', 'Thao tác']">
        @forelse ($suppliers as $supplier)
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900">{{ $supplier->name }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $supplier->phone }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $supplier->email ?? '—' }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $supplier->tax_code ?? '—' }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $supplier->goods_receipts_count }}</td>
                <td class="px-4 py-3">
                    <x-admin.status :value="$supplier->is_active" />
                </td>
                <td class="px-4 py-3 text-right"><div class="admin-row-actions">
                    <a href="{{ route('admin.suppliers.edit', $supplier) }}" class="admin-row-action"><x-icon name="edit" class="size-3.5" /> Sửa</a>
                    <form method="POST" action="{{ route('admin.suppliers.destroy', $supplier) }}" class="inline"
                          x-on:submit.prevent="$dispatch('admin-confirm', { form: $el, message: 'Xóa nhà cung cấp này?' })">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="admin-row-action"><x-icon name="delete" class="size-3.5" /> Xóa</button>
                    </form>
                </div></td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="px-4 py-8 text-center text-gray-500">Chưa có nhà cung cấp nào.</td>
            </tr>
        @endforelse
    </x-admin-table>
@endsection
