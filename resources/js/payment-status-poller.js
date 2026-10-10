// While a bank transfer is outstanding, ask the server every few seconds
// whether the SePay webhook has marked the order paid, and reload the page
// once the payment status changes. Gives up after `maxChecks` polls.
export default ({ url, current, intervalMs = 10000, maxChecks = 180 } = {}) => ({
    checks: 0,
    timer: null,
    init() {
        this.timer = setInterval(() => this.check(), intervalMs);
    },
    destroy() {
        clearInterval(this.timer);
    },
    async check() {
        if (document.hidden) return;
        if (++this.checks > maxChecks) {
            clearInterval(this.timer);
            return;
        }
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!response.ok) return;
            const { payment_status: status } = await response.json();
            if (status !== current) {
                clearInterval(this.timer);
                window.location.reload();
            }
        } catch {
            // Network blip — try again on the next tick.
        }
    },
});
