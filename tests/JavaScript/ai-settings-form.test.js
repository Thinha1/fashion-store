import test from 'node:test';
import assert from 'node:assert/strict';
import aiSettingsForm from '../../resources/js/ai-settings-form.js';

// test() reads `document` for the CSRF token, which doesn't exist under Node's test runner.
globalThis.document ??= { querySelector: () => null };

const initial = { endpoint: 'https://ai.example.test/v1', model: 'qwen', maxTokens: 2048 };

test('seeds its fields from the form\'s initial values, not the AI response', async t => {
    t.mock.method(globalThis, 'fetch', async () => ({ ok: true, json: async () => ({ ok: true, message: 'Kết nối thành công.' }) }));
    const form = aiSettingsForm(initial, '/test');
    assert.strictEqual(form.endpoint, initial.endpoint);
    assert.strictEqual(form.model, initial.model);
    assert.strictEqual(form.maxTokens, initial.maxTokens);
    assert.strictEqual(form.apiKey, '');
});

test('test() posts the current field values and surfaces a success result', async t => {
    const fetchMock = t.mock.method(globalThis, 'fetch', async () => ({ ok: true, json: async () => ({ ok: true, message: 'Kết nối thành công.' }) }));
    const form = aiSettingsForm(initial, '/test');
    form.apiKey = 'sk-typed';
    await form.test();
    const body = JSON.parse(fetchMock.mock.calls[0].arguments[1].body);
    assert.strictEqual(body.endpoint, initial.endpoint);
    assert.strictEqual(body.api_key, 'sk-typed');
    assert.strictEqual(form.testOk, true);
    assert.strictEqual(form.testResult, 'Kết nối thành công.');
    assert.strictEqual(form.testing, false);
});

test('test() surfaces a failure result without throwing', async t => {
    t.mock.method(globalThis, 'fetch', async () => ({ ok: true, json: async () => ({ ok: false, message: 'Sai API key.' }) }));
    const form = aiSettingsForm(initial, '/test');
    await form.test();
    assert.strictEqual(form.testOk, false);
    assert.strictEqual(form.testResult, 'Sai API key.');
});

test('test() surfaces a friendly message on rate limiting', async t => {
    t.mock.method(globalThis, 'fetch', async () => ({ ok: false, status: 429, json: async () => ({}) }));
    const form = aiSettingsForm(initial, '/test');
    await form.test();
    assert.strictEqual(form.testOk, false);
    assert.match(form.testResult, /quá nhanh/);
});

test('test() surfaces a connection error and clears the loading state', async t => {
    t.mock.method(globalThis, 'fetch', async () => { throw new Error('offline'); });
    const form = aiSettingsForm(initial, '/test');
    await form.test();
    assert.strictEqual(form.testOk, false);
    assert.ok(form.testResult);
    assert.strictEqual(form.testing, false);
});

test('test() ignores a call made while a request is already in flight', async t => {
    let callCount = 0;
    t.mock.method(globalThis, 'fetch', async () => {
        callCount++;

        return { ok: true, json: async () => ({ ok: true }) };
    });
    const form = aiSettingsForm(initial, '/test');
    form.testing = true;
    await form.test();
    assert.strictEqual(callCount, 0);
});
