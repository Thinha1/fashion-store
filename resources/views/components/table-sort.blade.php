@props(['label', 'column' => null, 'sorting' => null])

@if ($column && $sorting)
    @php
        $direction = $sorting->directionFor($column);
        $action = match ($direction) {
            'asc' => 'Sắp xếp '.$label.' giảm dần',
            'desc' => 'Bỏ sắp xếp '.$label,
            default => 'Sắp xếp '.$label.' tăng dần',
        };
    @endphp
    <a href="{{ $sorting->urlFor($column) }}" class="table-sort" aria-label="{{ $action }}" title="{{ $action }}">
        <span>{{ $label }}</span>
        <span class="flex shrink-0 flex-col text-[10px] leading-none" aria-hidden="true">
            <i @class(['fa-solid fa-caret-up', 'text-brand' => $direction === 'asc', 'text-gray-400' => $direction !== 'asc'])></i>
            <i @class(['fa-solid fa-caret-down', 'text-brand' => $direction === 'desc', 'text-gray-400' => $direction !== 'desc'])></i>
        </span>
    </a>
@else
    {{ $label }}
@endif
