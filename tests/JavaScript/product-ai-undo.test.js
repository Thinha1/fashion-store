import test from 'node:test';
import assert from 'node:assert/strict';
import productAiChat from '../../resources/js/product-ai-chat.js';

globalThis.document ??= { querySelector: () => null, getElementById: () => null };

// A product form with none of the fields: every field lookup comes back empty and every write
// is skipped, so these tests exercise only the replace-instead-of-append and undo bookkeeping.
const emptyForm = { querySelector: () => null };

function setup() {
    globalThis.window = { Alpine: {} };
    const chat = productAiChat('/assist', '/create');
    chat.draft = { name: 'Áo A', description: '', bullets: [], seo_title: '', price: '100000', sizes: [], colors: [], category: '', brand: '', variant_images: [] };

    return chat;
}

test('a second fill first undoes the previous one, so rows are replaced rather than added to', () => {
    const chat = setup();
    let firstUndone = 0;
    chat.lastDraftFillRestore = () => { firstUndone++; };

    chat.applyDraftToForm(emptyForm);

    assert.equal(firstUndone, 1);
    assert.equal(typeof chat.lastDraftFillRestore, 'function');
});

test('the first fill has nothing to undo and registers its own undo', () => {
    const chat = setup();

    chat.applyDraftToForm(emptyForm);

    assert.equal(typeof chat.lastDraftFillRestore, 'function');
    assert.equal(chat.lastFormChange.restore, chat.lastDraftFillRestore);
});

test('filling twice in a row only ever keeps the latest undo', () => {
    const chat = setup();
    chat.applyDraftToForm(emptyForm);
    const first = chat.lastDraftFillRestore;

    chat.applyDraftToForm(emptyForm);

    assert.notEqual(chat.lastDraftFillRestore, first);
    assert.equal(chat.lastFormChange.restore, chat.lastDraftFillRestore);
});

test('undoing a fill runs its restore once and a later fill does not undo it a second time', () => {
    const chat = setup();
    let restored = 0;
    const restore = () => { restored++; };
    chat.lastDraftFillRestore = restore;
    chat.lastFormChange = { restore };

    chat.undoLastFormChange();
    chat.applyDraftToForm(emptyForm);

    assert.equal(restored, 1);
    assert.equal(chat.lastFormChange.restore === restore, false);
});

test('undoing a standalone edit leaves the draft-fill undo alone', () => {
    const chat = setup();
    const fill = () => {};
    chat.lastDraftFillRestore = fill;
    chat.lastFormChange = { restore: () => {} };

    chat.undoLastFormChange();

    assert.equal(chat.lastDraftFillRestore, fill);
    assert.equal(chat.lastFormChange, null);
});

test('canUndoDraftFill() is true only while the latest form change is a draft fill', () => {
    const chat = setup();
    assert.equal(chat.canUndoDraftFill(), false, 'nothing filled yet');

    chat.applyDraftToForm(emptyForm);
    assert.equal(chat.canUndoDraftFill(), true, 'right after a fill the card offers undo');

    chat.lastFormChange = { restore: () => {} };
    assert.equal(chat.canUndoDraftFill(), false, 'a later standalone edit took over the single undo slot');

    chat.applyDraftToForm(emptyForm);
    chat.undoLastFormChange();
    assert.equal(chat.canUndoDraftFill(), false, 'once undone there is nothing left to undo');
});

test('starting a new conversation forgets the draft-fill undo', () => {
    const chat = setup();
    chat.lastDraftFillRestore = () => {};

    chat.startNewConversation();

    assert.equal(chat.lastDraftFillRestore, null);
    assert.equal(chat.canUndoDraftFill(), false);
});
