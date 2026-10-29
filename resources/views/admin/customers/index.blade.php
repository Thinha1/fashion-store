@extends('layouts.admin')

@section('title', 'Khách hàng')

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Khách hàng',
        'subtitle' => 'Tài khoản đã đăng ký mua hàng. "Đã chi" chỉ tính đơn đã giao và đã thanh toán.',
    ])

    @php($active = array_filter(['q' => $search, 'mua' => $filter]))

    <form method="GET" action="{{ route('admin.customers.index') }}" role="search" class="mb-4 flex flex-wrap items-center gap-2">
        <label for="customer-q" class="sr-only">Tìm khách hàng</label>
        <input id="customer-q" type="search" name="q" value="{{ $search }}" placeholder="Tên, email hoặc số điện thoại" class="field min-h-10 sm:w-64">
        <label for="customer-bought" class="sr-only">Đã mua hay chưa</label>
        <select id="customer-bought" name="mua" class="field min-h-10 sm:w-auto" x-data x-on:change="$el.form.requestSubmit()">
            <option value="">Tất cả khách hàng</option>
            @foreach ($filters as $value => $label)
                <option value="{{ $value }}" @selected($filter === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <x-admin.action type="submit" variant="secondary" icon="search">Lọc</x-admin.action>
        @if ($active)
            <a href="{{ route('admin.customers.index') }}" class="px-2 text-sm font-medium text-gray-500 hover:text-gray-900 hover:underline">Xoá lọc</a>
        @endif
    </form>

    <x-admin-table :paginator="$customers" :sorting="$sorting"
        :sortable="['Khách hàng' => 'name', 'Số đơn' => 'orders_count', 'Đã chi' => 'spent', 'Đơn gần nhất' => 'last_order_at', 'Đăng ký' => 'created_at']"
        :header="['Khách hàng', 'Số điện thoại', 'Số đơn', 'Đã chi', 'Đơn gần nhất', 'Đăng ký', 'Thao tác']">
        @forelse ($customers as $customer)
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900">
                    <a href="{{ route('admin.customers.show', $customer) }}" class="hover:underline">{{ $customer->name }}</a>
                    <span class="block text-xs font-normal text-gray-400">{{ $customer->email }}</span>
                </td>
                <td class="px-4 py-3 text-gray-600">{{ $customer->phone ?: '—' }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $customer->orders_count }}</td>
                <td class="px-4 py-3 font-medium text-gray-900"><x-money :amount="$customer->spent ?? 0" /></td>
                <td class="px-4 py-3 text-gray-600">{{ $customer->last_order_at ? \Illuminate\Support\Carbon::parse($customer->last_order_at)->format('d/m/Y') : '—' }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $customer->created_at->format('d/m/Y') }}</td>
                <td class="px-4 py-3 text-right"><div class="admin-row-actions">
                    <a href="{{ route('admin.customers.show', $customer) }}" class="admin-row-action"><x-icon name="eye" class="size-3.5" /> Xem</a>
                </div></td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="px-4 py-8 text-center text-gray-500">{{ $active ? 'Không có khách hàng nào khớp bộ lọc.' : 'Chưa có khách hàng nào.' }}</td>
            </tr>
        @endforelse
    </x-admin-table>
@endsection
