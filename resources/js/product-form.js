export default (removedImages = []) => ({
    nextIndex: 0,
    removedImages: Array.isArray(removedImages) ? removedImages.map(Number) : [],
    form: null,
    init() {
        this.form = this.$el;
    },
    addVariant() {
        const list = this.form.querySelector('#variants-list');
        const row = this.form.querySelector('#variant-row-template').content.cloneNode(true);
        let key;
        do { key = `new-${this.nextIndex++}`; }
        while ([...list.children].some((item) => item.dataset.variantKey === key));
        row.querySelectorAll('*').forEach((element) => {
            for (const attribute of [...element.attributes]) {
                if (attribute.value.includes('__INDEX__')) {
                    element.setAttribute(attribute.name, attribute.value.replaceAll('__INDEX__', key));
                }
            }
        });
        list.append(row);
        list.lastElementChild.querySelector('input').focus();
    },
    removeVariant(button) {
        button.closest('[data-variant-row]').remove();
    },
    removeImage(id) {
        if (!this.removedImages.includes(id)) this.removedImages.push(id);
    },
});
