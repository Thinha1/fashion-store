import test from 'node:test';
import assert from 'node:assert/strict';
import productAiChat, { buildFillPlan, toWireMessages } from '../../resources/js/product-ai-chat.js';

// send()/fillForm() read `document` for the CSRF token / the product form,
// which doesn't exist under Node's test runner. A minimal stub is enough to
// exercise the network/state logic without needing a real DOM.
globalThis.document ??= { querySelector: () => null, getElementById: () => null };

// init() reads/writes sessionStorage (unavailable under Node) and calls
// Alpine's injected `this.$watch`. A tiny in-memory stub of both is enough
// to exercise the persistence logic without a real browser or Alpine.
function fakeSessionStorage() {
    const store = new Map();

    return {
        getItem: (key) => (store.has(key) ? store.get(key) : null),
        setItem: (key, value) => store.set(key, value),
    };
}

function withAlpineStubs(chat) {
    chat.$watch = () => {};

    return chat;
}

const draft = {
    name: 'Áo sơ mi trắng', description: 'Chất liệu thoáng mát.',
    bullets: ['Form rộng', 'Vải cotton'], seo_title: 'Áo sơ mi trắng nam',
    price: '350000', sizes: ['S', 'M'], colors: ['Trắng', 'Đen'],
};

test('buildFillPlan folds bullets and SEO title into the description', () => {
    const plan = buildFillPlan(draft);
    assert.match(plan.description, /Chất liệu thoáng mát\./);
    assert.match(plan.description, /• Form rộng/);
    assert.match(plan.description, /SEO: Áo sơ mi trắng nam/);
});

test('buildFillPlan builds the cartesian product of sizes and colors, capped at 12 rows', () => {
    const plan = buildFillPlan({ ...draft, sizes: ['S', 'M', 'L'], colors: ['Trắng', 'Đen', 'Xanh', 'Vàng', 'Hồng'] });
    assert.equal(plan.variantRows.length, 12);
    assert.deepEqual(plan.variantRows[0], { size: 'S', color: 'Trắng' });
});

test('buildFillPlan creates one row per size (empty color) when no colors were suggested', () => {
    const plan = buildFillPlan({ ...draft, sizes: ['S', 'M', 'L'], colors: [] });
    assert.deepEqual(plan.variantRows, [{ size: 'S', color: '' }, { size: 'M', color: '' }, { size: 'L', color: '' }]);
});

test('buildFillPlan creates no variant rows when only colors were suggested (size is required)', () => {
    const plan = buildFillPlan({ ...draft, sizes: [], colors: ['Trắng'] });
    assert.deepEqual(plan.variantRows, []);
});

test('toWireMessages merges consecutive same-role turns to keep strict alternation', () => {
    const wire = toWireMessages([
        { role: 'user', blocks: [{ type: 'image', mediaType: 'image/jpeg', data: 'abc' }] },
        { role: 'user', blocks: [{ type: 'text', text: 'áo sơ mi giá 350k' }] },
        { role: 'assistant', blocks: [{ type: 'text', text: '{"name":"Áo"}' }] },
        { role: 'user', blocks: [{ type: 'text', text: 'đổi tên cho sang trọng hơn' }] },
    ]);
    assert.equal(wire.length, 3);
    assert.equal(wire[0].role, 'user');
    assert.deepEqual(wire[0].content, [
        { type: 'image', media_type: 'image/jpeg', data: 'abc' },
        { type: 'text', text: 'áo sơ mi giá 350k' },
    ]);
    assert.equal(wire[1].role, 'assistant');
    assert.equal(wire[2].role, 'user');
});

test('send() resends the full conversation history and stores the parsed draft', async t => {
    const fetch = t.mock.method(globalThis, 'fetch', async () => ({
        ok: true, json: async () => ({ data: draft, raw: JSON.stringify(draft) }),
    }));
    const chat = productAiChat('/admin/san-pham/ai-goi-y');
    chat.input = 'áo sơ mi trắng giá 350k';
    await chat.send();
    assert.equal(fetch.mock.calls[0].arguments[0], '/admin/san-pham/ai-goi-y');
    const body = JSON.parse(fetch.mock.calls[0].arguments[1].body);
    assert.equal(body.messages.length, 1);
    assert.deepEqual(chat.draft, draft);
    assert.equal(chat.messages.length, 2);
    assert.equal(chat.loading, false);
    assert.equal(chat.error, '');
});

test('send() surfaces a friendly message on rate limiting and keeps the failed turn for retry', async t => {
    t.mock.method(globalThis, 'fetch', async () => ({ ok: false, status: 429, json: async () => ({}) }));
    const chat = productAiChat('/admin/san-pham/ai-goi-y');
    chat.input = 'áo thun';
    await chat.send();
    assert.match(chat.error, /quá nhanh/);
    assert.equal(chat.messages.length, 1);
    assert.equal(chat.messages[0].role, 'user');
});

test('send() ignores a call made while a request is already in flight', async t => {
    const fetch = t.mock.method(globalThis, 'fetch', () => new Promise(() => {}));
    const chat = productAiChat('/admin/san-pham/ai-goi-y');
    chat.input = 'áo thun';
    chat.send();
    assert.equal(chat.loading, true);
    chat.input = 'quần jean';
    await chat.send();
    assert.equal(fetch.mock.calls.length, 1);
    assert.equal(chat.messages.length, 1);
});

test('send() does nothing without text or an attached image', async t => {
    const fetch = t.mock.method(globalThis, 'fetch', async () => ({ ok: true, json: async () => ({ data: draft }) }));
    const chat = productAiChat('/admin/san-pham/ai-goi-y');
    await chat.send();
    assert.equal(fetch.mock.calls.length, 0);
    assert.equal(chat.messages.length, 0);
});

test('init() restores a conversation saved before navigating to another admin page', () => {
    globalThis.sessionStorage = fakeSessionStorage();
    globalThis.sessionStorage.setItem('product-ai-chat:v1', JSON.stringify({
        open: true, draft, messages: [{ role: 'user', blocks: [{ type: 'text', text: 'áo thun' }] }],
    }));
    const chat = withAlpineStubs(productAiChat('/admin/san-pham/ai-goi-y'));
    chat.init();
    assert.equal(chat.open, true);
    assert.deepEqual(chat.draft, draft);
    assert.equal(chat.messages.length, 1);
});

test('init() starts empty when nothing was previously saved', () => {
    globalThis.sessionStorage = fakeSessionStorage();
    const chat = withAlpineStubs(productAiChat('/admin/san-pham/ai-goi-y'));
    chat.init();
    assert.equal(chat.open, false);
    assert.equal(chat.draft, null);
    assert.deepEqual(chat.messages, []);
});

test('persist() writes the current conversation so it survives the next page load', () => {
    globalThis.sessionStorage = fakeSessionStorage();
    const chat = withAlpineStubs(productAiChat('/admin/san-pham/ai-goi-y'));
    chat.init();
    chat.messages.push({ role: 'user', blocks: [{ type: 'text', text: 'áo thun' }] });
    chat.draft = draft;
    chat.persist();
    const saved = JSON.parse(globalThis.sessionStorage.getItem('product-ai-chat:v1'));
    assert.equal(saved.messages.length, 1);
    assert.deepEqual(saved.draft, draft);
});

test('startNewConversation() clears the conversation, draft and any attached image', () => {
    globalThis.sessionStorage = fakeSessionStorage();
    const chat = withAlpineStubs(productAiChat('/admin/san-pham/ai-goi-y'));
    chat.init();
    chat.messages.push({ role: 'user', blocks: [{ type: 'text', text: 'áo thun' }] });
    chat.draft = draft;
    chat.attachedImage = { dataUrl: 'data:image/jpeg;base64,abc', mediaType: 'image/jpeg', data: 'abc', name: 'a.jpg' };
    chat.startNewConversation();
    assert.deepEqual(chat.messages, []);
    assert.equal(chat.draft, null);
    assert.equal(chat.attachedImage, null);
});
