export default (initial, lookupUrl) => ({
    taxCode: initial.taxCode ?? '',
    name: initial.name ?? '',
    address: initial.address ?? '',
    loading: false,
    message: '',
    error: '',
    requestId: 0,
    resetLookup() {
        this.requestId++;
        this.loading = false;
        this.message = '';
        this.error = '';
    },
    async lookup() {
        if (this.loading) return;
        this.resetLookup();
        const taxCode = this.taxCode.trim();
        if (!/^\d{10}(?:-?\d{3})?$/.test(taxCode)) {
            this.error = 'Nhập mã số thuế doanh nghiệp gồm 10 hoặc 13 số.';
            return;
        }
        const requestId = this.requestId;
        this.loading = true;
        try {
            const response = await fetch(`${lookupUrl}?${new URLSearchParams({ tax_code: taxCode })}`, {
                headers: { Accept: 'application/json' }, credentials: 'same-origin',
            });
            const payload = await response.json();
            if (requestId !== this.requestId) return;
            if (!response.ok) {
                this.error = response.status === 429 ? 'Bạn tra cứu quá nhanh. Vui lòng thử lại sau một phút.'
                    : payload.message || 'Chưa thể tra cứu. Bạn vẫn có thể nhập thông tin thủ công.';
                return;
            }
            this.name = payload.data.name;
            this.address = payload.data.address;
            this.taxCode = payload.data.tax_code;
        } catch {
            if (requestId === this.requestId) this.error = 'Không kết nối được dịch vụ tra cứu. Bạn vẫn có thể nhập thông tin thủ công.';
        } finally {
            if (requestId === this.requestId) this.loading = false;
        }
    },
});
