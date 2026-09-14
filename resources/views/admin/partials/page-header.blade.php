@php($title = $title ?? 'Trang')
@php($subtitle = $subtitle ?? null)

<div class="page-header">
    <div>
    <h1>{{ $title }}</h1>
    @if ($subtitle)
        <p class="mt-1 text-sm text-gray-600">{{ $subtitle }}</p>
    @endif
    </div>
    @if (isset($actions) && $actions)
        <div class="page-actions">
            {!! $actions !!}
        </div>
    @endif
</div>
