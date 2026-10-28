// A home-page shelf: four products in view and arrows that slide one view at
// a time. The shelf keeps one page of four loaded ahead of the reader: the
// first is fetched as the shelf nears the screen, the next whenever the
// reader reaches the last loaded page, so the forward arrow never waits on
// the network unless the reader outruns it.
export default ({ next = null } = {}) => ({
    nextUrl: next,
    loading: null,
    failed: false,
    seen: false,
    atStart: true,
    atEnd: false,
    status: '',

    init() {
        this.update();
        if (!this.nextUrl) return;

        if (!('IntersectionObserver' in window)) {
            this.seen = true;
            this.prefetch();
            return;
        }

        const observer = new IntersectionObserver((entries) => {
            if (entries.some((entry) => entry.isIntersecting)) {
                observer.disconnect();
                this.seen = true;
                this.prefetch();
            }
        }, { rootMargin: '300px 0px' });
        observer.observe(this.$root);
    },

    update() {
        const track = this.$refs.track;
        const remaining = track.scrollWidth - (track.scrollLeft + track.clientWidth);
        this.atStart = track.scrollLeft <= 4;
        this.atEnd = remaining <= 4;

        // On the last loaded page: fetch the one after it now.
        if (this.seen && !this.failed && remaining < track.clientWidth) this.prefetch();
    },

    slide(direction) {
        const track = this.$refs.track;
        const smooth = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        track.scrollBy({ left: direction * track.clientWidth, behavior: smooth ? 'smooth' : 'auto' });
    },

    prev() {
        this.slide(-1);
    },

    async next() {
        this.failed = false;
        this.update();
        if (this.atEnd) {
            if (!this.nextUrl && !this.loading) return;
            const added = await this.prefetch();
            if (!added) return;
            await this.$nextTick();
        }
        this.slide(1);
    },

    // Fetch the next page once; concurrent callers share the same request.
    prefetch() {
        if (this.loading) return this.loading;
        if (!this.nextUrl) return Promise.resolve(false);

        this.loading = this.fetchPage().finally(() => {
            this.loading = null;
            this.update();
        });

        return this.loading;
    },

    async fetchPage() {
        try {
            const response = await fetch(this.nextUrl, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!response.ok) throw new Error(String(response.status));
            const { html, next } = await response.json();
            this.$refs.track.insertAdjacentHTML('beforeend', html);
            this.nextUrl = next;
            this.failed = false;
            this.status = '';
            return true;
        } catch {
            this.failed = true;
            this.status = 'Chưa tải thêm được sản phẩm. Bấm lại sau ít phút.';
            return false;
        }
    },
});
