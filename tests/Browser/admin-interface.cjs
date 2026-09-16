// Run against a seeded local app with production assets; never confirms destructive actions.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const puppeteer = require(process.env.PUPPETEER_MODULE || 'puppeteer');
const base = process.env.ADMIN_TEST_URL || 'http://localhost:8080';
const output = process.env.ADMIN_TEST_ARTIFACTS || '.admin-qa';

function luminance(css) {
    return css.match(/[\d.]+/g).slice(0, 3).map(Number).map(value => {
        const channel = value / 255;
        return channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;
    }).reduce((total, channel, index) => total + channel * [0.2126, 0.7152, 0.0722][index], 0);
}

(async () => {
    const browser = await puppeteer.launch({
        executablePath: process.env.CHROMIUM_PATH,
        headless: true,
        args: ['--no-sandbox', '--disable-dev-shm-usage'],
    });
    try {
        fs.mkdirSync(output, { recursive: true });
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        if (process.env.ADMIN_TEST_IMAGE_ORIGIN) {
            await page.setRequestInterception(true);
            page.on('request', request => request.continue({
                url: request.url().replace('http://localhost:9000', process.env.ADMIN_TEST_IMAGE_ORIGIN),
            }));
        }
        await page.setViewport({ width: 1440, height: 1000 });
        let pages = 0;
        async function visit(url) {
            const response = await page.goto(url.startsWith('http') ? url : base + url, { waitUntil: 'networkidle0' });
            assert.equal(response.status(), 200, url + ': HTTP ' + response.status());
            pages++;
        }
        async function screenshot(name) {
            await page.screenshot({ path: path.join(output, name + '.png'), fullPage: true });
        }
        async function checkButtons() {
            const buttons = await page.$$eval('.admin-action-primary', nodes => nodes.map(node => {
                const style = getComputedStyle(node);
                return { color: style.color, background: style.backgroundColor, text: node.textContent.trim() };
            }));
            for (const button of buttons) {
                const values = [luminance(button.color), luminance(button.background)].sort((a, b) => b - a);
                assert.ok((values[0] + 0.05) / (values[1] + 0.05) >= 4.5, 'Low contrast: ' + button.text);
            }
        }
        await visit('/dang-nhap');
        await page.type('[name=email]', process.env.ADMIN_TEST_EMAIL || 'admin@example.com');
        await page.type('[name=password]', process.env.ADMIN_TEST_PASSWORD || 'password');
        await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('button[type=submit]')]);

        const editLinks = new Set();
        let productDetail;
        for (const resource of ['san-pham', 'danh-muc', 'thuong-hieu', 'nha-cong-cap', 'giam-gia', 'nhap-hang']) {
            await visit('/admin/' + resource);
            assert.ok(await page.$('.admin-shell'));
            await checkButtons();
            const link = await page.$('a.admin-row-action[href$="/sua"]');
            if (link) editLinks.add(await link.evaluate(node => node.href));
            if (resource === 'san-pham') {
                productDetail = await page.$eval('tbody td a', node => node.href);
                await screenshot('products-desktop');
            }
            const before = await page.$eval('.table-sort', node => {
                const style = getComputedStyle(node);
                return [style.color, style.backgroundColor];
            });
            await page.hover('.table-sort');
            const after = await page.$eval('.table-sort', node => {
                const style = getComputedStyle(node);
                return [style.color, style.backgroundColor];
            });
            assert.deepEqual(after, before, 'Sort header changes color on hover');
            await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('.table-sort')]);
            assert.ok(await page.$('th[aria-sort=ascending]'));

            await page.click('[aria-haspopup=dialog]');
            await page.waitForSelector('.excel-dialog[open]');
            assert.equal(await page.$eval('.excel-dialog', node => getComputedStyle(node).animationName), 'admin-dialog-in');
            await page.keyboard.press('Escape');
            await page.waitForFunction(() => !document.querySelector('.excel-dialog').open);

            await visit('/admin/' + resource + '/tao-moi');
            assert.ok(await page.$('.admin-form-actions'));
            await checkButtons();
            await screenshot(resource + '-create');
        }
        for (const url of editLinks) {
            await visit(url);
            await checkButtons();
        }
        if (productDetail) {
            await visit(productDetail);
            await checkButtons();
            await screenshot('product-detail');
            await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('.page-actions a')]);
            assert.ok(page.url().endsWith('/sua'));
        }

        await visit('/admin/san-pham/tao-moi');
        assert.equal(await page.$('[name=is_featured]'), null);
        assert.equal(await page.$eval('[name=brand_id]', node => node.checkValidity()), false);
        await page.waitForFunction(() => document.activeElement.id === 'brand_id');
        await page.click('#brand_id');
        await page.waitForSelector('#brand_id-options', { visible: true });
        // Exercise logo rendering even when the local demo brands have no uploaded logo.
        await page.evaluate(() => {
            const picker = window.Alpine.$data(document.querySelector('.image-select'));
            picker.options[1].image = 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="40" height="28"><rect width="40" height="28" fill="#175b60"/></svg>');
        });
        await page.waitForFunction(() => document.querySelector('#brand_id-option-1 img')?.naturalWidth > 0);
        assert.ok(await page.$eval('#brand_id-option-1', option => option.querySelector('.image-select-logo').getBoundingClientRect().left >= option.firstElementChild.getBoundingClientRect().right));
        await screenshot('brand-dropdown');
        await page.keyboard.press('ArrowDown');
        await page.keyboard.press('Enter');
        const brandValue = await page.$eval('[name=brand_id]', node => node.value);
        assert.ok(brandValue);
        assert.equal(await page.$eval('.admin-form', form => new FormData(form).get('brand_id')), brandValue);
        await page.waitForFunction(() => document.querySelector('#brand_id img')?.naturalWidth > 0);
        await page.keyboard.press('ArrowDown');
        await page.keyboard.press('Escape');
        assert.equal(await page.$eval('[name=brand_id]', node => node.value), brandValue);
        assert.equal(await page.$eval('#brand_id', node => node.getAttribute('aria-expanded')), 'false');
        assert.equal(await page.$eval('#status', node => node.type), 'checkbox');
        assert.equal(await page.$eval('.admin-form', form => new FormData(form).getAll('status').at(-1)), 'archived');
        await page.click('#status');
        assert.equal(await page.$eval('.admin-form', form => new FormData(form).getAll('status').at(-1)), 'active');
        await page.click('#status');
        assert.equal(await page.$eval('.admin-form', form => new FormData(form).getAll('status').at(-1)), 'archived');
        await page.click('#add-variant-row');
        await page.click('#add-variant-row');
        assert.equal((await page.$$('[data-variant-row]')).length, 2);
        await page.click('.remove-variant-row');
        assert.equal((await page.$$('[data-variant-row]')).length, 1);
        await (await page.$('#images')).uploadFile('database/seeders/assets/catalog/ao-thun.jpg', 'database/seeders/assets/catalog/ao-so-mi.jpg');
        await page.waitForFunction(() => document.querySelectorAll('img[alt="Ảnh sản phẩm mới chọn"]').length === 2);
        // Empty required fields make this submission invalid, so no product is created.
        await page.$eval('.admin-form', form => { form.noValidate = true; });
        await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('.admin-form-actions button[type=submit]')]);
        assert.ok(await page.$('.admin-form-errors'), 'Missing server validation summary');

        await visit('/admin/nhap-hang/tao-moi');
        await page.click('#add-item-row');
        await page.click('#add-item-row');
        const receiptLabels = await page.$$eval('[data-item-row] label', labels => labels.map(label => ({
            id: label.htmlFor,
            controlId: label.control?.id,
            rowMatches: label.control?.closest('[data-item-row]') === label.closest('[data-item-row]'),
        })));
        assert.equal(receiptLabels.length, 6);
        assert.equal(new Set(receiptLabels.map(label => label.id)).size, 6, 'Receipt rows reuse field IDs');
        assert.ok(receiptLabels.every(label => label.id === label.controlId && label.rowMatches), 'Receipt label targets the wrong row');
        await page.click('label[for="' + receiptLabels[4].id + '"]');
        assert.equal(await page.evaluate(() => document.activeElement.id), receiptLabels[4].id);
        await page.click('[data-remove-item]');
        assert.equal((await page.$$('[data-item-row]')).length, 1);
        await page.click('#add-item-row');
        assert.equal(await page.$$eval('#items-list [id]', fields => new Set(fields.map(field => field.id)).size), 6);

        await visit('/admin/san-pham');
        await page.click('.admin-row-actions form button');
        await page.waitForSelector('.admin-dialog[open]');
        await screenshot('confirm-dialog');
        await page.click('.admin-dialog .admin-action-secondary');
        await page.waitForFunction(() => !document.querySelector('.admin-dialog').open);
        await page.click('.admin-row-actions form button');
        await page.waitForSelector('.admin-dialog[open]');
        await page.mouse.click(10, 10);
        await page.waitForFunction(() => !document.querySelector('.admin-dialog').open);
        // Intercept native submission in this isolated page: no delete request is sent.
        await page.evaluate(() => {
            window.confirmedForms = [];
            HTMLFormElement.prototype.submit = function () { window.confirmedForms.push(this.action); };
        });
        const deleteUrl = await page.$eval('.admin-row-actions form', form => form.action);
        await page.click('.admin-row-actions form button');
        await page.waitForSelector('.admin-dialog[open]');
        await page.click('.admin-dialog .admin-action-primary');
        await page.$eval('.admin-dialog .admin-action-primary', button => button.click());
        assert.deepEqual(await page.evaluate(() => window.confirmedForms), [deleteUrl]);
        await page.keyboard.press('Escape');
        assert.ok(await page.$('.admin-dialog[open]'), 'Busy confirmation must stay open');
        await visit('/admin/san-pham');
        await page.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: 'reduce' }]);
        await page.click('[aria-haspopup=dialog]');
        await page.keyboard.press('Escape');
        await page.waitForFunction(() => !document.querySelector('.excel-dialog').open);

        await visit('/admin/dashboard');
        await screenshot('dashboard-desktop');
        await page.setViewport({ width: 390, height: 844 });
        const mobileUrls = ['san-pham', 'danh-muc', 'thuong-hieu', 'nha-cong-cap', 'giam-gia', 'nhap-hang']
            .flatMap(resource => ['/admin/' + resource, '/admin/' + resource + '/tao-moi']);
        for (const url of [...mobileUrls, '/admin/dashboard']) {
            await visit(url);
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'Mobile overflow: ' + url);
            if (url === '/admin/san-pham') {
                await page.focus('.data-table');
                await page.keyboard.press('ArrowRight');
                await page.waitForFunction(() => {
                    const region = document.querySelector('.data-table');
                    return region.scrollWidth <= region.clientWidth || region.scrollLeft > 0;
                });
            }
            await screenshot('mobile-' + url.split('/').slice(2).join('-'));
        }
        await page.setViewport({ width: 390, height: 500 });
        await page.click('[aria-label="Mở menu quản trị"]');
        await page.waitForFunction(() => !document.querySelector('#admin-navigation').inert);
        assert.equal(await page.$eval('#admin-navigation nav', node => getComputedStyle(node).scrollbarWidth), 'none');
        await page.focus('#admin-navigation nav');
        await page.keyboard.press('End');
        await page.waitForFunction(() => document.querySelector('#admin-navigation nav').scrollTop > 0);
        await page.keyboard.press('Escape');
        await page.waitForFunction(() => document.querySelector('#admin-navigation').inert);
        assert.deepEqual(errors, [], 'Browser errors');
        console.log(JSON.stringify({ result: 'PASS', pages, screenshots: output, checks: 'contrast, edit links, sorting, dialogs, forms, images, validation, mobile, sidebar' }));
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error.stack); process.exitCode = 1; });
