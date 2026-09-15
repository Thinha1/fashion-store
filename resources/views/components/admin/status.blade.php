@props(['value', 'activeLabel' => 'Hoạt động', 'inactiveLabel' => 'Tạm dừng'])
@php
    [$label, $tone] = match ((string) $value) {
        '1', 'active' => [$activeLabel, 'success'],
        'confirmed' => ['Đã xác nhận', 'success'],
        'draft' => ['Bản nháp', 'warning'],
        'archived' => ['Lưu trữ', 'neutral'],
        '', '0' => [$inactiveLabel, 'neutral'],
        default => [(string) $value, 'neutral'],
    };
@endphp
<span class="admin-status admin-status-{{ $tone }}"><span aria-hidden="true"></span>{{ $label }}</span>
