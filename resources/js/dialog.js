export function openDialog(dialog) {
    dialog.classList.remove('is-closing');
    if (!dialog.open) dialog.showModal();
}

export function closeDialog(dialog) {
    if (!dialog.open || dialog.classList.contains('is-closing')) return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        dialog.close();
        return;
    }
    dialog.classList.add('is-closing');
    setTimeout(() => {
        dialog.close();
        dialog.classList.remove('is-closing');
    }, 160);
}

export default () => ({
    form: null,
    message: '',
    busy: false,
    open(detail) {
        if (this.busy || this.$refs.dialog.open) return;
        this.form = detail.form;
        this.message = detail.message;
        openDialog(this.$refs.dialog);
    },
    close() {
        if (!this.busy) closeDialog(this.$refs.dialog);
    },
    confirm() {
        if (this.busy || !this.form?.isConnected) return;
        this.busy = true;
        HTMLFormElement.prototype.submit.call(this.form);
    },
});
