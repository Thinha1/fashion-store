import test from 'node:test';
import assert from 'node:assert/strict';
import { suggestSize } from '../../resources/js/size-guide.js';
import sizeChartForm from '../../resources/js/size-chart-form.js';

const rows = [
    { size: 'M', height_min: 160, height_max: 168, weight_min: 55, weight_max: 62 },
    { size: 'L', height_min: 168, height_max: 174, weight_min: 62, weight_max: 69 },
    { size: 'XL', height_min: 174, height_max: 180, weight_min: 69, weight_max: 76 },
];

test('a height and weight inside one row is an exact fit', () => {
    assert.deepEqual(suggestSize(rows, { height: '165', weight: '58' }), { size: 'M', exact: true });
    assert.deepEqual(suggestSize(rows, { height: 176, weight: 70 }), { size: 'XL', exact: true });
});

test('on a shared boundary the larger size wins', () => {
    assert.deepEqual(suggestSize(rows, { height: 168, weight: 62 }), { size: 'L', exact: true });
});

test('height and weight pointing at different sizes lean on the weight', () => {
    // Height says M, weight says L.
    assert.deepEqual(suggestSize(rows, { height: 163, weight: 66 }), { size: 'L', exact: false });
});

test('one value is enough, and values outside the chart get the nearest size', () => {
    assert.deepEqual(suggestSize(rows, { weight: 70 }), { size: 'XL', exact: true });
    assert.deepEqual(suggestSize(rows, { height: 150, weight: 45 }), { size: 'M', exact: false });
    assert.deepEqual(suggestSize(rows, { height: 195, weight: 95 }), { size: 'XL', exact: false });
});

test('nothing to suggest without a value or without ranges', () => {
    assert.equal(suggestSize(rows, { height: '', weight: '' }), null);
    assert.equal(suggestSize(rows, { height: 'abc' }), null);
    assert.equal(suggestSize([{ size: 'M', values: ['90'] }], { height: 170 }), null);
});

test('a row with only a weight range is still compared on weight', () => {
    const weightOnly = [{ size: 'S', weight_min: 45, weight_max: 52 }, { size: 'M', weight_min: 52, weight_max: 60 }];
    assert.deepEqual(suggestSize(weightOnly, { height: 170, weight: 55 }), { size: 'M', exact: false });
});

test('the chart form adds the next missing size and keeps values aligned with columns', () => {
    const form = sizeChartForm({ columns: ['Ngực'], rows: [{ size: 'S', values: ['86'] }], sizes: ['S', 'M', 'L'] });

    form.addRow();
    assert.deepEqual(form.rows.map((row) => row.size), ['S', 'M']);

    form.addColumn();
    form.rows[0].values[1] = '66';
    form.removeColumn(0);
    assert.deepEqual(form.columns, ['']);
    assert.deepEqual(form.rows[0].values, ['66']);

    form.addRow();
    form.addRow();
    assert.equal(form.rows.length, 3);

    form.removeRow(0);
    form.removeRow(0);
    form.removeRow(0);
    assert.equal(form.rows.length, 1);
});
