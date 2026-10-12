// Address form: once a province is picked, load its wards (via the shop's
// own route, which caches provinces.open-api.vn) as suggestions for the
// ward input. A failed load leaves the ward as free text; the server still
// checks the ward against the province whenever it has the list.

export default ({ wardsUrl, provinces = [], province = '', ward = '' } = {}) => ({
    province: provinces.some((item) => item.name === province) ? province : '',
    ward,
    wards: [],
    status: 'idle',
    request: 0,

    init() {
        void this.loadWards();
        this.$watch('province', () => {
            this.ward = '';
            void this.loadWards();
        });
    },

    get provinceCode() {
        return provinces.find((item) => item.name === this.province)?.code ?? null;
    },

    async loadWards() {
        const request = ++this.request;
        this.wards = [];
        const code = this.provinceCode;
        if (code === null) {
            this.status = 'idle';
            return;
        }
        this.status = 'loading';
        try {
            const response = await fetch(wardsUrl.replace('__CODE__', code), { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const { data } = await response.json();
            if (request !== this.request) return;
            this.wards = data.map((item) => item.name);
            this.status = 'ready';
        } catch {
            if (request === this.request) this.status = 'failed';
        }
    },
});
