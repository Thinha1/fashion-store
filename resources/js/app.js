import Alpine from 'alpinejs';
import mask from '@alpinejs/mask';
import featuredProduct from './featured-product';
import adminConfirm, { openDialog, closeDialog } from './dialog';
import imageSelect from './image-select';
import imageUpload from './image-upload';
import productForm from './product-form';
import productDetail from './product-detail';
import currencyInput from './currency-input';
import supplierForm from './supplier-form';
import productAiChat from './product-ai-chat';

window.Alpine = Alpine;

Alpine.plugin(mask);
Alpine.data('featuredProduct', featuredProduct);
Alpine.data('adminConfirm', adminConfirm);
Alpine.data('imageSelect', imageSelect);
Alpine.data('imageUpload', imageUpload);
Alpine.data('productForm', productForm);
Alpine.data('productDetail', productDetail);
Alpine.data('currencyInput', currencyInput);
Alpine.data('supplierForm', supplierForm);
Alpine.data('productAiChat', productAiChat);

Alpine.data('excelImport', (reopen = false) => ({
    busy: false,
    init() {
        if (reopen) this.$nextTick(() => this.openDialog());
    },
    openDialog() {
        openDialog(this.$refs.importDialog);
    },
    closeDialog() {
        if (!this.busy) closeDialog(this.$refs.importDialog);
    },
    trapFocus(event) {
        const controls = [...this.$refs.importDialog.querySelectorAll('a[href], button:not(:disabled), input:not(:disabled)')];
        const first = controls[0];
        const last = controls.at(-1);
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    },
}));

Alpine.data('dataTable', (hasActions = false) => ({
    query: '',
    total: 0,
    visible: 0,
    init() {
        this.total = this.$refs.rows.querySelectorAll('tr:not(:has(td[colspan]))').length;
        this.visible = this.total;
        this.$watch('query', () => this.filter());
    },
    filter() {
        const normalize = (value) => value.toLocaleLowerCase('vi').normalize('NFD').replace(/[\u0300-\u036f]/g, '').replaceAll('đ', 'd');
        const query = normalize(this.query.trim());
        this.visible = 0;
        for (const row of this.$refs.rows.querySelectorAll('tr:not(:has(td[colspan]))')) {
            const cells = [...row.cells];
            const content = (hasActions ? cells.slice(0, -1) : cells).map((cell) => cell.textContent).join(' ');
            row.hidden = !normalize(content).includes(query);
            if (!row.hidden) this.visible++;
        }
    },
}));

Alpine.start();
