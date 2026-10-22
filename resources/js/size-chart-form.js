/**
 * Alpine component for the admin size chart form: measurement columns and
 * one row per size are added and removed in place, and every input keeps a
 * `name` the server reads back as columns[i] / rows[r][...] /
 * rows[r][values][i] (see SaveSizeChartRequest).
 */
const blankRow = (size, columnCount) => ({
    size, height_min: '', height_max: '', weight_min: '', weight_max: '', values: Array(columnCount).fill(''),
});

export default ({ columns = [], rows = [], sizes = [] } = {}) => ({
    columns: [...columns],
    sizes,
    rows: rows.map((row) => ({
        ...blankRow(row.size ?? '', columns.length),
        ...Object.fromEntries(['height_min', 'height_max', 'weight_min', 'weight_max'].map((key) => [key, row[key] ?? ''])),
        values: columns.map((_, index) => row.values?.[index] ?? ''),
    })),

    /** The first size not yet in the table, so "Thêm dòng" continues S, M, L… */
    nextSize() {
        return this.sizes.find((size) => !this.rows.some((row) => row.size === size)) ?? '';
    },

    addRow() {
        if (this.rows.length >= this.sizes.length) return;
        this.rows.push(blankRow(this.nextSize(), this.columns.length));
    },

    removeRow(index) {
        if (this.rows.length > 1) this.rows.splice(index, 1);
    },

    addColumn() {
        if (this.columns.length >= 8) return;
        this.columns.push('');
        this.rows.forEach((row) => row.values.push(''));
    },

    removeColumn(index) {
        this.columns.splice(index, 1);
        this.rows.forEach((row) => row.values.splice(index, 1));
    },
});
