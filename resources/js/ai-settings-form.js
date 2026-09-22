/**
 * Alpine component for the /admin/cai-dat/ai form's "Kiểm tra kết nối"
 * button — same request/response shape as SupplierController's tax lookup
 * (see supplier-form.js), posting the form's CURRENT (not-yet-saved) values
 * so a wrong endpoint/model/key is caught before Save, not after.
 */
export default (initial, testUrl) => ({
    endpoint: initial.endpoint ?? '',
    model: initial.model ?? '',
    maxTokens: initial.maxTokens ?? '',
    apiKey: '',
    clearApiKey: false,
    testing: false,
    testOk: false,
    testResult: '',
    requestId: 0,

    async test() {
        if (this.testing) return;
        this.requestId++;
        const requestId = this.requestId;
        this.testing = true;
        this.testResult = '';

        try {
            const response = await fetch(testUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({
                    endpoint: this.endpoint, model: this.model, max_tokens: this.maxTokens || null, api_key: this.apiKey,
                }),
            });
            const payload = await response.json().catch(() => null);
            if (requestId !== this.requestId) return;

            if (!response.ok) {
                this.testOk = false;
                this.testResult = response.status === 429
                    ? 'Bạn kiểm tra quá nhanh. Vui lòng thử lại sau một phút.'
                    : payload?.message || 'Chưa thể kiểm tra kết nối.';
                return;
            }

            this.testOk = Boolean(payload?.ok);
            this.testResult = payload?.message || (this.testOk ? 'Kết nối thành công.' : 'Kết nối thất bại.');
        } catch {
            if (requestId === this.requestId) {
                this.testOk = false;
                this.testResult = 'Không kết nối được để kiểm tra. Vui lòng thử lại.';
            }
        } finally {
            if (requestId === this.requestId) this.testing = false;
        }
    },
});
