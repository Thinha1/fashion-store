export default () => ({
    ready: false,
    open: false,
    invalid: false,
    value: '',
    options: [],
    activeIndex: 0,
    brokenImages: {},
    upward: false,
    maxHeight: 256,
    init() {
        this.options = [...this.$refs.native.options].map(option => ({
            value: option.value, label: option.textContent.trim(), image: option.dataset.image || null,
        }));
        this.value = this.$refs.native.value;
        this.ready = true;
    },
    get selected() {
        return this.options.find(option => option.value === this.value) || this.options[0];
    },
    show() {
        const bounds = this.$refs.trigger.getBoundingClientRect();
        const below = window.innerHeight - bounds.bottom;
        this.upward = below < 260 && bounds.top > below;
        this.maxHeight = Math.max(120, Math.min(256, (this.upward ? bounds.top : below) - 12));
        this.activeIndex = Math.max(0, this.options.findIndex(option => option.value === this.value));
        this.open = true;
        this.reveal();
    },
    reveal() {
        this.$nextTick(() => this.$refs.list.querySelectorAll('[role="option"]')[this.activeIndex]?.scrollIntoView({ block: 'nearest' }));
    },
    choose(index) {
        this.value = this.options[index].value;
        this.$refs.native.value = this.value;
        this.$refs.native.dispatchEvent(new Event('change', { bubbles: true }));
        this.invalid = false;
        this.open = false;
        this.$refs.trigger.focus();
    },
    keydown(event) {
        if (event.key === 'Tab' || event.key === 'Escape') {
            this.open = false;
            return;
        }
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            if (this.open) this.choose(this.activeIndex);
            else this.show();
            return;
        }
        const movement = { ArrowDown: 1, ArrowUp: -1, Home: -Infinity, End: Infinity }[event.key];
        if (movement === undefined) return;
        event.preventDefault();
        if (!this.open) this.show();
        this.activeIndex = Math.max(0, Math.min(this.options.length - 1, this.activeIndex + movement));
        this.reveal();
    },
});
