@props(['title', 'description' => null, 'icon' => null])
<section {{ $attributes->class(['admin-panel']) }}>
    <div class="admin-panel-heading">
        <div>
            <h2>@if ($icon)<x-icon :name="$icon" class="size-4 text-brand" />@endif {{ $title }}</h2>
            @if ($description)<p>{{ $description }}</p>@endif
        </div>
        @isset($actions)<div>{{ $actions }}</div>@endisset
    </div>
    <div class="admin-panel-body">{{ $slot }}</div>
</section>
