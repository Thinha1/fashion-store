import test from 'node:test';
import assert from 'node:assert/strict';
import { compactVnd, formatVnd, nearestIndex, niceTicks, pickLabelIndices } from '../../resources/js/sales-chart.js';

test('ticks start at zero and end on a round number at or above the max', () => {
    assert.deepEqual(niceTicks(0), [0]);
    assert.deepEqual(niceTicks(870000), [0, 250000, 500000, 750000, 1000000]);
    assert.deepEqual(niceTicks(4_200_000), [0, 2_000_000, 4_000_000, 6_000_000]);
    assert.deepEqual(niceTicks(95), [0, 25, 50, 75, 100]);
});

test('the pointer snaps to the closest bucket and never leaves the range', () => {
    assert.equal(nearestIndex(56, 31, 56, 600), 0);
    assert.equal(nearestIndex(56 + 300, 31, 56, 600), 15);
    assert.equal(nearestIndex(56 + 309, 31, 56, 600), 15);
    assert.equal(nearestIndex(-40, 31, 56, 600), 0);
    assert.equal(nearestIndex(9999, 31, 56, 600), 30);
    assert.equal(nearestIndex(120, 1, 56, 600), 0);
});

test('x labels are evenly spaced and always keep the first and last bucket', () => {
    assert.deepEqual(pickLabelIndices(5, 8), [0, 1, 2, 3, 4]);
    const labels = pickLabelIndices(30, 6);
    assert.equal(labels[0], 0);
    assert.equal(labels.at(-1), 29);
    assert.ok(labels.length <= 6);
    assert.deepEqual(pickLabelIndices(0, 6), []);
});

test('money reads in Vietnamese: grouped dots in full, short units on the axis', () => {
    assert.equal(formatVnd(1250000).replace(/\s/g, ' '), '1.250.000 ₫');
    assert.equal(compactVnd(850000), '850k');
    assert.equal(compactVnd(1500000), '1,5 tr');
    assert.equal(compactVnd(2_000_000_000), '2 tỷ');
    assert.equal(compactVnd(0), '0');
});
