// Brand / product / variant picker of the discount form. Three tabs share one
// searchable checklist; the ticked ids are posted as targets[brand][] etc.

const TABS = [
    { key: 'brand', label: 'Thương hiệu' },
    { key: 'product', label: 'Sản phẩm' },
    { key: 'variant', label: 'Biến thể' },
];
const SHOWN_LIMIT = 150;

/** Lower case without accents, so "ao khoac" finds "Áo khoác". */
export const fold = (text) => String(text ?? '')
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/đ/gi, 'd')
    .toLowerCase();

const ids = (list) => [...new Set((list ?? []).map(Number).filter(id => Number.isInteger(id) && id > 0))];

/**
 * @param options   {brands, products, variants, selected} from the server
 * @param always    true when the picks are the whole form (new variant discounts);
 *                  false when a parent `appliesTo` switch turns them on (order vouchers)
 * @param maxVariants  most variants one submission may create discounts for (variant mode)
 */
export default (options, always = false, maxVariants = 500) => {
    const brandName = new Map((options.brands ?? []).map(brand => [brand.id, brand.name]));
    // Built once and kept out of Alpine's reactive state: the variant list is long.
    const lists = {
        brand: (options.brands ?? []).map(item => ({ ...item, detail: '', text: fold(item.name) })),
        product: (options.products ?? []).map(item => ({ ...item, detail: brandName.get(item.brand) ?? '', text: fold(`${item.name} ${brandName.get(item.brand) ?? ''}`) })),
        variant: (options.variants ?? []).map(item => ({ ...item, text: fold(`${item.name} ${item.detail} ${brandName.get(item.brand) ?? ''}`) })),
    };
    const names = {
        brand: new Map((options.brands ?? []).map(item => [item.id, item.name])),
        product: new Map((options.products ?? []).map(item => [item.id, item.name])),
    };
    let cache = null;

    return {
        tabs: TABS,
        tab: 'brand',
        search: '',
        onlySelected: false,
        always,
        selected: {
            brand: ids(options.selected?.brand),
            product: ids(options.selected?.product),
            variant: ids(options.selected?.variant),
        },

        setTab(key) {
            this.tab = key;
            this.search = '';
        },

        get matches() {
            const key = `${this.tab}|${this.search}|${this.onlySelected}|${this.selected[this.tab].length}`;
            if (cache?.key === key) return cache.items;

            const words = fold(this.search).split(/\s+/).filter(Boolean);
            const picked = new Set(this.selected[this.tab]);
            const items = lists[this.tab].filter(item => (!this.onlySelected || picked.has(item.id))
                && words.every(word => item.text.includes(word)));
            cache = { key, items };

            return items;
        },

        get shown() {
            return this.matches.slice(0, SHOWN_LIMIT);
        },

        get hidden() {
            return Math.max(0, this.matches.length - SHOWN_LIMIT);
        },

        isSelected(id) {
            return this.selected[this.tab].includes(id);
        },

        toggle(id) {
            const picked = this.selected[this.tab];
            this.selected[this.tab] = picked.includes(id) ? picked.filter(other => other !== id) : [...picked, id];
        },

        /** Ticks everything the search is showing, not just the first page. */
        selectShown() {
            this.selected[this.tab] = ids([...this.selected[this.tab], ...this.matches.map(item => item.id)]);
        },

        clearTab() {
            this.selected[this.tab] = [];
        },

        count(key) {
            return this.selected[key].length;
        },

        /** Every ticked id with its kind, flat, for the hidden inputs that get posted. */
        get picks() {
            return TABS.flatMap(tab => this.selected[tab.key].map(id => ({ type: tab.key, id })));
        },

        get total() {
            return TABS.reduce((sum, tab) => sum + this.count(tab.key), 0);
        },

        /** Variants covered by the picks: the ticked ones, those of ticked products and of ticked brands. */
        get covered() {
            const variants = new Set(this.selected.variant);
            const products = new Set(this.selected.product);
            const brands = new Set(this.selected.brand);

            return lists.variant.filter(item => variants.has(item.id) || products.has(item.product) || brands.has(item.brand)).length;
        },

        /**
         * Edits the ticked brands or products the way the assistant words it:
         * `value` is the whole list, `add` and `remove` change it; each is a
         * list of {id, name}.
         */
        applyTargets(type, edit) {
            let picked = [...this.selected[type]];
            if (Array.isArray(edit.value)) picked = ids(edit.value.map(item => item.id));
            if (Array.isArray(edit.add)) picked = ids([...picked, ...edit.add.map(item => item.id)]);
            if (Array.isArray(edit.remove)) {
                const gone = new Set(ids(edit.remove.map(item => item.id)));
                picked = picked.filter(id => !gone.has(id));
            }
            this.selected[type] = picked;
        },

        snapshot() {
            return { brand: [...this.selected.brand], product: [...this.selected.product], variant: [...this.selected.variant] };
        },

        restore(snapshot) {
            for (const tab of TABS) this.selected[tab.key] = ids(snapshot[tab.key]);
        },

        /** What is ticked, by name, for the assistant's view of the form. */
        describe() {
            const named = type => this.selected[type].map(id => names[type].get(id)).filter(Boolean).slice(0, 30);

            return { brands: named('brand'), products: named('product'), variants: this.selected.variant.length };
        },

        get overLimit() {
            return this.covered > maxVariants;
        },

        get maxVariants() {
            return maxVariants;
        },
    };
};
