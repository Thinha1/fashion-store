@php($title = $title ?? 'Trang')
@php($subtitle = $subtitle ?? null)

<div class="mb-6">
    <h1 class="text-xl font-semibold">{{ $title }}</h1>
    @if ($subtitle)
        <p class="mt-1 text-sm text-gray-600">{{ $subtitle }}</p>
    @endif
    @if (isset($actions) && $actions)
        <div class="mt-4 flex items-center gap-2">
            {{ $actions }}
        </div>
    @endif
</div>
