import test from 'node:test';
import assert from 'node:assert/strict';
import shoppingAssistChat, { trimHistory, toWireMessages } from '../../resources/js/shopping-assist-chat.js';

// send() reads `document` for the CSRF token, which doesn't exist under
// Node's test runner. A minimal stub is enough to exercise the
// network/state logic without needing a real DOM.
globalThis.document ??= { querySelector: () => null };

function fakeSessionStorage() {
    const store = new Map();

    return {
        getItem: (key) => (store.has(key) ? store.get(key) : null),
        setItem: (key, value) => store.set(key, value),
    };
}

function withAlpineStubs(chat) {
    chat.$watch = () => {};
    chat.$nextTick = (callback) => callback();
    chat.$refs = {};

    return chat;
}

test('trimHistory leaves a short conversation untouched', () => {
    const messages = Array.from({ length: 5 }, (_, i) => ({ role: 'user', text: `turn ${i}` }));
    assert.equal(trimHistory(messages, 20), messages);
});

test('trimHistory keeps only the most recent N messages once over the limit', () => {
    const messages = Array.from({ length: 25 }, (_, i) => ({ role: 'user', text: `turn ${i}` }));
    const trimmed = trimHistory(messages, 20);
    assert.equal(trimmed.length, 20);
    assert.deepEqual(trimmed, messages.slice(-20));
});

test('toWireMessages sends the user\'s plain text but the assistant\'s raw model reply, not the friendly text', () => {
    const wire = toWireMessages([
        { role: 'user', text: 'áo sơ mi trắng giá 500k' },
        { role: 'assistant', reply: 'Để mình tìm cho bạn nhé!', raw: '{"category":"Áo sơ mi"}', products: [] },
    ]);
    assert.deepEqual(wire, [
        { role: 'user', content: 'áo sơ mi trắng giá 500k' },
        { role: 'assistant', content: '{"category":"Áo sơ mi"}' },
    ]);
});

test('toWireMessages falls back to an empty string when an assistant turn has no raw reply', () => {
    const wire = toWireMessages([{ role: 'assistant', reply: 'ok', products: [] }]);
    assert.equal(wire[0].content, '');
});

test('send() posts the conversation and stores the reply with its product cards', async t => {
    const fetchMock = t.mock.method(globalThis, 'fetch', async () => ({
        ok: true,
        json: async () => ({
            reply: 'Mình tìm được 1 sản phẩm phù hợp cho bạn:',
            products: [{ id: 1, name: 'Áo sơ mi trắng', brand: 'Local', price: '350.000 ₫', image_url: null, in_stock: true, url: '/san-pham/ao-so-mi-trang' }],
            raw: '{"category":""}',
        }),
    }));
    const chat = shoppingAssistChat('/san-pham/goi-y-ai');
    chat.input = 'áo sơ mi trắng';
    await chat.send();

    assert.equal(fetchMock.mock.calls[0].arguments[0], '/san-pham/goi-y-ai');
    const body = JSON.parse(fetchMock.mock.calls[0].arguments[1].body);
    assert.deepEqual(body.messages, [{ role: 'user', content: 'áo sơ mi trắng' }]);

    assert.equal(chat.messages.length, 2);
    assert.equal(chat.messages[1].reply, 'Mình tìm được 1 sản phẩm phù hợp cho bạn:');
    assert.equal(chat.messages[1].products.length, 1);
    assert.equal(chat.loading, false);
    assert.equal(chat.error, '');
});

test('send() does nothing without any text typed', async t => {
    const fetchMock = t.mock.method(globalThis, 'fetch', async () => ({ ok: true, json: async () => ({}) }));
    const chat = shoppingAssistChat('/san-pham/goi-y-ai');
    await chat.send();
    assert.equal(fetchMock.mock.calls.length, 0);
    assert.equal(chat.messages.length, 0);
});

test('send() ignores a call made while a request is already in flight', async t => {
    const fetchMock = t.mock.method(globalThis, 'fetch', () => new Promise(() => {}));
    const chat = shoppingAssistChat('/san-pham/goi-y-ai');
    chat.input = 'áo thun';
    chat.send();
    assert.equal(chat.loading, true);
    chat.input = 'quần jean';
    await chat.send();
    assert.equal(fetchMock.mock.calls.length, 1);
    assert.equal(chat.messages.length, 1);
});

test('send() surfaces a friendly message on rate limiting and keeps the failed turn for retry', async t => {
    t.mock.method(globalThis, 'fetch', async () => ({ ok: false, status: 429, json: async () => ({}) }));
    const chat = shoppingAssistChat('/san-pham/goi-y-ai');
    chat.input = 'áo thun';
    await chat.send();
    assert.match(chat.error, /quá nhanh/);
    assert.equal(chat.messages.length, 1);
    assert.equal(chat.messages[0].role, 'user');
});

test('send() surfaces a connection error and clears the loading state', async t => {
    t.mock.method(globalThis, 'fetch', async () => { throw new Error('network down'); });
    const chat = shoppingAssistChat('/san-pham/goi-y-ai');
    chat.input = 'áo thun';
    await chat.send();
    assert.match(chat.error, /Không kết nối/);
    assert.equal(chat.loading, false);
});

test('init() restores a conversation saved before navigating to another storefront page', () => {
    globalThis.sessionStorage = fakeSessionStorage();
    globalThis.sessionStorage.setItem('shopping-assist-chat:v1', JSON.stringify({
        open: true,
        messages: [{ role: 'user', text: 'áo thun' }],
    }));
    const chat = withAlpineStubs(shoppingAssistChat('/san-pham/goi-y-ai'));
    chat.init();
    assert.equal(chat.open, true);
    assert.equal(chat.messages.length, 1);
});

test('init() starts empty when nothing was previously saved', () => {
    globalThis.sessionStorage = fakeSessionStorage();
    const chat = withAlpineStubs(shoppingAssistChat('/san-pham/goi-y-ai'));
    chat.init();
    assert.equal(chat.open, false);
    assert.deepEqual(chat.messages, []);
});

test('persist() writes the current conversation so it survives the next page load', () => {
    globalThis.sessionStorage = fakeSessionStorage();
    const chat = withAlpineStubs(shoppingAssistChat('/san-pham/goi-y-ai'));
    chat.init();
    chat.messages.push({ role: 'user', text: 'áo thun' });
    chat.persist();
    const saved = JSON.parse(globalThis.sessionStorage.getItem('shopping-assist-chat:v1'));
    assert.equal(saved.messages.length, 1);
});

test('startNewConversation() clears the conversation and any error', () => {
    globalThis.sessionStorage = fakeSessionStorage();
    const chat = withAlpineStubs(shoppingAssistChat('/san-pham/goi-y-ai'));
    chat.init();
    chat.messages.push({ role: 'user', text: 'áo thun' });
    chat.error = 'lỗi cũ';
    chat.startNewConversation();
    assert.deepEqual(chat.messages, []);
    assert.equal(chat.error, '');
});

test('toggle() flips the open state', () => {
    const chat = shoppingAssistChat('/san-pham/goi-y-ai');
    chat.toggle();
    assert.equal(chat.open, true);
    chat.toggle();
    assert.equal(chat.open, false);
});
