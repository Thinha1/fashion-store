import test from 'node:test';
import assert from 'node:assert/strict';
import productAiChat, {
    isPdfFile, splitIntoChunks, buildDocumentBlocks, describeRequestFailure, toWireMessages,
} from '../../resources/js/product-ai-chat.js';

// attachPdf()/send() read the CSRF token and sessionStorage, which Node's test runner lacks.
globalThis.document ??= { querySelector: () => null, getElementById: () => null };

function fakeSessionStorage() {
    const store = new Map();

    return {
        getItem: (key) => (store.has(key) ? store.get(key) : null),
        setItem: (key, value) => store.set(key, String(value)),
        removeItem: (key) => store.delete(key),
    };
}

const doc = { name: 'catalog.pdf', pages: 3, text: 'Áo thun cổ tròn\nGiá: 199.000đ\nSize: S, M, L', truncated: false };

function pdfResponse(data) {
    return { ok: true, json: async () => ({ data }) };
}

test('isPdfFile() accepts .pdf by extension or MIME type and nothing else', () => {
    assert.equal(isPdfFile({ name: 'Catalog.PDF', type: '' }), true);
    assert.equal(isPdfFile({ name: 'x', type: 'application/pdf' }), true);
    assert.equal(isPdfFile({ name: 'anh.png', type: 'image/png' }), false);
    assert.equal(isPdfFile(undefined), false);
});

test('describeRequestFailure() prefers a validation message, then the server message, then the fallback', () => {
    assert.equal(describeRequestFailure({ status: 429 }, null, 'x'), 'Bạn thao tác quá nhanh. Vui lòng thử lại sau một phút.');
    assert.equal(describeRequestFailure({ status: 422 }, { message: 'm', errors: { file: ['PDF này không có chữ để đọc.'] } }, 'x'), 'PDF này không có chữ để đọc.');
    assert.equal(describeRequestFailure({ status: 500 }, null, 'Dự phòng'), 'Dự phòng');
});

test('splitIntoChunks() keeps every piece within the limit, breaks at line ends, and loses nothing', () => {
    const text = Array.from({ length: 40 }, (_, i) => `Dòng số ${i} có nội dung khá dài để chiếm chỗ`).join('\n');
    const chunks = splitIntoChunks(text, 300);

    assert.ok(chunks.length > 1);
    assert.ok(chunks.every((chunk) => chunk.length <= 300));
    assert.equal(chunks.join('\n').replace(/\s+/g, ' '), text.replace(/\s+/g, ' '));
    assert.ok(chunks.slice(0, -1).every((chunk) => /\d$/.test(chunk) || chunk.endsWith('chỗ')), 'cuts fall on line ends');
    assert.deepEqual(splitIntoChunks('   '), []);
});

test('splitIntoChunks() still cuts a single huge line', () => {
    const chunks = splitIntoChunks('x'.repeat(1000), 400);

    assert.deepEqual(chunks.map((chunk) => chunk.length), [400, 400, 200]);
});

test('buildDocumentBlocks() tags every block with its file and stays under the server limit', () => {
    const long = { ...doc, text: 'Sản phẩm mô tả dài. '.repeat(900), truncated: true };
    const blocks = buildDocumentBlocks(long);

    assert.ok(blocks.length > 1);
    assert.ok(blocks.every((block) => block.type === 'text' && block.document === 'catalog.pdf' && block.pages === 3));
    assert.ok(blocks.every((block) => block.text.length <= 4000), 'the server rejects any text block over 4000 characters');
    assert.match(blocks[0].text, /^Nội dung trích từ file PDF "catalog.pdf" \(3 trang, đã cắt bớt vì quá dài\):/);
    assert.match(blocks[1].text, /^\(tiếp nội dung file PDF "catalog.pdf"\)/);
});

test('a very long file name cannot push a block over the limit', () => {
    const blocks = buildDocumentBlocks({ ...doc, name: `${'a'.repeat(250)}.pdf`, text: 'y'.repeat(3800) });

    assert.ok(blocks.every((block) => block.text.length <= 4000));
});

test('attachPdf() uploads the file and holds its text as an attachment', async (t) => {
    globalThis.window = {};
    const fetchMock = t.mock.method(globalThis, 'fetch', async () => pdfResponse({ text: doc.text, pages: 3, truncated: false }));
    const chat = productAiChat('/assist', '/create', undefined, '/pdf');

    await chat.attachPdf({ name: 'catalog.pdf' });

    const [url, options] = fetchMock.mock.calls[0].arguments;
    assert.equal(url, '/pdf');
    assert.equal(options.method, 'POST');
    assert.ok(options.body instanceof FormData);
    assert.deepEqual(chat.attachedDocuments, [{ name: 'catalog.pdf', pages: 3, text: doc.text, truncated: false }]);
    assert.equal(chat.loading, false);
    assert.equal(chat.error, '');
});

test('attachPdf() reports why a file could not be read and attaches nothing', async (t) => {
    globalThis.window = {};
    t.mock.method(globalThis, 'fetch', async () => ({ ok: false, status: 422, json: async () => ({ errors: { file: ['PDF này không có chữ để đọc (có thể là bản scan).'] } }) }));
    const chat = productAiChat('/assist', '/create', undefined, '/pdf');

    await chat.attachPdf({ name: 'scan.pdf' });

    assert.equal(chat.error, 'PDF này không có chữ để đọc (có thể là bản scan).');
    assert.deepEqual(chat.attachedDocuments, []);
    assert.equal(chat.loading, false);
});

test('attachPdf() allows at most two PDFs per message and needs an endpoint', async (t) => {
    globalThis.window = {};
    t.mock.method(globalThis, 'fetch', async () => pdfResponse({ text: 'đủ dài để hợp lệ', pages: 1, truncated: false }));
    const chat = productAiChat('/assist', '/create', undefined, '/pdf');
    await chat.attachPdf({ name: 'a.pdf' });
    await chat.attachPdf({ name: 'b.pdf' });
    await chat.attachPdf({ name: 'c.pdf' });

    assert.equal(chat.attachedDocuments.length, 2);
    assert.match(chat.error, /tối đa 2/);

    const noEndpoint = productAiChat('/assist', '/create');
    await noEndpoint.attachPdf({ name: 'a.pdf' });
    assert.match(noEndpoint.error, /chưa hỗ trợ/);
});

test('send() carries the PDF text to the model but shows only a chip in the bubble', async (t) => {
    globalThis.sessionStorage = fakeSessionStorage();
    globalThis.window = {};
    const fetchMock = t.mock.method(globalThis, 'fetch', async () => ({ ok: true, json: async () => ({ data: { name: '' }, raw: '{}' }) }));
    const chat = productAiChat('/assist', '/create', undefined, '/pdf');
    chat.attachedDocuments = [doc];
    chat.input = 'lấy sản phẩm đầu tiên';

    await chat.send();

    const sent = JSON.parse(fetchMock.mock.calls[0].arguments[1].body);
    const texts = sent.messages[0].content.map((block) => block.text);
    assert.match(texts[0], /^Nội dung trích từ file PDF "catalog.pdf" \(3 trang\):\nÁo thun cổ tròn/);
    assert.equal(texts.at(-1), 'lấy sản phẩm đầu tiên');
    assert.equal(sent.messages[0].content.every((block) => !('document' in block)), true, 'the UI-only marker never reaches the server');

    const userMessage = chat.messages[0];
    assert.deepEqual(chat.documentsOf(userMessage), [{ name: 'catalog.pdf', label: 'catalog.pdf · 3 trang' }]);
    assert.deepEqual(userMessage.blocks.filter((b) => b.type === 'text' && !b.document).map((b) => b.text), ['lấy sản phẩm đầu tiên']);
    assert.deepEqual(chat.attachedDocuments, []);
});

test('send() works with a PDF alone and supplies a default request', async (t) => {
    globalThis.sessionStorage = fakeSessionStorage();
    globalThis.window = {};
    t.mock.method(globalThis, 'fetch', async () => ({ ok: true, json: async () => ({ data: { name: '' }, raw: '{}' }) }));
    const chat = productAiChat('/assist', '/create', undefined, '/pdf');
    chat.attachedDocuments = [doc];

    await chat.send();

    assert.equal(chat.messages.length >= 1, true);
    const wire = toWireMessages(chat.messages.slice(0, 1));
    assert.equal(wire[0].content.at(-1).text, 'Hãy soạn nội dung đăng sản phẩm từ file PDF đính kèm.');
});

test('send() does nothing when there is no text, image or PDF', async () => {
    const chat = productAiChat('/assist', '/create', undefined, '/pdf');

    await chat.send();

    assert.deepEqual(chat.messages, []);
});

test('starting a new conversation drops pending PDFs', () => {
    const chat = productAiChat('/assist', '/create', undefined, '/pdf');
    chat.attachedDocuments = [doc];

    chat.startNewConversation();

    assert.deepEqual(chat.attachedDocuments, []);
});
