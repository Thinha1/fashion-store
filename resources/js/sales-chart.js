// Sales-over-time line chart for the admin dashboard, drawn as SVG.
// Spec (dataviz skill): 2px line + 10% area wash, hairline solid grid,
// crosshair snapping to the nearest bucket with a tooltip (value first,
// label second), keyboard arrows do the same as the pointer, one direct
// label on the peak only. Labels go in with textContent, never innerHTML.

const SVG_NS = 'http://www.w3.org/2000/svg';
const MARGIN = { top: 28, right: 20, bottom: 30, left: 56 };

const groupFormat = new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 1 });

/** 1.250.000 ₫ */
export function formatVnd(value) {
    return `${new Intl.NumberFormat('vi-VN').format(Math.round(value))} ₫`;
}

/** Axis-sized money: 850k, 1,5 tr, 2 tỷ. */
export function compactVnd(value) {
    const abs = Math.abs(value);
    if (abs >= 1e9) return `${groupFormat.format(value / 1e9)} tỷ`;
    if (abs >= 1e6) return `${groupFormat.format(value / 1e6)} tr`;
    if (abs >= 1e3) return `${groupFormat.format(value / 1e3)}k`;
    return groupFormat.format(value);
}

/** Clean tick values from 0 up to a round number at or above `maxValue`. */
export function niceTicks(maxValue, target = 4) {
    if (!(maxValue > 0)) return [0];
    const rough = maxValue / target;
    const power = 10 ** Math.floor(Math.log10(rough));
    const step = [1, 2, 2.5, 5, 10].map((multiple) => multiple * power).find((candidate) => candidate >= rough);
    const top = Math.ceil(maxValue / step) * step;
    const ticks = [];
    for (let index = 0; index * step <= top + step / 1e6; index++) {
        ticks.push(Math.round(index * step * 1e6) / 1e6);
    }
    return ticks;
}

/** Index of the bucket whose x is closest to `px` (plot spans left..left+innerWidth). */
export function nearestIndex(px, count, left, innerWidth) {
    if (count <= 1) return 0;
    const index = Math.round(((px - left) / innerWidth) * (count - 1));
    return Math.min(count - 1, Math.max(0, index));
}

/** Evenly spaced x-label positions, always keeping the first and the last. */
export function pickLabelIndices(count, maxLabels) {
    if (count <= 0) return [];
    if (count <= maxLabels) return Array.from({ length: count }, (_, index) => index);
    const step = Math.ceil((count - 1) / Math.max(1, maxLabels - 1));
    const indices = [];
    for (let index = 0; index < count - 1; index += step) indices.push(index);
    if (count - 1 - indices[indices.length - 1] < step / 2) indices.pop();
    indices.push(count - 1);
    return indices;
}

function node(tag, attributes = {}, text = null) {
    const element = document.createElementNS(SVG_NS, tag);
    for (const [name, value] of Object.entries(attributes)) element.setAttribute(name, String(value));
    if (text !== null) element.textContent = text;
    return element;
}

export default ({ points = [], height = 260 } = {}) => ({
    points,
    height,
    width: 0,
    active: null,
    view: 'chart',
    observer: null,
    formatVnd,

    init() {
        this.observer = new ResizeObserver((entries) => {
            const width = Math.floor(entries[0].contentRect.width);
            if (width > 0 && width !== this.width) {
                this.width = width;
                this.draw();
            }
        });
        this.observer.observe(this.$refs.frame);
    },

    destroy() {
        this.observer?.disconnect();
    },

    get isEmpty() {
        return this.points.every((point) => point.sales === 0);
    },

    get activePoint() {
        return this.active === null ? null : this.points[this.active];
    },

    layout() {
        const innerWidth = Math.max(1, this.width - MARGIN.left - MARGIN.right);
        const innerHeight = this.height - MARGIN.top - MARGIN.bottom;
        const ticks = niceTicks(Math.max(0, ...this.points.map((point) => point.sales)));
        const topValue = ticks[ticks.length - 1] || 1;
        const count = this.points.length;
        return {
            innerWidth,
            innerHeight,
            ticks,
            x: (index) => MARGIN.left + (count <= 1 ? innerWidth / 2 : (index * innerWidth) / (count - 1)),
            y: (value) => MARGIN.top + innerHeight - (value / topValue) * innerHeight,
        };
    },

    draw() {
        const svg = this.$refs.svg;
        svg.replaceChildren();
        svg.setAttribute('width', this.width);
        svg.setAttribute('height', this.height);
        svg.setAttribute('viewBox', `0 0 ${this.width} ${this.height}`);
        if (!this.width || this.points.length === 0) return;

        const { innerWidth, ticks, x, y } = this.layout();
        const baseline = y(0);

        for (const tick of ticks) {
            const ty = y(tick);
            svg.append(node('line', { class: tick === 0 ? 'viz-axis' : 'viz-grid', x1: MARGIN.left, x2: MARGIN.left + innerWidth, y1: ty, y2: ty }));
            svg.append(node('text', { class: 'viz-tick', x: MARGIN.left - 8, y: ty, 'text-anchor': 'end', 'dominant-baseline': 'middle' }, compactVnd(tick)));
        }

        const maxLabels = Math.max(2, Math.floor(innerWidth / 72));
        for (const index of pickLabelIndices(this.points.length, maxLabels)) {
            svg.append(node('text', { class: 'viz-tick', x: x(index), y: this.height - 8, 'text-anchor': 'middle' }, this.points[index].label));
        }

        if (this.isEmpty) return;

        const line = this.points.map((point, index) => `${index ? 'L' : 'M'}${x(index).toFixed(1)},${y(point.sales).toFixed(1)}`).join('');
        const lastX = x(this.points.length - 1).toFixed(1);
        svg.append(node('path', { class: 'viz-area', d: `${line}L${lastX},${baseline}L${x(0).toFixed(1)},${baseline}Z` }));
        svg.append(node('path', { class: 'viz-line', d: line }));

        // One direct label: the peak bucket.
        const peak = this.points.reduce((best, point, index) => (point.sales > this.points[best].sales ? index : best), 0);
        const peakX = Math.min(Math.max(x(peak), MARGIN.left + 40), MARGIN.left + innerWidth - 40);
        svg.append(node('circle', { class: 'viz-dot', cx: x(peak), cy: y(this.points[peak].sales), r: 4 }));
        svg.append(node('text', { class: 'viz-peak-label', x: peakX, y: y(this.points[peak].sales) - 12, 'text-anchor': 'middle' }, `Cao nhất ${compactVnd(this.points[peak].sales)}`));

        const cursor = node('g', { class: 'viz-cursor', 'data-cursor': '' });
        cursor.append(node('line', { y1: MARGIN.top - 8, y2: baseline }));
        cursor.append(node('circle', { class: 'viz-dot', r: 4 }));
        svg.append(cursor);
        this.drawCursor();
    },

    drawCursor() {
        const cursor = this.$refs.svg.querySelector('[data-cursor]');
        if (!cursor) return;
        if (this.active === null) {
            cursor.style.display = 'none';
            return;
        }
        const { x, y } = this.layout();
        const cx = x(this.active);
        const [rule, dot] = cursor.children;
        rule.setAttribute('x1', cx);
        rule.setAttribute('x2', cx);
        dot.setAttribute('cx', cx);
        dot.setAttribute('cy', y(this.points[this.active].sales));
        cursor.style.display = '';
    },

    get tooltipStyle() {
        if (this.active === null || !this.width) return '';
        const { x } = this.layout();
        const left = Math.min(Math.max(x(this.active), 90), this.width - 90);
        return `left: ${left}px`;
    },

    moveTo(index) {
        this.active = index;
        this.drawCursor();
    },

    pointerMove(event) {
        const rect = this.$refs.svg.getBoundingClientRect();
        const { innerWidth } = this.layout();
        this.moveTo(nearestIndex(event.clientX - rect.left, this.points.length, MARGIN.left, innerWidth));
    },

    clear() {
        this.active = null;
        this.drawCursor();
    },

    keydown(event) {
        const last = this.points.length - 1;
        const moves = { ArrowLeft: -1, ArrowRight: 1 };
        if (event.key in moves) {
            event.preventDefault();
            this.moveTo(Math.min(last, Math.max(0, (this.active ?? last) + moves[event.key])));
        } else if (event.key === 'Home' || event.key === 'End') {
            event.preventDefault();
            this.moveTo(event.key === 'Home' ? 0 : last);
        } else if (event.key === 'Escape') {
            this.clear();
        }
    },
});
