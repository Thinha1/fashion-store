import { closeDialog, openDialog } from './dialog.js';

const number = (value) => {
    const parsed = Number(String(value ?? '').replace(',', '.'));

    return value === '' || value === null || value === undefined || !Number.isFinite(parsed) || parsed <= 0 ? null : parsed;
};

/** How far `value` lies outside [min, max]; 0 inside it, null when the row has no such range. */
function distance(value, min, max) {
    const low = number(min) ?? number(max);
    const high = number(max) ?? number(min);
    if (low === null) return null;
    if (value < low) return low - value;

    return value > high ? value - high : 0;
}

/**
 * The size-chart row that best fits a height (cm) and weight (kg). A row
 * whose ranges contain both is an exact fit; otherwise the nearest row wins,
 * weight counting a little more than height (it decides the width), and a
 * tie goes to the larger size — easier to wear than one too small.
 *
 * @param  Array<{size: string, height_min?: number, height_max?: number, weight_min?: number, weight_max?: number}>  rows  in size order
 * @return {{size: string, exact: boolean}|null} null when neither value is given or no row has a usable range
 */
export function suggestSize(rows, { height, weight } = {}) {
    const h = number(height);
    const w = number(weight);
    if (h === null && w === null) return null;

    let best = null;
    rows.forEach((row) => {
        const hd = h === null ? null : distance(h, row.height_min, row.height_max);
        const wd = w === null ? null : distance(w, row.weight_min, row.weight_max);
        if (hd === null && wd === null) return;
        const score = (hd ?? 0) / 5 + (wd ?? 0) / 4;
        const exact = (h === null || hd === 0) && (w === null || wd === 0);
        if (best === null || score <= best.score) best = { size: row.size, exact, score };
    });

    return best && { size: best.size, exact: best.exact };
}

/**
 * Alpine component for the "Bảng size" dialog on the product page. It sits
 * inside productDetail's scope, so the Blade calls that component's
 * pickSize/hasVariant directly to apply the suggestion.
 */
export default ({ rows = [] } = {}) => ({
    rows,
    height: '',
    weight: '',

    get suggestion() {
        return suggestSize(this.rows, { height: this.height, weight: this.weight });
    },

    open() {
        openDialog(this.$refs.sizeGuide);
    },

    close() {
        closeDialog(this.$refs.sizeGuide);
    },
});
