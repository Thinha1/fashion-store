import test from 'node:test';
import assert from 'node:assert/strict';

// setFieldValue writes through the native value setter, which Node has no
// DOM for — a field class with a value accessor stands in for all three.
class FakeField {
    constructor(value = '', extra = {}) {
        this.current = value;
        this.tagName = 'INPUT';
        this.classList = { add: () => {}, remove: () => {} };
        Object.assign(this, extra);
    }

    get value() { return this.current; }

    set value(value) { this.current = value; }

    dispatchEvent() { return true; }

    addEventListener() {}

    closest() { return null; }
}
globalThis.HTMLInputElement ??= FakeField;
globalThis.HTMLTextAreaElement ??= FakeField;
globalThis.HTMLSelectElement ??= FakeField;
globalThis.document ??= { querySelector: () => null, getElementById: () => null };

const {
    applySetFields, describeProductForm, describeVariantChanges, matchesVariant, planVariantChanges, readVariantRows, suggestSku,
} = await import('../../resources/js/product-ai-chat.js');

/** One variant row with the inputs the widget reads and writes. */
function fakeRow(list, { size = '', color = '', sku = '', price = '', stock = '' } = {}) {
    const hidden = new FakeField(price);
    const wrapper = { querySelector: () => hidden };
    const fields = {
        'select[name$="[size]"]': new FakeField(size),
        '[name$="[color]"]': new FakeField(color),
        'input[name$="[sku]"]': new FakeField(sku),
        '.currency-input input[type="text"]': new FakeField(price, { closest: () => wrapper }),
        '.currency-input input[type="hidden"]': hidden,
        'input[name$="[stock_quantity]"]': new FakeField(stock),
        'input[type="file"]': null,
    };

    return {
        fields,
        querySelector: (selector) => fields[selector] ?? null,
        remove() { list.children = list.children.filter((row) => row !== this); },
    };
}

/** A product form whose variant list supports what applySetFields touches. */
function fakeForm(rows, name = 'Áo Polo') {
    const list = {
        children: [],
        querySelectorAll: () => [...list.children],
        appendChild(row) { list.children = [...list.children.filter((r) => r !== row), row]; },
        get lastElementChild() { return list.children.at(-1); },
    };
    list.children = rows.map((values) => fakeRow(list, values));
    const nameField = new FakeField(name);
    const form = {
        list,
        querySelector: (selector) => ({ '#variants-list': list, '#name': nameField }[selector] ?? null),
        querySelectorAll: (selector) => (selector === '#variants-list [data-variant-row]' ? [...list.children] : []),
    };
    const alpine = { $data: () => ({ addVariant: () => list.children.push(fakeRow(list)) }), initTree: () => {} };

    return { form, alpine };
}

const summary = (form) => readVariantRows(form).map(({ size, color, sku, price, stock }) => ({ size, color, sku, price, stock }));

const shirt = [
    { size: 'M', color: 'Đen', sku: 'CM-POLO-D-M', price: '', stock: '10' },
    { size: 'L', color: 'Đen', sku: 'CM-POLO-D-L', price: '', stock: '8' },
    { size: '2XL', color: 'Đen', sku: 'CM-POLO-D-2XL', price: '', stock: '3' },
];

test('planVariantChanges adds a size for every existing color and keeps the other rows', () => {
    const rows = [{ size: 'M', color: 'Đen' }, { size: 'M', color: 'Trắng' }];
    assert.deepEqual(planVariantChanges(rows, { sizes: { add: ['XL'] } }), {
        add: [{ size: 'XL', color: 'Đen' }, { size: 'XL', color: 'Trắng' }], remove: [], truncated: false,
    });
});

test('planVariantChanges adds a color across every size and replaces size-only placeholders', () => {
    const rows = [{ size: 'M', color: '' }, { size: 'L', color: '' }];
    assert.deepEqual(planVariantChanges(rows, { colors: { add: ['Đen'] } }), {
        add: [{ size: 'M', color: 'Đen' }, { size: 'L', color: 'Đen' }], remove: [0, 1], truncated: false,
    });
});

test('planVariantChanges removes only the rows of a removed size', () => {
    assert.deepEqual(planVariantChanges(shirt, { sizes: { remove: ['m'] } }), { add: [], remove: [0], truncated: false });
});

test('planVariantChanges turns a full value list into adds and removes', () => {
    assert.deepEqual(planVariantChanges(shirt, { sizes: { value: ['L', '2XL', 'XL'] } }), {
        add: [{ size: 'XL', color: 'Đen' }], remove: [0], truncated: false,
    });
    assert.deepEqual(planVariantChanges(shirt, { sizes: { value: [] } }).remove, [0, 1, 2]);
});

test('planVariantChanges stops at the row limit and says so', () => {
    const plan = planVariantChanges([], { sizes: { add: ['S', 'M', 'L'] }, colors: { add: ['Đen', 'Trắng'] } }, 4);
    assert.equal(plan.add.length, 4);
    assert.equal(plan.truncated, true);
});

test('suggestSku reuses the product SKU stem and never repeats one on the form', () => {
    assert.equal(suggestSku(['CM-POLO-D-M'], { size: 'XL', color: 'Xanh navy' }), 'CM-POLO-XN-XL');
    assert.equal(suggestSku(['CM-POLO-D-M', 'CM-POLO-D-XL'], { size: 'XL', color: 'Đen' }), 'CM-POLO-D-XL-2');
    assert.equal(suggestSku([], { size: 'M', color: 'Đỏ' }, 'Áo thun basic'), 'ATB-D-M');
});

test('matchesVariant compares sizes and colors loosely; an empty side matches any', () => {
    assert.equal(matchesVariant({ size: '2XL', color: 'Đen' }, { sizes: ['2xl'] }), true);
    assert.equal(matchesVariant({ size: 'L', color: 'Đen' }, { sizes: ['2XL'] }), false);
    assert.equal(matchesVariant({ size: 'L', color: 'Đen' }, { sizes: ['L'], colors: ['đen '] }), true);
    assert.equal(matchesVariant({ size: 'L', color: 'Trắng' }, { sizes: ['L'], colors: ['Đen'] }), false);
});

test('a variant price scoped to size 2XL changes only that variant', () => {
    const { form, alpine } = fakeForm(shirt);
    const { variantChanges, restore } = applySetFields([{ field: 'variant_price', value: '350000', match: { sizes: ['2XL'] } }], form, alpine);

    assert.deepEqual(summary(form).map(({ size, price }) => [size, price]), [['M', ''], ['L', ''], ['2XL', '350000']]);
    assert.deepEqual(variantChanges, { updated: 1, unmatched: [] });
    assert.equal(describeVariantChanges(variantChanges), 'Đã cập nhật giá/tồn kho 1 biến thể.');

    restore();
    assert.deepEqual(summary(form), shirt);
});

test('a variant stock with no match still sets every variant', () => {
    const { form, alpine } = fakeForm(shirt);
    applySetFields([{ field: 'variant_stock', value: '20' }], form, alpine);
    assert.deepEqual(summary(form).map(({ stock }) => stock), ['20', '20', '20']);
});

test('a match naming no variant on the form changes nothing and says so', () => {
    const { form, alpine } = fakeForm(shirt);
    const { variantChanges } = applySetFields([{ field: 'variant_price', value: '350000', match: { sizes: ['3XL'] } }], form, alpine);

    assert.deepEqual(summary(form), shirt);
    assert.equal(describeVariantChanges(variantChanges), 'Không có biến thể nào size 3XL trên form.');
});

test('adding a size keeps the existing rows untouched and gives the new ones a SKU and this turn\'s stock', () => {
    const { form, alpine } = fakeForm(shirt);
    const original = [...form.list.children];
    const { variantChanges } = applySetFields([{ field: 'sizes', add: ['XL'] }, { field: 'variant_stock', value: '5' }], form, alpine);

    assert.deepEqual(form.list.children.slice(0, 3), original);
    assert.deepEqual(summary(form).slice(0, 3), shirt);
    assert.deepEqual(summary(form)[3], { size: 'XL', color: 'Đen', sku: 'CM-POLO-D-XL', price: '', stock: '5' });
    assert.equal(describeVariantChanges(variantChanges), 'Đã thêm 1 biến thể.');
});

test('undo of a size removal brings back the very same row nodes in their order', () => {
    const { form, alpine } = fakeForm(shirt);
    const original = [...form.list.children];
    const { variantChanges, restore } = applySetFields([{ field: 'sizes', remove: ['L'] }], form, alpine);

    assert.deepEqual(summary(form).map(({ size }) => size), ['M', '2XL']);
    assert.match(describeVariantChanges(variantChanges), /^Đã gỡ 1 biến thể\. .*Hoàn tác/);

    restore();
    assert.deepEqual(form.list.children, original);
});

test('describeProductForm reports the form, unsaved variant values included', () => {
    const { form } = fakeForm(shirt.slice(0, 1));
    const context = describeProductForm(form);

    assert.equal(context.mode, 'create');
    assert.equal(context.name, 'Áo Polo');
    assert.deepEqual(context.variants, [shirt[0]]);
    assert.equal(describeProductForm(null), null);
});
