@props(['href' => null])
{{-- One labelled bar in a dashboard bar list: a link when it leads somewhere, otherwise a labelled group (its aria-label carries the tooltip's figures for screen readers). --}}
@if ($href)
    <a href="{{ $href }}" {{ $attributes->class(['viz-bar-row block']) }}>{{ $slot }}</a>
@else
    <div role="group" {{ $attributes->class(['viz-bar-row block']) }}>{{ $slot }}</div>
@endif
