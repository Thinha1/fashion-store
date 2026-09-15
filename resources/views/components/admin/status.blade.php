@props(['value'])
@php
    [$label, $tone] = match ((string) $value) {
        '1', 'active' => ['Hoạt động', 'success'],
        'confirmed' => ['Đã xác nhận', 'success'],
        'draft' => ['Bản nháp', 'warning'],
        'archived' => ['Lưu trữ', 'neutral'],
        '', '0' => ['Tạm dừng', 'neutral'],
        default => [(string) $value, 'neutral'],
    };
@endphp
<span class="admin-status admin-status-{{ $tone }}"><span aria-hidden="true"></span>{{ $label }}</span>
