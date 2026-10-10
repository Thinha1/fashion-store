@props(['status'])
@php
    $tone = match ($status) {
        'pending' => 'warning',
        'confirmed', 'preparing', 'shipping' => 'info',
        'delivered' => 'success',
        default => 'neutral',
    };
@endphp
<span class="admin-status admin-status-{{ $tone }}"><span aria-hidden="true"></span>{{ \App\Models\Order::STATUS_LABELS[$status] ?? $status }}</span>
