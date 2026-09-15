export default ({ variants = [], images = [] } = {}) => {
    const first = variants.find((variant) => variant.stock > 0) ?? variants[0];
    return {
        variants,
        allImages: images,
        activeImage: 0,
        selectedSize: first?.size ?? null,
        selectedColor: first?.color ?? null,
        qty: 1,
        get variant() {
            return this.variants.find((variant) => variant.size === this.selectedSize && variant.color === this.selectedColor) ?? null;
        },
        get images() {
            const specific = this.variant ? this.allImages.filter((image) => image.variantId === this.variant.id) : [];
            return specific.length ? specific : this.allImages.filter((image) => image.variantId === null);
        },
        get inStock() { return this.maxQty > 0; },
        get maxQty() { return this.variant?.stock ?? 0; },
        hasVariant(size, color) {
            return this.variants.some((variant) => variant.size === size && variant.color === color);
        },
        pickSize(size) {
            this.selectedSize = size;
            if (!this.hasVariant(size, this.selectedColor)) {
                this.selectedColor = this.variants.find((variant) => variant.size === size)?.color ?? null;
            }
            this.resetSelection();
        },
        pickColor(color) {
            this.selectedColor = color;
            if (!this.hasVariant(this.selectedSize, color)) {
                this.selectedSize = this.variants.find((variant) => variant.color === color)?.size ?? null;
            }
            this.resetSelection();
        },
        resetSelection() { this.qty = 1; this.activeImage = 0; },
        prevImage() {
            if (this.images.length) this.activeImage = (this.activeImage - 1 + this.images.length) % this.images.length;
        },
        nextImage() {
            if (this.images.length) this.activeImage = (this.activeImage + 1) % this.images.length;
        },
        inc() { if (this.qty < this.maxQty) this.qty++; },
        dec() { if (this.qty > 1) this.qty--; },
    };
};
