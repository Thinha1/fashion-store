@props(['href' => null])
{{-- One labelled bar in a dashboard bar list: a link when it leads somewhere, otherwise focusable so keyboard users get the same tooltip as hover. --}}
@if ($href)
    <a href="{{ $href }}" {{ $attributes->class(['viz-bar-row block']) }}>{{ $slot }}</a>
@else
    <div tabindex="0" {{ $attributes->class(['viz-bar-row block']) }}>{{ $slot }}</div>
@endif
