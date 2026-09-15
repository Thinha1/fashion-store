@php
    $title = $title ?? 'Trang';
    $subtitle = $subtitle ?? null;
    $section = explode('.', request()->route()?->getName() ?? '')[1] ?? '';
    $sections = ['products' => ['Sản phẩm', 'shirt'], 'categories' => ['Danh mục', 'layers'], 'brands' => ['Thương hiệu', 'tag'], 'discounts' => ['Giảm giá', 'percent'], 'suppliers' => ['Nhà cung cấp', 'truck'], 'goods-receipts' => ['Phiếu nhập hàng', 'box']];
    $sectionInfo = $sections[$section] ?? ['Tổng quan', 'grid'];
@endphp

<div class="page-header">
    <div class="min-w-0">
    @if (isset($sections[$section]) && ! request()->routeIs('admin.'.$section.'.index'))
        <a href="{{ route('admin.'.$section.'.index') }}" class="admin-back"><x-icon name="chevron-left" class="size-3" /> {{ $sectionInfo[0] }}</a>
    @endif
    <h1>{{ $title }}</h1>
    @if ($subtitle)
        <p class="mt-1 text-sm text-gray-600">{{ $subtitle }}</p>
    @endif
    </div>
    @if (! empty($actionUrl))
        <div class="page-actions">
            <x-admin.action :href="$actionUrl" :icon="$actionIcon ?? 'plus'">{{ $actionLabel ?? 'Thêm mới' }}</x-admin.action>
        </div>
    @endif
</div>
