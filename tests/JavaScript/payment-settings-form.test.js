import test from 'node:test';
import assert from 'node:assert/strict';
import paymentSettingsForm, { previewQrUrl, randomKey } from '../../resources/js/payment-settings-form.js';

// test() reads `document` for the CSRF token, which doesn't exist under Node's test runner.
globalThis.document ??= { querySelector: () => null };

test('the preview QR uses the typed account, upper-cased', () => {
    const form = paymentSettingsForm({ bankId: 'mb', accountNumber: ' 0123456789 ', accountName: 'nguyen van a' });

    assert.equal(form.qrUrl(), 'https://img.vietqr.io/image/MB-0123456789-compact2.png?amount=10000&addInfo=DH+TEST&accountName=NGUYEN+VAN+A');
});

test('an empty field previews the .env value, and no account means no preview', () => {
    const form = paymentSettingsForm({ bankId: '', accountNumber: '', fallback: { bankId: 'VCB', accountNumber: '111', accountName: '' } });
    assert.match(form.qrUrl(), /\/VCB-111-compact2\.png/);

    assert.equal(paymentSettingsForm({}).qrUrl(), '');
    assert.equal(previewQrUrl({ bankId: 'VCB', accountNumber: '' }), '');
});

test('generated keys are 48 hex characters and differ each time', () => {
    const form = paymentSettingsForm({});
    form.generateKey();

    assert.match(form.apiKey, /^[0-9a-f]{48}$/);
    assert.notEqual(randomKey(), randomKey());
});

test('the webhook check shows the server verdict, and a rate limit in plain words', async t => {
    const form = paymentSettingsForm({}, '/check');
    const fetch = t.mock.method(globalThis, 'fetch', async () => ({ ok: true, status: 200, json: async () => ({ ok: false, message: 'Không gọi được URL.' }) }));
    await form.test();
    assert.equal(fetch.mock.calls[0].arguments[0], '/check');
    assert.deepEqual([form.testOk, form.testResult, form.testing], [false, 'Không gọi được URL.', false]);

    fetch.mock.mockImplementation(async () => ({ ok: false, status: 429, json: async () => ({}) }));
    await form.test();
    assert.equal(form.testResult, 'Bạn kiểm tra quá nhanh. Vui lòng thử lại sau một phút.');

    fetch.mock.mockImplementation(async () => ({ ok: true, status: 200, json: async () => ({ ok: true, message: 'Webhook hoạt động.' }) }));
    await form.test();
    assert.deepEqual([form.testOk, form.testResult], [true, 'Webhook hoạt động.']);
});
