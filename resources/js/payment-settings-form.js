/**
 * Alpine component for /admin/cai-dat/thanh-toan: a live VietQR preview of
 * the values being typed (an empty field shows the .env fallback, the same
 * rule BankTransfer applies), a random SePay webhook key generator and the
 * "Kiểm tra webhook" button (PaymentSettingController::test).
 */
export function previewQrUrl({ bankId, accountNumber, accountName }) {
    if (!bankId || !accountNumber) return '';
    const query = new URLSearchParams({ amount: '10000', addInfo: 'DH TEST', accountName: accountName ?? '' });

    return `https://img.vietqr.io/image/${encodeURIComponent(bankId)}-${encodeURIComponent(accountNumber)}-compact2.png?${query}`;
}

/** 48 hex characters from the browser's CSPRNG. */
export function randomKey(bytes = 24, crypto = globalThis.crypto) {
    return [...crypto.getRandomValues(new Uint8Array(bytes))].map((byte) => byte.toString(16).padStart(2, '0')).join('');
}

export default (initial, testUrl) => ({
    bankId: initial.bankId ?? '',
    accountNumber: initial.accountNumber ?? '',
    accountName: initial.accountName ?? '',
    fallback: initial.fallback ?? {},
    apiKey: '',
    testing: false,
    testOk: false,
    testResult: '',

    qrUrl() {
        return previewQrUrl({
            bankId: this.bankId.trim().toUpperCase() || this.fallback.bankId,
            accountNumber: this.accountNumber.trim() || this.fallback.accountNumber,
            accountName: this.accountName.trim().toUpperCase() || this.fallback.accountName,
        });
    },

    generateKey() {
        this.apiKey = randomKey();
    },

    async test() {
        if (this.testing) return;
        this.testing = true;
        this.testResult = '';
        try {
            const response = await fetch(testUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
            });
            const payload = await response.json().catch(() => null);
            this.testOk = response.ok && Boolean(payload?.ok);
            this.testResult = response.status === 429
                ? 'Bạn kiểm tra quá nhanh. Vui lòng thử lại sau một phút.'
                : payload?.message || 'Chưa thể kiểm tra webhook.';
        } catch {
            this.testOk = false;
            this.testResult = 'Không kết nối được để kiểm tra. Vui lòng thử lại.';
        } finally {
            this.testing = false;
        }
    },
});
