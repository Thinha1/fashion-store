const assert = require('node:assert/strict');
const fs = require('node:fs');
const puppeteer = require(process.env.PUPPETEER_MODULE || 'puppeteer');
const base = process.env.ADMIN_TEST_URL || 'http://localhost:8080';

(async () => {
    const browser = await puppeteer.launch({ executablePath: process.env.CHROMIUM_PATH, headless: true, args: ['--no-sandbox', '--disable-dev-shm-usage'] });
    try {
        const page = await browser.newPage();
        const errors = [];
        let failLookup = false;
        page.on('pageerror', error => errors.push(error.message));
        await page.setRequestInterception(true);
        page.on('request', request => {
            if (failLookup && request.url().includes('/tra-cuu-ma-so-thue')) {
                return request.respond({ status: 503, contentType: 'application/json', body: JSON.stringify({ message: 'Dịch vụ đang bận. Nhập thủ công.' }) });
            }
            const url = process.env.ADMIN_TEST_IMAGE_ORIGIN
                ? request.url().replace('http://localhost:9000', process.env.ADMIN_TEST_IMAGE_ORIGIN) : request.url();
            return request.continue({ url });
        });
        await page.goto(base + '/dang-nhap', { waitUntil: 'networkidle0' });
        await page.type('[name=email]', process.env.ADMIN_TEST_EMAIL || 'admin@example.com');
        await page.type('[name=password]', process.env.ADMIN_TEST_PASSWORD || 'password');
        await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('button[type=submit]')]);
        await page.goto(base + '/admin/nha-cong-cap/tao-moi', { waitUntil: 'networkidle0' });
        await page.type('#name', 'Tên đang nhập');
        await page.type('#address', 'Địa chỉ đang nhập');
        await page.type('#phone', '0901234567');
        await page.type('#tax_code', '0316794479');
        const [response] = await Promise.all([
            page.waitForResponse(response => response.url().includes('/tra-cuu-ma-so-thue')),
            page.click('#tax-lookup-button'),
        ]);
        assert.strictEqual(response.status(), 200, await response.text());
        await page.waitForFunction(() => document.querySelector('#name').value.includes('CASSO'));
        assert.ok((await page.$eval('#name', input => input.value)).includes('CASSO'));
        assert.ok((await page.$eval('#address', input => input.value)).length > 10);
        assert.strictEqual(await page.$eval('#phone', input => input.value), '0901234567');
        const fields = await page.$eval('.admin-form', form => Object.fromEntries(new FormData(form)));
        assert.ok(fields.name.includes('CASSO'));
        assert.strictEqual(fields.tax_code, '0316794479');

        failLookup = true;
        await page.click('#tax-lookup-button');
        await page.waitForFunction(() => document.querySelector('[role=alert]').textContent.includes('Dịch vụ đang bận'));
        assert.ok((await page.$eval('#name', input => input.value)).includes('CASSO'));
        assert.strictEqual(await page.$eval('#name', input => input.readOnly), false);
        await page.setViewport({ width: 390, height: 844 });
        await page.waitForFunction(() => document.querySelector('#admin-navigation').getBoundingClientRect().right <= 1);
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
        fs.mkdirSync('.admin-qa', { recursive: true });
        await page.screenshot({ path: '.admin-qa/supplier-tax-mobile.png', fullPage: true });

        await page.setJavaScriptEnabled(false);
        await page.goto(base + '/admin/nha-cong-cap/tao-moi', { waitUntil: 'networkidle0' });
        await page.type('#name', 'Nhập thủ công');
        assert.strictEqual(await page.$eval('.admin-form', form => new FormData(form).get('name')), 'Nhập thủ công');
        assert.deepEqual(errors, []);
        console.log(JSON.stringify({ suite: 'supplier-tax-lookup', result: 'PASS' }));
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error.stack); process.exitCode = 1; });
