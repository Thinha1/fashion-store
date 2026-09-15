export default (initial) => ({
    featured: initial,
    busy: false,
    error: '',
    message: '',
    async save(form) {
        if (this.busy) return;

        this.busy = true;
        this.error = '';
        this.message = '';
        try {
            const data = new FormData(form);
            data.set('is_featured', this.featured ? '0' : '1');

            const response = await fetch(form.action, {
                method: 'POST',
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                body: data,
            });
            if (!response.ok) throw new Error('Save failed');

            const result = await response.json();
            if (typeof result.is_featured !== 'boolean') throw new Error('Invalid response');

            this.featured = result.is_featured;
            this.message = this.featured ? 'Đã đánh dấu nổi bật.' : 'Đã bỏ nổi bật.';
        } catch {
            this.error = 'Chưa lưu được. Vui lòng thử lại hoặc tải lại trang.';
        } finally {
            this.busy = false;
        }
    },
});
