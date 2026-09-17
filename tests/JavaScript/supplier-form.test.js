import test from 'node:test';
import assert from 'node:assert/strict';
import supplierForm from '../../resources/js/supplier-form.js';

const company = { tax_code: '0316794479', name: 'Công ty mẫu', address: 'Địa chỉ mẫu' };
const response = (data = company) => ({ ok: true, json: async () => ({ data }) });

test('successful lookup immediately fills name and address', async t => {
    const fetch = t.mock.method(globalThis, 'fetch', async () => response());
    const form = supplierForm({ taxCode: company.tax_code, name: 'Tên đang nhập', address: 'Địa chỉ đang nhập' }, '/lookup');
    await form.lookup();
    assert.strictEqual(fetch.mock.calls[0].arguments[0], '/lookup?tax_code=0316794479');
    assert.strictEqual(form.name, company.name);
    assert.strictEqual(form.address, company.address);
    assert.ok(form.message);
});

test('invalid input never calls the API', async t => {
    const fetch = t.mock.method(globalThis, 'fetch', async () => response());
    const form = supplierForm({ taxCode: '123' }, '/lookup');
    await form.lookup();
    assert.strictEqual(fetch.mock.calls.length, 0);
    assert.ok(form.error);
});

test('changing tax code discards an in-flight result', async t => {
    let finish;
    t.mock.method(globalThis, 'fetch', () => new Promise(resolve => { finish = resolve; }));
    const form = supplierForm({ taxCode: company.tax_code, name: 'Giữ lại' }, '/lookup');
    const pending = form.lookup();
    form.taxCode = '0100000000';
    form.resetLookup();
    finish(response());
    await pending;
    assert.strictEqual(form.name, 'Giữ lại');
    assert.strictEqual(form.loading, false);
});

test('provider and network failures keep manual entries intact', async t => {
    t.mock.method(globalThis, 'fetch', async () => ({ ok: false, status: 404, json: async () => ({ message: 'Không tìm thấy' }) }));
    const form = supplierForm({ taxCode: company.tax_code, name: 'Giữ lại' }, '/lookup');
    await form.lookup();
    assert.strictEqual(form.error, 'Không tìm thấy');
    assert.strictEqual(form.name, 'Giữ lại');
    t.mock.method(globalThis, 'fetch', async () => { throw new Error('offline'); });
    await form.lookup();
    assert.strictEqual(form.error, 'Không kết nối được dịch vụ tra cứu. Bạn vẫn có thể nhập thông tin thủ công.');
    assert.strictEqual(form.name, 'Giữ lại');
    assert.strictEqual(form.loading, false);
});

test('malformed JSON and incomplete success responses never erase form fields', async t => {
    const form = supplierForm({ taxCode: company.tax_code, name: 'Giữ tên', address: 'Giữ địa chỉ' }, '/lookup');
    const malformedResponses = [
        { ok: false, json: async () => { throw new SyntaxError('HTML response'); } },
        { ok: true, json: async () => ({}) },
        { ok: true, json: async () => ({ data: { name: 'Thiếu địa chỉ' } }) },
    ];
    for (const malformed of malformedResponses) {
        t.mock.method(globalThis, 'fetch', async () => malformed);
        await form.lookup();
        assert.strictEqual(form.name, 'Giữ tên');
        assert.strictEqual(form.address, 'Giữ địa chỉ');
        assert.ok(form.error);
        assert.strictEqual(form.loading, false);
    }
});
