// The AI chat robots (<x-ai-bot>) look toward the mouse pointer. While the
// mouse moves, each visible robot gets data-tracking and --look-x/--look-y
// (in SVG units, at most MAX_SHIFT); after IDLE_MS of stillness the
// attribute goes and the eyes return to their idle glance animation.
// Touch and reduced-motion users keep the idle animation only.
const MAX_SHIFT = 2.4;
const IDLE_MS = 2500;

export default function startAiBotGaze() {
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let pointer = null;
    let frame = 0;
    let idleTimer = 0;

    const look = () => {
        frame = 0;
        for (const bot of document.querySelectorAll('svg.ai-bot')) {
            const box = bot.getBoundingClientRect();
            if (box.width === 0) continue;
            const dx = pointer.x - (box.left + box.width / 2);
            const dy = pointer.y - (box.top + box.height / 2);
            const distance = Math.hypot(dx, dy) || 1;
            // Full shift once the pointer is a few robot-widths away.
            const reach = Math.min(1, distance / (box.width * 3)) * MAX_SHIFT;
            bot.style.setProperty('--look-x', `${((dx / distance) * reach).toFixed(2)}px`);
            bot.style.setProperty('--look-y', `${((dy / distance) * reach * 0.6).toFixed(2)}px`);
            bot.dataset.tracking = '';
        }
    };

    const rest = () => {
        for (const bot of document.querySelectorAll('svg.ai-bot[data-tracking]')) delete bot.dataset.tracking;
    };

    window.addEventListener('pointermove', (event) => {
        if (event.pointerType !== 'mouse' || reduceMotion.matches) return;
        pointer = { x: event.clientX, y: event.clientY };
        if (!frame) frame = requestAnimationFrame(look);
        clearTimeout(idleTimer);
        idleTimer = setTimeout(rest, IDLE_MS);
    }, { passive: true });
    document.documentElement.addEventListener('pointerleave', rest);
}
