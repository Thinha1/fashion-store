import test from 'node:test';
import assert from 'node:assert/strict';
import discountTargets, { fold } from '../../resources/js/discount-targets.js';

const options = {
    brands: [{ id: 1, name: 'Coolmate' }, { id: 2, name: 'Levents' }],
    products: [
        { id: 10, name: 'Áo khoác gió', brand: 1 },
        { id: 11, name: 'Quần jean', brand: 1 },
        { id: 12, name: 'Áo thun', brand: 2 },
    ],
    variants: [
        { id: 100, name: 'Áo khoác gió', detail: 'M / Đen (AK-M)', product: 10, brand: 1 },
        { id: 101, name: 'Áo khoác gió', detail: 'L / Đen (AK-L)', product: 10, brand: 1 },
        { id: 102, name: 'Quần jean', detail: '30 / Xanh (QJ-30)', product: 11, brand: 1 },
        { id: 103, name: 'Áo thun', detail: 'M / Trắng (AT-M)', product: 12, brand: 2 },
    ],
    selected: {},
};

test('fold ignores case and Vietnamese accents', () => {
    assert.strictEqual(fold('Áo Khoác Đen'), 'ao khoac den');
});

test('search finds items without typing accents and across brand names', () => {
    const picker = discountTargets(options);
    picker.setTab('product');
    picker.search = 'ao khoac';
    assert.deepEqual(picker.matches.map(item => item.id), [10]);
    picker.search = 'levents ao';
    assert.deepEqual(picker.matches.map(item => item.id), [12]);
});

test('toggle ticks and unticks, and each tab keeps its own picks', () => {
    const picker = discountTargets(options);
    picker.toggle(1);
    picker.setTab('variant');
    picker.toggle(103);
    assert.deepEqual(picker.selected, { brand: [1], product: [], variant: [103] });
    assert.strictEqual(picker.total, 2);
    picker.toggle(103);
    assert.strictEqual(picker.count('variant'), 0);
});

test('select shown ticks every match of the search, and only those', () => {
    const picker = discountTargets(options);
    picker.setTab('variant');
    picker.search = 'khoac';
    picker.selectShown();
    assert.deepEqual(picker.selected.variant, [100, 101]);
    picker.clearTab();
    assert.deepEqual(picker.selected.variant, []);
});

test('only-selected narrows the list to what is ticked', () => {
    const picker = discountTargets({ ...options, selected: { product: [11, 12] } });
    picker.setTab('product');
    picker.onlySelected = true;
    assert.deepEqual(picker.matches.map(item => item.id), [11, 12]);
});

test('covered counts each variant once however it was picked', () => {
    const picker = discountTargets(options);
    picker.toggle(1); // brand Coolmate: variants 100, 101, 102
    picker.setTab('product');
    picker.toggle(10); // already covered by the brand
    picker.setTab('variant');
    picker.toggle(103);
    assert.strictEqual(picker.covered, 4);
});

test('over the limit is flagged', () => {
    const picker = discountTargets(options, true, 2);
    picker.toggle(1);
    assert.strictEqual(picker.covered, 3);
    assert.ok(picker.overLimit);
    picker.clearTab();
    picker.setTab('variant');
    picker.toggle(100);
    assert.ok(!picker.overLimit);
});

test('the list shows a first page and says how many more there are', () => {
    const many = Array.from({ length: 200 }, (_, index) => ({ id: index + 1, name: `Sản phẩm ${index + 1}`, brand: 1 }));
    const picker = discountTargets({ ...options, products: many });
    picker.setTab('product');
    assert.strictEqual(picker.shown.length, 150);
    assert.strictEqual(picker.hidden, 50);
    picker.selectShown();
    assert.strictEqual(picker.count('product'), 200);
});

test('server-sent selections are cleaned up', () => {
    const picker = discountTargets({ ...options, selected: { brand: ['1', 1, 0, 'x'], product: null } });
    assert.deepEqual(picker.selected, { brand: [1], product: [], variant: [] });
});

test('picks lists every ticked id with its kind for the hidden inputs', () => {
    const picker = discountTargets(options);
    picker.toggle(2);
    picker.setTab('variant');
    picker.toggle(101);
    assert.deepEqual(picker.picks, [{ type: 'brand', id: 2 }, { type: 'variant', id: 101 }]);
});
