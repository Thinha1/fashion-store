// Canonical decimal strings avoid floating-point rounding when submitting money.
export function parseCurrency(display) {
    const text = String(display ?? '');
    if (text === '') return '';
    if (!/^(?:\d+|\d{1,3}(?:\.\d{3})+)(?:,\d{0,2})?$/.test(text)) return null;

    const [integer, fraction] = text.split(',');
    return integer.replaceAll('.', '') + (fraction ? `.${fraction}` : '');
}

export default (display = '') => ({
    display,
    init() {
        // Transfer the submission name only after Alpine has initialized.
        // Without JavaScript the visible input keeps submitting its raw value.
        this.$refs.canonical.name = this.$refs.display.name;
        this.$refs.display.removeAttribute('name');
    },
    get canonicalValue() {
        return parseCurrency(this.display) ?? '';
    },
    get valid() {
        return parseCurrency(this.display) !== null;
    },
});
