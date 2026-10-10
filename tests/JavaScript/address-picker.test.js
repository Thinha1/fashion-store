import test from 'node:test';
import assert from 'node:assert/strict';
import addressPicker from '../../resources/js/address-picker.js';

const provinces = [{ code: 79, name: 'Thành phố Hồ Chí Minh' }, { code: 1, name: 'Thành phố Hà Nội' }];
const wardsUrl = '/tai-khoan/dia-chi/phuong-xa/__CODE__';

function stubFetch(responses) {
    const calls = [];
    globalThis.fetch = async (url) => {
        calls.push(url);
        const next = typeof responses === 'function' ? await responses(url) : responses.shift();
        if (next instanceof Error) throw next;
        return { ok: next?.ok ?? true, status: next?.status ?? 200, json: async () => next?.body ?? {} };
    };
    return calls;
}

function mount(options) {
    const picker = addressPicker({ wardsUrl, provinces, ...options });
    const watchers = {};
    picker.$watch = (key, callback) => { watchers[key] = callback; };
    picker.init();
    return { picker, change: (key, value) => { picker[key] = value; watchers[key]?.(); } };
}

test('loads the wards of the saved province and keeps the saved ward', async () => {
    const calls = stubFetch(() => ({ body: { data: [{ code: 26734, name: 'Phường Bến Thành' }] } }));
    const { picker } = mount({ province: 'Thành phố Hồ Chí Minh', ward: 'Phường Bến Thành' });
    await picker.loadWards();

    assert.deepEqual(calls.at(-1), '/tai-khoan/dia-chi/phuong-xa/79');
    assert.deepEqual(picker.wards, ['Phường Bến Thành']);
    assert.equal(picker.ward, 'Phường Bến Thành');
    assert.equal(picker.status, 'ready');
});

test('an old province name that is no longer in the list is cleared', () => {
    stubFetch([]);
    const { picker } = mount({ province: 'TP. Hồ Chí Minh', ward: 'Phường Bến Nghé' });

    assert.equal(picker.province, '');
    assert.equal(picker.status, 'idle');
});

test('changing the province clears the ward and loads the new list', async () => {
    const calls = stubFetch(() => ({ body: { data: [{ code: 4, name: 'Phường Ba Đình' }] } }));
    const { picker, change } = mount({ province: 'Thành phố Hồ Chí Minh', ward: 'Phường Bến Thành' });
    change('province', 'Thành phố Hà Nội');
    await picker.loadWards();

    assert.equal(picker.ward, '');
    assert.equal(calls.at(-1), '/tai-khoan/dia-chi/phuong-xa/1');
    assert.deepEqual(picker.wards, ['Phường Ba Đình']);
});

test('a failed load leaves the ward as free text', async () => {
    stubFetch(() => ({ ok: false, status: 503 }));
    const { picker } = mount({ province: 'Thành phố Hà Nội', ward: 'Phường Ba Đình' });
    await picker.loadWards();

    assert.equal(picker.status, 'failed');
    assert.deepEqual(picker.wards, []);
    assert.equal(picker.ward, 'Phường Ba Đình');
});

test('a slow answer for a previous province is ignored', async () => {
    let releaseFirst;
    stubFetch((url) => url.endsWith('/79')
        ? new Promise((resolve) => { releaseFirst = () => resolve({ body: { data: [{ code: 1, name: 'Phường Cũ' }] } }); })
        : { body: { data: [{ code: 2, name: 'Phường Mới' }] } });
    const { picker, change } = mount({ province: 'Thành phố Hồ Chí Minh' });
    change('province', 'Thành phố Hà Nội');
    await picker.loadWards();
    releaseFirst();
    await new Promise((resolve) => setTimeout(resolve, 0));

    assert.deepEqual(picker.wards, ['Phường Mới']);
});
