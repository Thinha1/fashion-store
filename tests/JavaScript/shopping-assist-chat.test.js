import test from 'node:test';
import assert from 'node:assert/strict';
import shoppingAssistChat, {
    trimHistory, toWireMessages, QUICK_PROMPTS, REFINE_PROMPTS, parseSseFrames, describeStreamProgress,
} from '../../resources/js/shopping-assist-chat.js';

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

/** A minimal `ReadableStreamDefaultReader`-alike that hands back the whole text in one chunk, then signals done. */
function sseReaderFromText(text) {
    const chunks = [new TextEncoder().encode(text)];

    return { read: async () => (chunks.length ? { done: false, value: chunks.shift() } : { done: true, value: undefined }) };
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

test('sendQuickReply() fills the input with the chip text and sends it immediately', async t => {
    const fetchMock = t.mock.method(globalThis, 'fetch', async () => ({
        ok: true, json: async () => ({ reply: 'ok', products: [], raw: '{}' }),
    }));
    const chat = shoppingAssistChat('/san-pham/goi-y-ai');
    await chat.sendQuickReply(QUICK_PROMPTS[0]);

    assert.equal(fetchMock.mock.calls.length, 1);
    const body = JSON.parse(fetchMock.mock.calls[0].arguments[1].body);
    assert.deepEqual(body.messages, [{ role: 'user', content: QUICK_PROMPTS[0] }]);
});

test('exposes the quick-reply prompts for the empty-state chips', () => {
    const chat = shoppingAssistChat('/san-pham/goi-y-ai');
    assert.deepEqual(chat.quickPrompts, QUICK_PROMPTS);
});

test('exposes the refine prompts for chips under the latest reply', () => {
    const chat = shoppingAssistChat('/san-pham/goi-y-ai');
    assert.deepEqual(chat.refinePrompts, REFINE_PROMPTS);
});

test('init() reads the current product id from the page and sends it with every turn', async t => {
    globalThis.sessionStorage = fakeSessionStorage();
    globalThis.document = { querySelector: () => null, body: { dataset: { currentProductId: '42' } } };
    const fetchMock = t.mock.method(globalThis, 'fetch', async () => ({ ok: true, json: async () => ({ reply: 'ok', products: [], raw: '{}' }) }));
    try {
        const chat = withAlpineStubs(shoppingAssistChat('/san-pham/goi-y-ai'));
        chat.init();
        assert.equal(chat.contextProductId, 42);

        chat.input = 'áo thun';
        await chat.send();
        const body = JSON.parse(fetchMock.mock.calls[0].arguments[1].body);
        assert.equal(body.context_product_id, 42);
    } finally {
        globalThis.document = { querySelector: () => null };
    }
});

test('contextProductId stays null when there is no current-product page context', () => {
    globalThis.document = { querySelector: () => null, body: { dataset: {} } };
    try {
        const chat = withAlpineStubs(shoppingAssistChat('/san-pham/goi-y-ai'));
        chat.init();
        assert.equal(chat.contextProductId, null);
    } finally {
        globalThis.document = { querySelector: () => null };
    }
});

test('parseSseFrames splits a buffer into complete frames and keeps an incomplete tail as the remainder', () => {
    const { frames, remainder } = parseSseFrames(
        'event: delta\ndata: {"text":"a"}\n\nevent: delta\ndata: {"text":"b"}\n\nevent: do',
    );
    assert.deepEqual(frames, [
        { event: 'delta', data: { text: 'a' } },
        { event: 'delta', data: { text: 'b' } },
    ]);
    assert.equal(remainder, 'event: do');
});

test('describeStreamProgress returns null before any known field has appeared', () => {
    assert.equal(describeStreamProgress(''), null);
    assert.equal(describeStreamProgress('{"category": "'), null);
});

test('describeStreamProgress reports fields as they appear', () => {
    assert.match(describeStreamProgress('{"category": "Áo thun"'), /đã chọn danh mục/);
});

test('send() uses the streaming endpoint when configured, applying the payload from the "done" event', async t => {
    const sse = `event: delta\ndata: ${JSON.stringify({ text: '{"category":' })}\n\n`
        + `event: done\ndata: ${JSON.stringify({ reply: 'ok', products: [], raw: '{}' })}\n\n`;
    t.mock.method(globalThis, 'fetch', async () => ({ ok: true, status: 200, body: { getReader: () => sseReaderFromText(sse) } }));
    const chat = shoppingAssistChat('/san-pham/goi-y-ai', '/san-pham/goi-y-ai/stream');
    chat.input = 'áo thun';
    await chat.send();
    assert.equal(chat.messages.length, 2);
    assert.equal(chat.messages[1].reply, 'ok');
    assert.equal(chat.streamStatus, '');
    assert.equal(chat.loading, false);
});

test('send() falls back to the JSON endpoint when the browser cannot read the stream body', async t => {
    const fetchMock = t.mock.method(globalThis, 'fetch', async (url) => (
        url === '/san-pham/goi-y-ai/stream'
            ? { ok: true, status: 200, body: {} }
            : { ok: true, json: async () => ({ reply: 'ok', products: [], raw: '{}' }) }
    ));
    const chat = shoppingAssistChat('/san-pham/goi-y-ai', '/san-pham/goi-y-ai/stream');
    chat.input = 'áo thun';
    await chat.send();
    assert.equal(fetchMock.mock.calls.length, 2);
    assert.equal(chat.messages.length, 2);
});

test('send() surfaces a stream "error" event directly, without retrying via the JSON endpoint', async t => {
    const sse = `event: error\ndata: ${JSON.stringify({ message: 'AI lỗi rồi' })}\n\n`;
    const fetchMock = t.mock.method(globalThis, 'fetch', async () => ({ ok: true, status: 200, body: { getReader: () => sseReaderFromText(sse) } }));
    const chat = shoppingAssistChat('/san-pham/goi-y-ai', '/san-pham/goi-y-ai/stream');
    chat.input = 'áo thun';
    await chat.send();
    assert.equal(chat.error, 'AI lỗi rồi');
    assert.equal(fetchMock.mock.calls.length, 1);
});

test('toggle() flips the open state', () => {
    const chat = shoppingAssistChat('/san-pham/goi-y-ai');
    chat.toggle();
    assert.equal(chat.open, true);
    chat.toggle();
    assert.equal(chat.open, false);
});
