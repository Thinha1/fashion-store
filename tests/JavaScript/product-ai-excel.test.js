import test from 'node:test';
import assert from 'node:assert/strict';
import productAiChat, {
    isExcelFile, describeExcelColumns, describeRequestFailure, draftFromExcelProduct, buildFillPlan, toWireMessages,
} from '../../resources/js/product-ai-chat.js';

// readExcel()/fillExcelProduct() read the CSRF token, sessionStorage and the product form,
// none of which exist under Node's test runner — minimal stand-ins are enough.
globalThis.document ??= { querySelector: () => null, getElementById: () => null };

function fakeSessionStorage() {
    const store = new Map();

    return {
        getItem: (key) => (store.has(key) ? store.get(key) : null),
        setItem: (key, value) => store.set(key, String(value)),
        removeItem: (key) => store.delete(key),
    };
}

const product = {
    name: 'Áo thun cổ tròn', category: 'Áo nam', brand: 'Mộc Daily', price: 199000, description: 'Cotton 100%', status: null,
    variants: [
        { size: 'M', color: 'Trắng', sku: 'ATB-M-TRANG', price: null },
        { size: 'L', color: 'Đen', sku: 'ATB-L-DEN', price: 219000 },
    ],
};

test('isExcelFile() accepts .xlsx by extension or MIME type and nothing else', () => {
    assert.equal(isExcelFile({ name: 'Bang-Gia.XLSX', type: '' }), true);
    assert.equal(isExcelFile({ name: 'x', type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }), true);
    assert.equal(isExcelFile({ name: 'anh.png', type: 'image/png' }), false);
    assert.equal(isExcelFile({ name: 'cu.xls', type: 'application/vnd.ms-excel' }), false);
    assert.equal(isExcelFile(undefined), false);
});

test('describeExcelColumns() shows each mapped column with its header text', () => {
    assert.deepEqual(describeExcelColumns({ name: { column: 'B', header: 'Tên hàng' }, price: { column: 'H', header: '' } }), [
        'Tên ← cột B (Tên hàng)',
        'Giá ← cột H',
    ]);
    assert.deepEqual(describeExcelColumns(undefined), []);
});

test('describeRequestFailure() prefers a validation message, then the server message, then the fallback', () => {
    assert.equal(describeRequestFailure({ status: 429 }, null, 'x'), 'Bạn thao tác quá nhanh. Vui lòng thử lại sau một phút.');
    assert.equal(describeRequestFailure({ status: 422 }, { message: 'm', errors: { file: ['Chỉ nhận file .xlsx.'] } }, 'x'), 'Chỉ nhận file .xlsx.');
    assert.equal(describeRequestFailure({ status: 502 }, { message: 'AI chưa xác định được cột.' }, 'x'), 'AI chưa xác định được cột.');
    assert.equal(describeRequestFailure({ status: 500 }, null, 'Dự phòng'), 'Dự phòng');
});

test('an Excel product becomes a fill plan with explicit variants (size, color, SKU, price) and no grid guessing', () => {
    const plan = buildFillPlan(draftFromExcelProduct(product));

    assert.equal(plan.name, 'Áo thun cổ tròn');
    assert.equal(plan.price, '199000');
    assert.equal(plan.category, 'Áo nam');
    assert.equal(plan.brand, 'Mộc Daily');
    assert.equal(plan.status, undefined, 'a file without a status leaves the form default alone');
    assert.deepEqual(plan.variantRows, [
        { size: 'M', color: 'Trắng', sku: 'ATB-M-TRANG', imageIndex: undefined, price: undefined, stock: undefined },
        { size: 'L', color: 'Đen', sku: 'ATB-L-DEN', imageIndex: undefined, price: '219000', stock: undefined },
    ]);
});

test('a stated status is carried into the plan, and variants beyond the form limit are cut', () => {
    const many = { ...product, status: 'archived', variants: Array.from({ length: 15 }, (_, i) => ({ size: 'M', color: `Màu ${i}`, sku: `S-${i}`, price: null })) };
    const plan = buildFillPlan(draftFromExcelProduct(many));

    assert.equal(plan.status, 'archived');
    assert.equal(plan.variantRows.length, 12);
});

test('readExcel() uploads the file, shows a local card, and the chat model never sees that card', async (t) => {
    globalThis.sessionStorage = fakeSessionStorage();
    globalThis.window = {};
    const fetchMock = t.mock.method(globalThis, 'fetch', async () => ({
        ok: true, json: async () => ({ data: { product_count: 1, variant_count: 2, products: [product], columns: {}, skipped: [], warnings: [] } }),
    }));
    const chat = productAiChat('/assist', '/create', undefined, '/excel');

    await chat.readExcel({ name: 'bang-gia.xlsx' });

    const [url, options] = fetchMock.mock.calls[0].arguments;
    assert.equal(url, '/excel');
    assert.equal(options.method, 'POST');
    assert.ok(options.body instanceof FormData);
    assert.equal(chat.messages.length, 1);
    assert.equal(chat.messages[0].local, true);
    assert.equal(chat.messages[0].excel.fileName, 'bang-gia.xlsx');
    assert.deepEqual(chat.messages[0].excel.filled, []);
    assert.deepEqual(toWireMessages(chat.messages), []);
    assert.equal(chat.loading, false);
    assert.equal(chat.error, '');
});

test('readExcel() reports the server message when the file cannot be read', async (t) => {
    globalThis.sessionStorage = fakeSessionStorage();
    globalThis.window = {};
    t.mock.method(globalThis, 'fetch', async () => ({ ok: false, status: 502, json: async () => ({ message: 'AI chưa xác định được cột tên sản phẩm.' }) }));
    const chat = productAiChat('/assist', '/create', undefined, '/excel');

    await chat.readExcel({ name: 'bang-gia.xlsx' });

    assert.equal(chat.error, 'AI chưa xác định được cột tên sản phẩm.');
    assert.equal(chat.messages.length, 0);
    assert.equal(chat.loading, false);
});

test('readExcel() refuses politely when the page has no Excel endpoint', async () => {
    const chat = productAiChat('/assist', '/create');

    await chat.readExcel({ name: 'bang-gia.xlsx' });

    assert.match(chat.error, /chưa hỗ trợ/);
});

test('fillExcelProduct() hands the product to the add-product page and never submits anything', () => {
    globalThis.sessionStorage = fakeSessionStorage();
    const defaultDocument = globalThis.document;
    globalThis.window = { location: { href: '' } };
    globalThis.document = { querySelector: () => null, getElementById: () => null };
    try {
        const chat = productAiChat('/assist', '/create', undefined, '/excel');
        chat.messages = [{ role: 'assistant', local: true, blocks: [], excel: { products: [product], filled: [] } }];

        chat.fillExcelProduct(0, 0);

        assert.equal(chat.draft.name, 'Áo thun cổ tròn');
        assert.equal(chat.draft.variants.length, 2);
        assert.equal(chat.draftMessageIndex, null, 'the Excel card is the review surface, no extra draft card');
        assert.equal(chat.pendingFill, true);
        assert.equal(globalThis.window.location.href, '/create');
        assert.deepEqual(chat.messages[0].excel.filled, [0]);
    } finally {
        globalThis.document = defaultDocument;
    }
});

test('fillExcelProduct() ignores an unknown product', () => {
    const chat = productAiChat('/assist', '/create', undefined, '/excel');
    chat.messages = [{ role: 'assistant', local: true, blocks: [], excel: { products: [], filled: [] } }];

    chat.fillExcelProduct(0, 3);
    chat.fillExcelProduct(9, 0);

    assert.equal(chat.draft, null);
    assert.equal(chat.pendingFill, false);
});
