{{-- The AI assistants' mascot: a robot head that blinks every few seconds.
     Drawn in SVG and animated in CSS (.ai-bot-eyes) so it stays sharp at
     any size and holds still under prefers-reduced-motion. --}}
<svg {{ $attributes->class('ai-bot') }} viewBox="0 0 32 32" fill="none" aria-hidden="true" focusable="false">
    <path d="M16 4.5V8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
    <circle class="ai-bot-tip" cx="16" cy="3.25" r="2.25" fill="currentColor"/>
    <rect x="5" y="8" width="22" height="18" rx="6" stroke="currentColor" stroke-width="2.25"/>
    <rect x="1.75" y="13.5" width="3.25" height="7" rx="1.6" fill="currentColor"/>
    <rect x="27" y="13.5" width="3.25" height="7" rx="1.6" fill="currentColor"/>
    <g class="ai-bot-eyes">
        <ellipse cx="11.75" cy="16" rx="2.3" ry="2.7" fill="currentColor"/>
        <ellipse cx="20.25" cy="16" rx="2.3" ry="2.7" fill="currentColor"/>
    </g>
    <path d="M12.75 21.5h6.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
</svg>
