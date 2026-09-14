@extends('layouts.admin')
@section('title', 'Tổng quan')
@section('content')
    <section class="mb-8 flex flex-wrap items-center justify-between gap-5 rounded-2xl border border-brand/10 bg-brand-soft px-6 py-8 sm:px-8">
        <div><p class="eyebrow">Cửa hàng của bạn</p><h1 class="mt-3 text-2xl font-semibold tracking-tight sm:text-3xl">Xin chào, {{ auth()->user()->name }}.</h1><p class="mt-3 text-sm leading-6 text-gray-600">Bắt đầu ngày làm việc với các tác vụ quản lý cửa hàng.</p></div>
        <a href="{{ route('home') }}" class="btn btn-secondary"><x-icon name="home" class="size-4" /> Xem cửa hàng <x-icon class="size-4" /></a>
    </section>
    <div class="mb-5"><h2 class="text-lg font-semibold">Bạn muốn làm gì hôm nay?</h2><p class="mt-1 text-sm text-gray-500">Truy cập nhanh những công việc thường dùng.</p></div>
    <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ([['products.manage', 'products', 'shirt', 'Quản lý sản phẩm', 'Cập nhật thông tin, hình ảnh, size và màu của sản phẩm.', 'Xem sản phẩm'], ['products.manage', 'categories', 'layers', 'Sắp xếp danh mục', 'Tổ chức sản phẩm theo nhóm để dễ quản lý và tìm kiếm.', 'Xem danh mục'], ['products.manage', 'brands', 'tag', 'Quản lý thương hiệu', 'Cập nhật thương hiệu và thông tin nhận diện.', 'Xem thương hiệu'], ['inventory.manage', 'goods-receipts', 'box', 'Nhập hàng vào kho', 'Tạo và xác nhận phiếu nhập hàng từ nhà cung cấp.', 'Xem phiếu nhập hàng'], ['suppliers.manage', 'suppliers', 'truck', 'Quản lý nhà cung cấp', 'Tra cứu thông tin liên hệ và đối tác cung ứng.', 'Xem nhà cung cấp'], ['products.manage', 'discounts', 'percent', 'Thiết lập giảm giá', 'Quản lý mức giảm và thời gian áp dụng cho biến thể.', 'Xem giảm giá']] as [$permission, $section, $icon, $title, $description, $action])
            @if (auth()->user()->hasPermission($permission))
                <a href="{{ route('admin.'.$section.'.index') }}" class="task-card"><span class="task-icon"><x-icon :name="$icon" class="size-6" /></span><div><h3>{{ $title }}</h3><p>{{ $description }}</p></div><span>{{ $action }} <x-icon class="size-4" /></span></a>
            @endif
        @endforeach
    </div>
    <div class="mt-8 flex items-start gap-3 rounded-xl border border-gray-200 bg-white p-5"><x-icon name="info" class="mt-0.5 shrink-0 text-brand" /><div><h2 class="text-sm font-semibold">Không thấy tác vụ cần dùng?</h2><p class="mt-1 text-sm leading-6 text-gray-500">Các tác vụ hiển thị theo quyền được cấp. Liên hệ Admin nếu bạn cần thêm quyền quản lý.</p></div></div>
@endsection
