// Exercises the real Alpine mask and submitted form values without saving demo records.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const puppeteer = require(process.env.PUPPETEER_MODULE || 'puppeteer');
const base = process.env.ADMIN_TEST_URL || 'http://localhost:8080';

(async () => {
    const browser = await puppeteer.launch({
        executablePath: process.env.CHROMIUM_PATH,
        headless: process.env.ADMIN_TEST_HEADLESS !== 'false',
        args: ['--no-sandbox', '--disable-dev-shm-usage'],
    });
    try {
        fs.mkdirSync('.admin-qa', { recursive: true });
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', error => {
            errors.push(error.message);
            console.error(error.stack);
        });
        const checkBrowserErrors = () => assert.deepEqual(errors, [], 'Browser errors');
        if (process.env.ADMIN_TEST_IMAGE_ORIGIN) {
            await page.setRequestInterception(true);
            page.on('request', request => request.continue({ url: request.url().replace('http://localhost:9000', process.env.ADMIN_TEST_IMAGE_ORIGIN) }));
        }
        const visit = async url => {
            const response = await page.goto(url.startsWith('http') ? url : base + url, { waitUntil: 'networkidle0' });
            assert.equal(response.status(), 200, url);
            checkBrowserErrors();
            return response;
        };
        const values = name => page.$eval('.admin-form', (form, field) => new FormData(form).getAll(field), name);
        const discountUnit = () => page.$eval('#discount_value', input => input.closest('.currency-input').querySelector('span').textContent);
        async function checkCurrencySpacing() {
            checkBrowserErrors();
            const fields = await page.$$eval('.currency-input input[type="text"]', inputs => inputs.map(input => ({
                id: input.id,
                padding: Number.parseFloat(getComputedStyle(input).paddingRight),
                requiredSpace: input.closest('.currency-input').querySelector('span').getBoundingClientRect().width + 12,
            })));
            for (const field of fields) {
                assert.ok(field.padding > field.requiredSpace, field.id + ': currency suffix overlaps text');
            }
        }
        async function clear(selector) {
            await page.focus(selector);
            await page.keyboard.down('Control');
            await page.keyboard.press('A');
            await page.keyboard.up('Control');
            await page.keyboard.press('Backspace');
        }
        async function enter(selector, text, expected, name, raw) {
            await clear(selector);
            await page.type(selector, text);
            await page.waitForFunction((field, value) => document.querySelector(field).value === value, {}, selector, expected);
            assert.deepEqual(await values(name), [raw], name + ': wrong submitted value');
            checkBrowserErrors();
        }
        await page.setViewport({ width: 1440, height: 1000 });
        await visit('/dang-nhap');
        await page.type('[name=email]', process.env.ADMIN_TEST_EMAIL || 'admin@example.com');
        await page.type('[name=password]', process.env.ADMIN_TEST_PASSWORD || 'password');
        await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('button[type=submit]')]);

        await visit('/admin/san-pham');
        const editUrl = await page.$eval('a.admin-row-action[href$="/sua"]', link => link.href);
        const edit = await visit(editUrl);
        const original = await page.evaluate(html => new DOMParser().parseFromString(html, 'text/html').querySelector('#base_price').getAttribute('value'), await edit.text());
        assert.equal(Number((await values('base_price'))[0]), Number(original), 'Opening edit changed the saved price');
        assert.equal(await page.$eval('#base_price', input => input.value), Number(original).toLocaleString('vi-VN', { maximumFractionDigits: 2 }));

        await visit('/admin/san-pham/tao-moi');
        await clear('#base_price');
        await page.type('#base_price', '123');
        assert.equal(await page.$eval('#base_price', input => input.value), '123');
        await page.type('#base_price', '4');
        assert.equal(await page.$eval('#base_price', input => input.value), '1.234');
        await page.type('#base_price', '567');
        assert.equal(await page.$eval('#base_price', input => input.value), '1.234.567');
        assert.deepEqual(await values('base_price'), ['1234567']);
        await page.keyboard.press('Backspace');
        await page.focus('#name');
        assert.equal(await page.$eval('#base_price', input => input.value), '123.456');
        assert.deepEqual(await values('base_price'), ['123456']);
        await page.focus('#base_price');
        await page.$eval('#base_price', input => input.setSelectionRange(0, 0));
        await page.keyboard.type('9');
        assert.equal(await page.$eval('#base_price', input => input.value), '9.123.456');
        assert.ok(await page.$eval('#base_price', input => input.selectionStart < input.value.length), 'Caret jumped to the end');
        await enter('#base_price', '1234567,89', '1.234.567,89', 'base_price', '1234567.89');
        await enter('#base_price', '1234,56', '1.234,56', 'base_price', '1234.56');
        // sendCharacter follows the browser's paste-like input path, not just key presses.
        await clear('#base_price');
        await page.keyboard.sendCharacter('1.234,56');
        assert.equal(await page.$eval('#base_price', input => input.value), '1.234,56');
        assert.deepEqual(await values('base_price'), ['1234.56']);
        await clear('#base_price');
        await page.keyboard.sendCharacter('abc1.234,56xyz');
        assert.equal(await page.$eval('#base_price', input => input.value), '1.234,56');
        assert.deepEqual(await values('base_price'), ['1234.56'], 'Mask did not discard nonnumeric characters');
        await clear('#base_price');
        await page.keyboard.sendCharacter('2.500.000');
        assert.equal(await page.$eval('#base_price', input => input.value), '2.500.000');
        assert.deepEqual(await values('base_price'), ['2500000']);
        await enter('#base_price', '0', '0', 'base_price', '0');
        await clear('#base_price');
        assert.equal(await page.$eval('#base_price', input => input.checkValidity()), false);
        await enter('#base_price', '1234567,89', '1.234.567,89', 'base_price', '1234567.89');

        await page.click('#add-variant-row');
        await page.click('#add-variant-row');
        await enter('#variant-new-0-price', '250000', '250.000', 'variants[new-0][price]', '250000');
        await enter('#variant-new-1-price', '399000,5', '399.000,5', 'variants[new-1][price]', '399000.5');
        await clear('#variant-new-1-price');
        assert.deepEqual(await values('variants[new-1][price]'), ['']);
        assert.equal(await page.$eval('#variant-new-1-price', input => input.checkValidity()), true);
        await checkCurrencySpacing();
        // Missing product name/category/SKUs guarantees no product can be saved.
        await page.$eval('.admin-form', form => { form.noValidate = true; });
        await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('.admin-form-actions button[type=submit]')]);
        assert.ok(await page.$('.admin-form-errors'));
        assert.equal(await page.$eval('#base_price', input => input.value), '1.234.567,89');
        assert.deepEqual(await values('base_price'), ['1234567.89']);
        assert.equal(await page.$eval('#variant-new-0-price', input => input.value), '250.000');

        await visit('/admin/nhap-hang/tao-moi');
        await page.click('#add-item-row');
        await page.click('#add-item-row');
        await clear('#item-0-cost_price');
        assert.equal(await page.$eval('#item-0-cost_price', input => input.validity.valueMissing), true);
        assert.deepEqual(await values('items[0][cost_price]'), ['']);
        await enter('#item-0-cost_price', '1000000', '1.000.000', 'items[0][cost_price]', '1000000');
        await enter('#item-1-cost_price', '250000,25', '250.000,25', 'items[1][cost_price]', '250000.25');
        await page.click('[data-remove-item]');
        assert.deepEqual(await values('items[0][cost_price]'), []);
        await page.click('#add-item-row');
        await enter('#item-2-cost_price', '120000', '120.000', 'items[2][cost_price]', '120000');
        await checkCurrencySpacing();

        await visit('/admin/giam-gia/tao-moi');
        await enter('#discount_value', '12,5', '12,5', 'discount_value', '12.5');
        assert.equal(await discountUnit(), '%');
        await page.select('#discount_type', 'fixed');
        await enter('#discount_value', '150000', '150.000', 'discount_value', '150000');
        assert.equal(await discountUnit(), '₫');
        await enter('#max_discount_amount', '2500000', '2.500.000', 'max_discount_amount', '2500000');
        await page.setViewport({ width: 390, height: 844 });
        await page.waitForFunction(() => document.querySelector('#admin-navigation').getBoundingClientRect().right <= 1);
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'Mobile overflow');
        await checkCurrencySpacing();
        await page.screenshot({ path: '.admin-qa/currency-input-mobile.png', fullPage: true });

        // Missing variant and dates guarantees no discount can be saved.
        await page.$eval('.admin-form', form => { form.noValidate = true; });
        await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('.admin-form-actions button[type=submit]')]);
        assert.ok(await page.$('.admin-form-errors'));
        assert.equal(await page.$eval('#discount_type', select => select.value), 'fixed');
        assert.equal(await discountUnit(), '₫');
        assert.deepEqual(await values('discount_value'), ['150000']);
        await page.setJavaScriptEnabled(false);
        await page.$eval('.admin-form', form => { form.noValidate = true; });
        await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('.admin-form-actions button[type=submit]')]);
        assert.ok(await page.$('.admin-form-errors'));
        assert.equal(await page.$eval('#discount_type', select => select.value), 'fixed');
        assert.equal(await discountUnit(), '₫', 'No-JavaScript fallback has the wrong saved unit');
        assert.deepEqual(await values('discount_value'), ['150000']);
        await visit('/admin/giam-gia/tao-moi');
        assert.equal(await discountUnit(), '%', 'No-JavaScript fallback has the wrong default unit');

        await visit('/admin/san-pham/tao-moi');
        await clear('#base_price');
        await page.type('#base_price', '1234567.89');
        assert.deepEqual(await values('base_price'), ['1234567.89'], 'No-JavaScript fallback submits duplicate or stale values');
        checkBrowserErrors();
        console.log(JSON.stringify({ suite: 'currency-input', result: 'PASS' }));
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error.stack); process.exitCode = 1; });
