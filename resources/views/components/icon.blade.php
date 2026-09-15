@props(['name' => 'arrow'])

@php
    $icons = [
        'grid' => 'fa-table-cells-large', 'shirt' => 'fa-shirt',
        'tag' => 'fa-tag', 'percent' => 'fa-percent', 'layers' => 'fa-layer-group', 'truck' => 'fa-truck',
        'box' => 'fa-box-open', 'user' => 'fa-user', 'logout' => 'fa-right-from-bracket',
        'menu' => 'fa-bars', 'close' => 'fa-xmark', 'check' => 'fa-check',
        'info' => 'fa-circle-info', 'eye' => 'fa-eye', 'eye-slash' => 'fa-eye-slash',
        'plus' => 'fa-plus', 'home' => 'fa-house', 'arrow' => 'fa-arrow-right',
        'revenue' => 'fa-wallet', 'orders' => 'fa-receipt', 'calendar' => 'fa-calendar-days',
        'star' => 'fa-star',
        'edit' => 'fa-pen-to-square', 'delete' => 'fa-trash-can', 'save' => 'fa-check',
        'search' => 'fa-magnifying-glass', 'image' => 'fa-image', 'back' => 'fa-arrow-left',
        'chevron-left' => 'fa-chevron-left', 'chevron-right' => 'fa-chevron-right',
    ];
@endphp
<i {{ $attributes->merge(['class' => 'ui-icon fa-solid '.($icons[$name] ?? $icons['arrow'])]) }} aria-hidden="true"></i>
