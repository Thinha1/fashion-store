// The home cover: one featured product large and the next three beside it.
// The arrows move through them; nothing advances on its own.
export default ({ count = 1 } = {}) => ({
    active: 0,
    count,
    status: '',

    // How far product i sits after the one on the cover (0 = on the cover).
    position(i) {
        return (i - this.active + this.count) % this.count;
    },

    upNext(i) {
        const at = this.position(i);
        return at >= 1 && at <= 3;
    },

    go(step) {
        this.active = (this.active + step + this.count) % this.count;
        const name = this.$root.querySelectorAll('.cover-lead strong')[this.active]?.textContent ?? '';
        this.status = `Đang xem ${name}`;
    },

    next() {
        this.go(1);
    },

    prev() {
        this.go(-1);
    },
});
