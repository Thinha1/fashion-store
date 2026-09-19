import test from 'node:test';
import assert from 'node:assert/strict';
import productAiChat, {
    buildFillPlan, toWireMessages, parsePriceToInteger, collectImageGallery, compactMessages,
} from '../../resources/js/product-ai-chat.js';

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
    // init() now calls scrollToBottom() directly (not just via a $watch)
    // to fix the initial-restore case, so every init()-driven test needs a
    // working $nextTick even if it never touches scrolling itself.
    chat.$nextTick = (callback) => callback();
    chat.$refs = {};

    return chat;
}

const defaultDocument = { querySelector: () => null, getElementById: () => null };

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
    assert.deepEqual(plan.variantRows[0], { size: 'S', color: 'Trắng', imageIndex: undefined, price: undefined, stock: undefined });
});

test('buildFillPlan creates one row per size (empty color) when no colors were suggested', () => {
    const plan = buildFillPlan({ ...draft, sizes: ['S', 'M', 'L'], colors: [] });
    assert.deepEqual(plan.variantRows, [
        { size: 'S', color: '', imageIndex: undefined, price: undefined, stock: undefined },
        { size: 'M', color: '', imageIndex: undefined, price: undefined, stock: undefined },
        { size: 'L', color: '', imageIndex: undefined, price: undefined, stock: undefined },
    ]);
});

test('buildFillPlan applies variant_price/variant_stock uniformly to every generated row', () => {
    const plan = buildFillPlan({ ...draft, sizes: ['S', 'M'], colors: [], variant_price: '120000', variant_stock: '20' });
    assert.deepEqual(plan.variantRows, [
        { size: 'S', color: '', imageIndex: undefined, price: '120000', stock: '20' },
        { size: 'M', color: '', imageIndex: undefined, price: '120000', stock: '20' },
    ]);
});

test('buildFillPlan creates no variant rows when only colors were suggested (size is required)', () => {
    const plan = buildFillPlan({ ...draft, sizes: [], colors: ['Trắng'] });
    assert.deepEqual(plan.variantRows, []);
});

test('buildFillPlan passes category and brand through untouched (DOM matching happens later)', () => {
    const plan = buildFillPlan({ ...draft, category: ' Áo thun ', brand: 'Local Brand X' });
    assert.equal(plan.category, 'Áo thun');
    assert.equal(plan.brand, 'Local Brand X');
});

test('buildFillPlan defaults category and brand to empty when the AI left them blank', () => {
    const plan = buildFillPlan(draft);
    assert.equal(plan.category, '');
    assert.equal(plan.brand, '');
});

test('buildFillPlan attaches the matching image index to every row sharing that color', () => {
    const plan = buildFillPlan({
        ...draft, sizes: ['S', 'M'], colors: ['Trắng', 'Đen'],
        variant_images: [{ color: 'Đen', image_index: 2 }, { color: '  trắng  ', image_index: 1 }],
    });
    assert.deepEqual(plan.variantRows, [
        { size: 'S', color: 'Trắng', imageIndex: 1, price: undefined, stock: undefined },
        { size: 'S', color: 'Đen', imageIndex: 2, price: undefined, stock: undefined },
        { size: 'M', color: 'Trắng', imageIndex: 1, price: undefined, stock: undefined },
        { size: 'M', color: 'Đen', imageIndex: 2, price: undefined, stock: undefined },
    ]);
});

test('buildFillPlan ignores variant_images entries for colors that are not in the draft', () => {
    const plan = buildFillPlan({
        ...draft, sizes: ['S'], colors: ['Trắng'], variant_images: [{ color: 'Xanh lá', image_index: 3 }],
    });
    assert.deepEqual(plan.variantRows, [{ size: 'S', color: 'Trắng', imageIndex: undefined, price: undefined, stock: undefined }]);
});

test('collectImageGallery lays out every attached image by its stable index, across all messages', () => {
    const gallery = collectImageGallery([
        {
            role: 'user',
            blocks: [
                { type: 'text', text: 'Ảnh số 0:' },
                { type: 'image', dataUrl: 'data:image/jpeg;base64,AAA', imageIndex: 0 },
                { type: 'text', text: 'Ảnh số 1:' },
                { type: 'image', dataUrl: 'data:image/jpeg;base64,BBB', imageIndex: 1 },
            ],
        },
        { role: 'assistant', blocks: [{ type: 'text', text: '{}' }] },
        {
            role: 'user',
            blocks: [{ type: 'text', text: 'Ảnh số 2:' }, { type: 'image', dataUrl: 'data:image/jpeg;base64,CCC', imageIndex: 2 }],
        },
    ]);
    assert.deepEqual(gallery, ['data:image/jpeg;base64,AAA', 'data:image/jpeg;base64,BBB', 'data:image/jpeg;base64,CCC']);
});

test('parsePriceToInteger understands plain digits, grouped VND text, and "k" shorthand', () => {
    assert.equal(parsePriceToInteger('199000'), 199000);
    assert.equal(parsePriceToInteger('350.000đ'), 350000);
    assert.equal(parsePriceToInteger('350,000 VND'), 350000);
    assert.equal(parsePriceToInteger('350k'), 350000);
    assert.equal(parsePriceToInteger('12.5k'), 12500);
    assert.equal(parsePriceToInteger(''), null);
    assert.equal(parsePriceToInteger('liên hệ'), null);
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

function textTurn(role, text) {
    return { role, blocks: [{ type: 'text', text }] };
}

function imageTurn(imageIndex) {
    return { role: 'user', blocks: [{ type: 'image', mediaType: 'image/jpeg', data: 'abc', imageIndex }] };
}

test('compactMessages leaves a short conversation untouched', () => {
    const messages = Array.from({ length: 10 }, (_, i) => textTurn(i % 2 ? 'assistant' : 'user', `turn ${i}`));
    assert.equal(compactMessages(messages, draft), messages);
});

test('compactMessages collapses older text-only turns into one recap, keeping the last few verbatim', () => {
    const messages = Array.from({ length: 20 }, (_, i) => textTurn(i % 2 ? 'assistant' : 'user', `turn ${i}`));
    const compacted = compactMessages(messages, draft);
    assert.equal(compacted.length, 7); // 1 recap + last 6 kept verbatim
    assert.equal(compacted[0].isRecap, true);
    assert.match(compacted[0].blocks[0].text, /Áo sơ mi trắng/);
    assert.deepEqual(compacted.slice(1), messages.slice(-6));
});

test('compactMessages never drops or renumbers a message carrying a photo', () => {
    const messages = [
        imageTurn(0), textTurn('assistant', 'a0'),
        ...Array.from({ length: 16 }, (_, i) => textTurn(i % 2 ? 'assistant' : 'user', `turn ${i}`)),
    ];
    const compacted = compactMessages(messages, draft);
    const keptImage = compacted.find((m) => m.blocks.some((b) => b.type === 'image'));
    assert.deepEqual(keptImage, messages[0]);
    assert.equal(keptImage.blocks[0].imageIndex, 0);
});

test('compactMessages falls back to a generic note when nothing was composed yet', () => {
    const messages = Array.from({ length: 20 }, (_, i) => textTurn(i % 2 ? 'assistant' : 'user', `turn ${i}`));
    const compacted = compactMessages(messages, null);
    assert.match(compacted[0].blocks[0].text, /chưa soạn nội dung sản phẩm nào/);
});

test('compactMessages does nothing once the older portion is entirely photos (nothing to collapse)', () => {
    const messages = [imageTurn(0), imageTurn(1), ...Array.from({ length: 16 }, (_, i) => imageTurn(i + 2))];
    assert.equal(compactMessages(messages, draft), messages);
});

test('send() auto-compacts a long conversation before it can hit the server history limit', async t => {
    t.mock.method(globalThis, 'fetch', async () => ({ ok: true, json: async () => ({ data: draft, raw: '{}' }) }));
    const chat = productAiChat('/admin/san-pham/ai-goi-y');
    for (let i = 0; i < 9; i++) {
        chat.input = `lượt ${i}`;
        await chat.send();
    }
    // 9 turns is 18 raw messages uncompacted — well past MAX_MESSAGES_BEFORE_COMPACT (16),
    // so compaction must have kicked in partway through instead of growing unbounded.
    assert.equal(chat.messages.length, 8);
    assert.equal(chat.messages[0].isRecap, true);
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
    // Ties the draft card to this exact (last) message so it retires once
    // the conversation moves on to unrelated turns (see the blade's
    // `draftMessageIndex === messages.length - 1` guard).
    assert.equal(chat.draftMessageIndex, 1);
});

test('send() retires the previous draft card once a later turn composes nothing new', async t => {
    t.mock.method(globalThis, 'fetch', async () => ({ ok: true, json: async () => ({ data: draft, raw: '{}' }) }));
    const chat = productAiChat('/admin/san-pham/ai-goi-y');
    chat.input = 'áo sơ mi trắng giá 350k';
    await chat.send();
    assert.equal(chat.draftMessageIndex, 1);

    t.mock.method(globalThis, 'fetch', async () => ({
        ok: true, json: async () => ({ data: { ...draft, name: '' }, raw: '{}' }),
    }));
    chat.input = 'cảm ơn nhé';
    await chat.send();
    assert.equal(chat.draft, null);
    assert.equal(chat.draftMessageIndex, null);
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

test('send() labels each attached image with its stable index so the AI can reference it back', async t => {
    const fetch = t.mock.method(globalThis, 'fetch', async () => ({
        ok: true, json: async () => ({ data: draft, raw: '{}' }),
    }));
    const chat = productAiChat('/admin/san-pham/ai-goi-y');
    chat.attachedImages = [
        { dataUrl: 'data:image/jpeg;base64,AAA', mediaType: 'image/jpeg', data: 'AAA', name: 'a.jpg', imageIndex: 0 },
        { dataUrl: 'data:image/jpeg;base64,BBB', mediaType: 'image/jpeg', data: 'BBB', name: 'b.jpg', imageIndex: 1 },
    ];
    chat.input = 'áo có 2 màu';
    await chat.send();
    const body = JSON.parse(fetch.mock.calls[0].arguments[1].body);
    assert.deepEqual(body.messages[0].content, [
        { type: 'text', text: 'Ảnh số 0:' },
        { type: 'image', media_type: 'image/jpeg', data: 'AAA' },
        { type: 'text', text: 'Ảnh số 1:' },
        { type: 'image', media_type: 'image/jpeg', data: 'BBB' },
        { type: 'text', text: 'áo có 2 màu' },
    ]);
    assert.deepEqual(chat.attachedImages, []);
});

test('send() does not surface a draft card when the AI composed nothing (e.g. the staff member only asked to navigate)', async t => {
    const emptyDraft = { ...draft, name: '' };
    t.mock.method(globalThis, 'fetch', async () => ({ ok: true, json: async () => ({ data: emptyDraft, raw: '{}' }) }));
    const chat = productAiChat('/admin/san-pham/ai-goi-y');
    chat.input = 'đưa tôi tới danh sách sản phẩm';
    await chat.send();
    assert.equal(chat.draft, null);
});

test('send() stores the resolved navigate suggestion and navigates there immediately, no confirmation needed', async t => {
    globalThis.window = { location: { href: '' } };
    t.mock.method(globalThis, 'fetch', async () => ({
        ok: true, json: async () => ({ data: draft, raw: '{}', navigate: { key: 'products.index', label: 'Danh sách sản phẩm', url: '/admin/san-pham' } }),
    }));
    const chat = withAlpineStubs(productAiChat('/admin/san-pham/ai-goi-y'));
    chat.input = 'đưa tôi tới danh sách sản phẩm';
    await chat.send();
    assert.deepEqual(chat.navigate, { key: 'products.index', label: 'Danh sách sản phẩm', url: '/admin/san-pham' });
    assert.equal(globalThis.window.location.href, '/admin/san-pham');
    assert.equal(chat.messages.at(-1).navigateLabel, 'Danh sách sản phẩm');
    // Consumed by init() on the destination page to focus the chat box —
    // see the dedicated init() test below.
    assert.equal(chat.pendingFocus, true);
});

test('init() focuses the chat box and clears pendingFocus after an auto-navigate reload', () => {
    globalThis.sessionStorage = fakeSessionStorage();
    globalThis.sessionStorage.setItem('product-ai-chat:v1', JSON.stringify({ open: true, messages: [], pendingFocus: true }));
    const chat = withAlpineStubs(productAiChat('/admin/san-pham/ai-goi-y'));
    let focused = false;
    chat.$refs.messageInput = { focus: () => { focused = true; } };
    chat.init();
    assert.equal(focused, true);
    assert.equal(chat.pendingFocus, false);
    const saved = JSON.parse(globalThis.sessionStorage.getItem('product-ai-chat:v1'));
    assert.equal(saved.pendingFocus, false);
});

test('init() does not touch focus when there is no pending navigate', () => {
    globalThis.sessionStorage = fakeSessionStorage();
    const chat = withAlpineStubs(productAiChat('/admin/san-pham/ai-goi-y'));
    let focused = false;
    chat.$refs.messageInput = { focus: () => { focused = true; } };
    chat.init();
    assert.equal(focused, false);
});

test('send() clears a previous navigate suggestion when the next reply has none', async t => {
    t.mock.method(globalThis, 'fetch', async () => ({ ok: true, json: async () => ({ data: draft, raw: '{}', navigate: null }) }));
    const chat = productAiChat('/admin/san-pham/ai-goi-y');
    chat.navigate = { key: 'products.index', label: 'Danh sách sản phẩm', url: '/admin/san-pham' };
    chat.input = 'áo thun';
    await chat.send();
    assert.equal(chat.navigate, null);
});

test('send() applies set_fields directly to the live form without a confirmation click', async t => {
    t.mock.method(globalThis, 'fetch', async () => ({
        ok: true, json: async () => ({ data: { ...draft, name: '', set_fields: [{ field: 'price', value: '100000' }] }, raw: '{}' }),
    }));
    // A minimal stand-in for the real product form — just enough (a no-op
    // `querySelector`) for the field-writing helpers to no-op safely instead
    // of touching real DOM APIs Node doesn't have. Confirms the surrounding
    // orchestration (which form it targets, no draft card, the bubble
    // label) rather than the DOM writes themselves, which get exercised
    // live in the browser.
    const fakeForm = { querySelector: () => null };
    globalThis.document = { querySelector: () => null, getElementById: () => fakeForm };
    globalThis.window = {};
    try {
        const chat = productAiChat('/admin/san-pham/ai-goi-y');
        chat.input = 'giá 100k';
        await chat.send();
        assert.equal(chat.draft, null);
        assert.equal(chat.messages.at(-1).setFieldsLabel, 'giá');
        assert.equal(chat.error, '');
    } finally {
        globalThis.document = defaultDocument;
    }
});

test('send() applies variant_price/variant_stock straight to existing variant rows when sizes/colors are not part of the turn', async t => {
    t.mock.method(globalThis, 'fetch', async () => ({
        ok: true,
        json: async () => ({
            data: { ...draft, name: '', set_fields: [{ field: 'variant_stock', value: '20' }, { field: 'variant_price', value: '120000' }] },
            raw: '{}',
        }),
    }));
    const existingRow = { querySelector: () => null };
    const fakeForm = { querySelector: () => null, querySelectorAll: () => [existingRow] };
    globalThis.document = { querySelector: () => null, getElementById: () => fakeForm };
    globalThis.window = {};
    try {
        const chat = productAiChat('/admin/san-pham/ai-goi-y');
        chat.input = 'tồn kho 20, giá riêng 120k';
        await chat.send();
        assert.equal(chat.messages.at(-1).setFieldsLabel, 'tồn kho biến thể, giá biến thể');
        assert.equal(chat.error, '');
    } finally {
        globalThis.document = defaultDocument;
    }
});

test('send() tags the message with which tool(s) were used this turn', async t => {
    globalThis.window = {};
    t.mock.method(globalThis, 'fetch', async () => ({
        ok: true, json: async () => ({ data: draft, raw: '{}' }),
    }));
    const chat = productAiChat('/admin/san-pham/ai-goi-y');
    chat.input = 'áo sơ mi trắng giá 350k';
    await chat.send();
    assert.deepEqual(chat.messages.at(-1).tools, ['draft']);
});

test('send() jumps to the product create page and remembers set_fields when no form is on the current page', async t => {
    globalThis.sessionStorage = fakeSessionStorage();
    globalThis.window = { location: { href: '' } };
    globalThis.document = { querySelector: () => null, getElementById: () => null };
    t.mock.method(globalThis, 'fetch', async () => ({
        ok: true, json: async () => ({ data: { ...draft, name: '', set_fields: [{ field: 'price', value: '100000' }] }, raw: '{}' }),
    }));
    try {
        const chat = withAlpineStubs(productAiChat('/admin/san-pham/ai-goi-y', '/admin/san-pham/tao-moi'));
        chat.input = 'giá 100k';
        await chat.send();
        assert.deepEqual(chat.pendingSetFields, [{ field: 'price', value: '100000' }]);
        assert.equal(globalThis.window.location.href, '/admin/san-pham/tao-moi');
        const saved = JSON.parse(globalThis.sessionStorage.getItem('product-ai-chat:v1'));
        assert.deepEqual(saved.pendingSetFields, [{ field: 'price', value: '100000' }]);
    } finally {
        globalThis.document = defaultDocument;
    }
});

test('send() shows an error instead of navigating when set_fields has nowhere to go', async t => {
    globalThis.document = { querySelector: () => null, getElementById: () => null };
    t.mock.method(globalThis, 'fetch', async () => ({
        ok: true, json: async () => ({ data: { ...draft, name: '', set_fields: [{ field: 'price', value: '100000' }] }, raw: '{}' }),
    }));
    try {
        const chat = productAiChat('/admin/san-pham/ai-goi-y');
        chat.input = 'giá 100k';
        await chat.send();
        assert.match(chat.error, /Không tìm thấy form/);
    } finally {
        globalThis.document = defaultDocument;
    }
});

test('send() ignores an absent or empty set_fields (no error, no redirect)', async t => {
    globalThis.document = { querySelector: () => null, getElementById: () => null };
    t.mock.method(globalThis, 'fetch', async () => ({ ok: true, json: async () => ({ data: draft, raw: '{}' }) }));
    try {
        const chat = productAiChat('/admin/san-pham/ai-goi-y');
        chat.input = 'áo thun';
        await chat.send();
        assert.equal(chat.error, '');
        assert.equal(chat.messages.at(-1).setFieldsLabel, null);
    } finally {
        globalThis.document = defaultDocument;
    }
});

test('init() finishes a pending set_fields edit once the product-form page has loaded', () => {
    globalThis.sessionStorage = fakeSessionStorage();
    globalThis.sessionStorage.setItem('product-ai-chat:v1', JSON.stringify({
        open: false, messages: [], pendingSetFields: [{ field: 'price', value: '100000' }],
    }));
    const fakeForm = {};
    globalThis.document = { querySelector: () => null, getElementById: () => fakeForm };
    try {
        const chat = withAlpineStubs(productAiChat('/admin/san-pham/ai-goi-y', '/admin/san-pham/tao-moi'));
        let appliedWith = null;
        chat.applySetFieldsToForm = (form) => { appliedWith = form; };
        chat.init();
        assert.equal(chat.open, true);
        assert.equal(appliedWith, fakeForm);
    } finally {
        globalThis.document = defaultDocument;
    }
});

test('startNewConversation() also clears a pending set_fields edit', () => {
    globalThis.sessionStorage = fakeSessionStorage();
    const chat = withAlpineStubs(productAiChat('/admin/san-pham/ai-goi-y'));
    chat.init();
    chat.pendingSetFields = [{ field: 'price', value: '100000' }];
    chat.startNewConversation();
    assert.equal(chat.pendingSetFields, null);
});

test('goToPage() navigates to the server-resolved URL', () => {
    globalThis.window = { location: { href: '' } };
    const chat = productAiChat('/admin/san-pham/ai-goi-y');
    chat.navigate = { key: 'products.index', label: 'Danh sách sản phẩm', url: '/admin/san-pham' };
    chat.goToPage();
    assert.equal(globalThis.window.location.href, '/admin/san-pham');
});

test('goToPage() does nothing without a pending suggestion', () => {
    globalThis.window = { location: { href: '' } };
    const chat = productAiChat('/admin/san-pham/ai-goi-y');
    chat.goToPage();
    assert.equal(globalThis.window.location.href, '');
});

test('scrollToBottom() scrolls the message list to reveal the newest content', () => {
    const chat = productAiChat('/admin/san-pham/ai-goi-y');
    chat.$nextTick = (callback) => callback();
    chat.$refs = { messageList: { scrollTop: 0, scrollHeight: 480 } };
    chat.scrollToBottom();
    assert.equal(chat.$refs.messageList.scrollTop, 480);
});

test('scrollToBottom() does nothing when the message list ref is not mounted yet', () => {
    const chat = productAiChat('/admin/san-pham/ai-goi-y');
    chat.$nextTick = (callback) => callback();
    chat.$refs = {};
    assert.doesNotThrow(() => chat.scrollToBottom());
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
    const navigate = { key: 'products.index', label: 'Danh sách sản phẩm', url: '/admin/san-pham' };
    globalThis.sessionStorage.setItem('product-ai-chat:v1', JSON.stringify({
        open: true, draft, navigate, draftMessageIndex: 0,
        messages: [{ role: 'user', blocks: [{ type: 'text', text: 'áo thun' }] }],
    }));
    const chat = withAlpineStubs(productAiChat('/admin/san-pham/ai-goi-y'));
    chat.init();
    assert.equal(chat.open, true);
    assert.deepEqual(chat.draft, draft);
    assert.deepEqual(chat.navigate, navigate);
    assert.equal(chat.messages.length, 1);
    assert.equal(chat.draftMessageIndex, 0);
});

test('init() scrolls a restored conversation to the bottom (e.g. right after an auto-navigate reload)', () => {
    globalThis.sessionStorage = fakeSessionStorage();
    globalThis.sessionStorage.setItem('product-ai-chat:v1', JSON.stringify({
        open: true, messages: [{ role: 'user', blocks: [{ type: 'text', text: 'áo thun' }] }],
    }));
    const chat = withAlpineStubs(productAiChat('/admin/san-pham/ai-goi-y'));
    // $watch never fires for the direct assignment inside init() itself
    // (only for changes afterward), so without an explicit scroll call the
    // panel would render stuck at the top of a restored history.
    chat.$refs = { messageList: { scrollTop: 0, scrollHeight: 480 } };
    chat.init();
    assert.equal(chat.$refs.messageList.scrollTop, 480);
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

test('startNewConversation() clears the conversation, draft, navigate suggestion and any attached images', () => {
    globalThis.sessionStorage = fakeSessionStorage();
    const chat = withAlpineStubs(productAiChat('/admin/san-pham/ai-goi-y'));
    chat.init();
    chat.messages.push({ role: 'user', blocks: [{ type: 'text', text: 'áo thun' }] });
    chat.draft = draft;
    chat.navigate = { key: 'products.index', label: 'Danh sách sản phẩm', url: '/admin/san-pham' };
    chat.attachedImages = [{ dataUrl: 'data:image/jpeg;base64,abc', mediaType: 'image/jpeg', data: 'abc', name: 'a.jpg', imageIndex: 0 }];
    chat.imageCounter = 3;
    chat.draftMessageIndex = 0;
    chat.startNewConversation();
    assert.deepEqual(chat.messages, []);
    assert.equal(chat.draft, null);
    assert.equal(chat.navigate, null);
    assert.deepEqual(chat.attachedImages, []);
    assert.equal(chat.imageCounter, 0);
    assert.equal(chat.draftMessageIndex, null);
});

test('fillForm() jumps to the product create page when no form is on the current page', () => {
    globalThis.sessionStorage = fakeSessionStorage();
    globalThis.window = { location: { href: '' } };
    globalThis.document = { querySelector: () => null, getElementById: () => null };
    try {
        const chat = withAlpineStubs(productAiChat('/admin/san-pham/ai-goi-y', '/admin/san-pham/tao-moi'));
        chat.init();
        chat.draft = draft;
        chat.fillForm();
        assert.equal(chat.pendingFill, true);
        assert.equal(globalThis.window.location.href, '/admin/san-pham/tao-moi');
        const saved = JSON.parse(globalThis.sessionStorage.getItem('product-ai-chat:v1'));
        assert.equal(saved.pendingFill, true);
    } finally {
        globalThis.document = defaultDocument;
    }
});

test('fillForm() shows an error instead of navigating when no create-page URL was configured', () => {
    globalThis.document = { querySelector: () => null, getElementById: () => null };
    try {
        const chat = productAiChat('/admin/san-pham/ai-goi-y');
        chat.draft = draft;
        chat.fillForm();
        assert.match(chat.error, /Không tìm thấy form/);
    } finally {
        globalThis.document = defaultDocument;
    }
});

test('init() finishes a pending fill once the product-form page has loaded', () => {
    globalThis.sessionStorage = fakeSessionStorage();
    globalThis.sessionStorage.setItem('product-ai-chat:v1', JSON.stringify({
        open: false, draft, messages: [], pendingFill: true,
    }));
    const fakeForm = {};
    globalThis.document = { querySelector: () => null, getElementById: () => fakeForm };
    try {
        const chat = withAlpineStubs(productAiChat('/admin/san-pham/ai-goi-y', '/admin/san-pham/tao-moi'));
        let filledWith = null;
        chat.applyDraftToForm = (form) => { filledWith = form; };
        chat.init();
        assert.equal(chat.open, true);
        assert.equal(filledWith, fakeForm);
    } finally {
        globalThis.document = defaultDocument;
    }
});

test('init() leaves the fill pending when the destination page still has no form', () => {
    globalThis.sessionStorage = fakeSessionStorage();
    globalThis.sessionStorage.setItem('product-ai-chat:v1', JSON.stringify({
        open: false, draft, messages: [], pendingFill: true,
    }));
    globalThis.document = { querySelector: () => null, getElementById: () => null };
    try {
        const chat = withAlpineStubs(productAiChat('/admin/san-pham/ai-goi-y', '/admin/san-pham/tao-moi'));
        chat.init();
        assert.equal(chat.open, false);
        assert.equal(chat.pendingFill, true);
    } finally {
        globalThis.document = defaultDocument;
    }
});
