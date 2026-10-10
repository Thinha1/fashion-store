import test from 'node:test';
import assert from 'node:assert/strict';
import paymentStatusPoller from '../../resources/js/payment-status-poller.js';

function stubBrowser({ hidden = false, responses = [] } = {}) {
    const calls = [];
    let reloads = 0;
    globalThis.document = { hidden };
    globalThis.window = { location: { reload: () => { reloads++; } } };
    globalThis.fetch = async (url) => {
        calls.push(url);
        const next = responses.shift();
        if (next instanceof Error) throw next;
        return { ok: next?.ok ?? true, json: async () => next?.body ?? {} };
    };
    return { calls, reloads: () => reloads };
}

test('reloads the page once the payment status changes', async () => {
    const browser = stubBrowser({ responses: [
        { body: { payment_status: 'unpaid' } },
        { body: { payment_status: 'paid' } },
    ] });
    const poller = paymentStatusPoller({ url: '/don-hang/DH1/trang-thai-thanh-toan', current: 'unpaid' });

    await poller.check();
    assert.equal(browser.reloads(), 0);
    await poller.check();
    assert.equal(browser.reloads(), 1);
    assert.deepEqual(browser.calls, ['/don-hang/DH1/trang-thai-thanh-toan', '/don-hang/DH1/trang-thai-thanh-toan']);
});

test('skips polling while the tab is hidden', async () => {
    const browser = stubBrowser({ hidden: true });
    const poller = paymentStatusPoller({ url: '/x', current: 'unpaid' });

    await poller.check();
    assert.equal(browser.calls.length, 0);
});

test('a failed request or network error is retried later, not treated as a change', async () => {
    const browser = stubBrowser({ responses: [{ ok: false }, new Error('offline')] });
    const poller = paymentStatusPoller({ url: '/x', current: 'unpaid' });

    await poller.check();
    await poller.check();
    assert.equal(browser.reloads(), 0);
});

test('stops asking after maxChecks polls', async () => {
    const browser = stubBrowser({ responses: [{ body: { payment_status: 'unpaid' } }] });
    const poller = paymentStatusPoller({ url: '/x', current: 'unpaid', maxChecks: 1 });

    await poller.check();
    await poller.check();
    assert.equal(browser.calls.length, 1);
});
