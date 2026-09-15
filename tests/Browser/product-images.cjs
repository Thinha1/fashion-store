// Uses page-local gallery fixtures and invalid submissions; never saves demo data.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const puppeteer = require(process.env.PUPPETEER_MODULE || 'puppeteer');
const base = process.env.ADMIN_TEST_URL || 'http://localhost:8080';
const photos = ['database/seeders/assets/catalog/ao-thun.jpg', 'database/seeders/assets/catalog/ao-so-mi.jpg'];

(async () => {
    const browser = await puppeteer.launch({ executablePath: process.env.CHROMIUM_PATH, headless: true, args: ['--no-sandbox', '--disable-dev-shm-usage'] });
    try {
        fs.mkdirSync('.admin-qa', { recursive: true });
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        if (process.env.ADMIN_TEST_IMAGE_ORIGIN) {
            await page.setRequestInterception(true);
            page.on('request', request => request.continue({ url: request.url().replace('http://localhost:9000', process.env.ADMIN_TEST_IMAGE_ORIGIN) }));
        }
        async function visit(url) {
            const response = await page.goto(url.startsWith('http') ? url : base + url, { waitUntil: 'networkidle0' });
            assert.equal(response.status(), 200, url);
        }
        await page.setViewport({ width: 1440, height: 1000 });
        await visit('/dang-nhap');
        await page.type('[name=email]', process.env.ADMIN_TEST_EMAIL || 'admin@example.com');
        await page.type('[name=password]', process.env.ADMIN_TEST_PASSWORD || 'password');
        await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('button[type=submit]')]);
        await visit('/admin/san-pham');
        const editUrl = await page.$eval('a.admin-row-action[href$="/sua"]', link => link.href);
        await visit(editUrl);
        assert.equal(await page.$('[data-image-variant]'), null);
        const savedId = await page.$eval('[data-saved-image]', image => image.dataset.savedImage);
        const savedSelector = `[data-saved-image="${savedId}"]`;
        assert.ok(await page.$(`${savedSelector} .fa-trash-can`));
        assert.ok(await page.$eval(`${savedSelector} button`, button => {
            const image = button.closest('figure').getBoundingClientRect();
            const rect = button.getBoundingClientRect();
            return rect.top - image.top < 10 && image.right - rect.right < 10;
        }));
        await page.click(`${savedSelector} button`);
        await page.waitForFunction(id => new FormData(document.querySelector('.admin-form')).getAll('removed_images[]').includes(id), {}, savedId);
        assert.equal(await page.$eval(savedSelector, image => getComputedStyle(image).display), 'none');
        await page.click('[x-on\\:click="removedImages = []"]');
        await page.waitForFunction(selector => getComputedStyle(document.querySelector(selector)).display !== 'none', {}, savedSelector);

        await page.click('#add-variant-row');
        const newKey = await page.$eval('#variants-list', list => list.lastElementChild.dataset.variantKey);
        const row = `[data-variant-key="${newKey}"]`;
        const upload = await page.$(`${row} input[type=file]`);
        await upload.uploadFile(...photos);
        await page.waitForFunction(selector => document.querySelectorAll(selector + ' img[alt="Ảnh biến thể mới chọn"]').length === 2, {}, row);
        await page.click(`${row} .admin-image-remove`);
        await page.waitForFunction(selector => document.querySelector(selector + ' input[type=file]').files.length === 1, {}, row);
        assert.equal(await upload.evaluate(input => input.files[0].name), 'ao-so-mi.jpg');
        assert.equal((await page.$$(`${row} img[alt="Ảnh biến thể mới chọn"]`)).length, 1);
        await (await page.$('#images')).uploadFile(...photos);
        await page.waitForFunction(() => document.querySelectorAll('img[alt="Ảnh sản phẩm mới chọn"]').length === 2);
        await page.$eval('img[alt="Ảnh sản phẩm mới chọn"]', image => image.closest('figure').querySelector('button').click());
        await page.waitForFunction(() => document.querySelector('#images').files.length === 1);
        await page.screenshot({ path: '.admin-qa/product-images-desktop.png', fullPage: true });
        await page.setViewport({ width: 390, height: 844 });
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'Mobile form overflow');
        await page.screenshot({ path: '.admin-qa/product-images-mobile.png', fullPage: true });

        await page.$eval(`${savedSelector} button`, button => button.click());
        await page.$eval('.admin-form', form => { form.noValidate = true; form.querySelector('#name').value = ''; });
        await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('.admin-form-actions button[type=submit]')]);
        assert.ok(await page.$('.admin-form-errors'));
        assert.ok(await page.$(row), 'New variant disappeared after validation');
        assert.equal(await page.$eval(savedSelector, image => getComputedStyle(image).display), 'none');
        await page.click('#add-variant-row');
        const keys = await page.$$eval('[data-variant-row]', rows => rows.map(item => item.dataset.variantKey));
        assert.equal(new Set(keys).size, keys.length, 'Duplicate row keys after validation');

        await page.setViewport({ width: 1440, height: 1000 });
        await visit('/san-pham');
        const urls = await page.$$eval('a.pg-card', links => links.map(link => link.href));
        let pair;
        for (const url of urls) {
            await visit(url);
            pair = await page.evaluate(() => {
                const state = Alpine.$data(document.querySelector('[x-data^="productDetail("]'));
                const first = state.variants[0];
                const second = state.variants.find(variant => variant.color !== first?.color);
                return second ? [first, second].map(({ id, size, color }) => ({ id, size, color })) : null;
            });
            if (pair) break;
        }
        assert.ok(pair, 'Need a seeded product with at least two colors');
        // Page-local photos make color changes visible without altering the database.
        await page.evaluate(([first, second]) => {
            const state = Alpine.$data(document.querySelector('[x-data^="productDetail("]'));
            const picture = color => 'data:image/svg+xml,' + encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" width="400" height="500"><rect width="400" height="500" fill="${color}"/></svg>`);
            state.allImages = [
                { id: 1, variantId: first.id, url: picture('#17343a'), alt: 'First front' },
                { id: 2, variantId: first.id, url: picture('#425d62'), alt: 'First back' },
                { id: 3, variantId: second.id, url: picture('#ddd5c8'), alt: 'Second color' },
                { id: 4, variantId: null, url: picture('#e9f3ef'), alt: 'Shared photo' },
            ];
            state.pickSize(first.size);
            state.pickColor(first.color);
        }, pair);
        const mainPhoto = '.product-card-media > img';
        await page.waitForFunction(selector => document.querySelector(selector)?.alt === 'First front', {}, mainPhoto);
        await page.click('[aria-label="Ảnh sau"]');
        await page.waitForFunction(selector => document.querySelector(selector)?.alt === 'First back', {}, mainPhoto);
        await page.$$eval('.color-swatch', (buttons, color) => buttons.find(button => button.getAttribute('aria-label') === 'Màu ' + color).click(), pair[1].color);
        await page.waitForFunction(selector => document.querySelector(selector)?.alt === 'Second color', {}, mainPhoto);
        assert.equal(await page.$('[aria-label="Ảnh sau"]'), null);
        await page.evaluate(id => {
            const state = Alpine.$data(document.querySelector('[x-data^="productDetail("]'));
            state.allImages = state.allImages.filter(image => image.variantId !== id);
        }, pair[1].id);
        await page.waitForFunction(selector => document.querySelector(selector)?.alt === 'Shared photo', {}, mainPhoto);
        await page.setViewport({ width: 390, height: 844 });
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'Mobile gallery overflow');
        await page.screenshot({ path: '.admin-qa/variant-gallery-mobile.png', fullPage: true });
        assert.deepEqual(errors, [], 'Browser JavaScript errors');
        console.log(JSON.stringify({ result: 'PASS', checks: 'variant uploads, trash-can placement, remove files, staged deletion, undo, validation, gallery color switch, fallback, mobile' }));
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error.stack); process.exitCode = 1; });
