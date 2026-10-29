// The admin chat assistant on the discount form: what it sees of the form
// (describeDiscountForm) and how a `set_fields` reply is written into it
// (applyDiscountFields). The reply was already cleaned and resolved on the
// server (DiscountFormFields, DiscountFieldResolver): values are in the form's
// own formats and brands/products arrive as {id, name}.
import { flashField, setCheckboxChecked, setFieldValue } from './form-fill.js';

export const DISCOUNT_FIELD_LABELS = {
    name: 'tên ưu đãi',
    code: 'mã voucher',
    is_auto_apply: 'tự động áp dụng',
    discount_type: 'kiểu giảm',
    discount_value: 'mức giảm',
    max_discount_amount: 'giảm tối đa',
    min_order_amount: 'đơn tối thiểu',
    usage_limit: 'tổng lượt dùng',
    usage_limit_per_customer: 'lượt dùng mỗi khách',
    starts_at: 'bắt đầu',
    ends_at: 'kết thúc',
    is_active: 'đang hoạt động',
    applies_to: 'phạm vi áp dụng',
    brands: 'thương hiệu',
    products: 'sản phẩm',
};

const TEXT_FIELDS = {
    name: '#name',
    code: '#code',
    discount_type: '#discount_type',
    usage_limit: '#usage_limit',
    usage_limit_per_customer: '#usage_limit_per_customer',
    starts_at: '#starts_at',
    ends_at: '#ends_at',
};
const MONEY_FIELDS = {
    discount_value: '#discount_value',
    max_discount_amount: '#max_discount_amount',
    min_order_amount: '#min_order_amount',
};
const CHECKBOXES = {
    is_auto_apply: 'input[type="checkbox"][name="is_auto_apply"]',
    is_active: 'input[type="checkbox"][name="is_active"]',
};
const PICKS = { brands: 'brand', products: 'product' };
const APPLIES_TO = 'input[type="radio"][name="applies_to"]';

/** 1250000 → "1.250.000", 12.5 → "12,5": how the form's money fields show a number. */
export function formatAmount(value) {
    const [integer, fraction] = String(value).split('.');

    return integer.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + (fraction ? `,${fraction}` : '');
}

/** The brand/product/variant picker on the form: its Alpine state and root element, or null when the form has none. */
function pickerOf(form, alpine) {
    const root = form.querySelector('[x-data*="discountTargets"]');

    return root ? { root, data: alpine.$data(root) } : null;
}

const canonicalOf = (input) => input?.closest('.currency-input')?.querySelector('input[type="hidden"]');

/**
 * Money fields are the `<x-currency-input>` component: the masked visible
 * field and a hidden "canonical" one that is what gets submitted. Like the
 * product form's price, both are written directly rather than through the
 * mask, which only copes with one keystroke at a time.
 */
function setMoneyValue(input, value) {
    if (!input) return false;
    input.value = value === '' ? '' : formatAmount(value);
    const canonical = canonicalOf(input);
    if (canonical) canonical.value = value === '' ? '' : String(value);
    flashField(input);

    return true;
}

function setRadio(form, value) {
    const radio = form.querySelector(`${APPLIES_TO}[value="${value}"]`);
    if (!radio) return false;
    Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'checked').set.call(radio, true);
    radio.dispatchEvent(new Event('change', { bubbles: true }));
    flashField(radio.closest('label') ?? radio);

    return true;
}

/** Everything the assistant can change, as it is now — the undo half of applyDiscountFields. */
function snapshotForm(form, alpine) {
    const entries = (fields, read) => Object.fromEntries(Object.entries(fields).map(([field, selector]) => [field, read(form.querySelector(selector))]));

    return {
        text: entries(TEXT_FIELDS, (el) => el?.value ?? null),
        money: entries(MONEY_FIELDS, (el) => (el ? { display: el.value, canonical: canonicalOf(el)?.value ?? '' } : null)),
        checks: entries(CHECKBOXES, (el) => el?.checked ?? null),
        appliesTo: form.querySelector(`${APPLIES_TO}:checked`)?.value ?? null,
        picks: pickerOf(form, alpine)?.data.snapshot() ?? null,
    };
}

function restoreForm(snapshot, form, alpine) {
    for (const [field, value] of Object.entries(snapshot.text)) {
        const el = form.querySelector(TEXT_FIELDS[field]);
        if (el && value !== null && el.value !== value) setFieldValue(el, value);
    }
    for (const [field, value] of Object.entries(snapshot.money)) {
        const el = form.querySelector(MONEY_FIELDS[field]);
        if (el && value && el.value !== value.display) {
            el.value = value.display;
            const canonical = canonicalOf(el);
            if (canonical) canonical.value = value.canonical;
            flashField(el);
        }
    }
    for (const [field, value] of Object.entries(snapshot.checks)) {
        const el = form.querySelector(CHECKBOXES[field]);
        if (el && value !== null && el.checked !== value) setCheckboxChecked(el, value);
    }
    if (snapshot.appliesTo !== null && form.querySelector(`${APPLIES_TO}:checked`)?.value !== snapshot.appliesTo) setRadio(form, snapshot.appliesTo);
    const picker = pickerOf(form, alpine);
    if (picker && snapshot.picks) picker.data.restore(snapshot.picks);
}

/**
 * The form as the assistant sees it, sent with every turn (form_context):
 * what kind of discount it is and what is filled in, so "đổi thành 15%" or
 * "thêm Levents" works on what is there.
 */
export function describeDiscountForm(form, alpine) {
    if (!form?.querySelector) return null;

    const value = (selector) => form.querySelector(selector)?.value?.trim() ?? '';
    const money = (selector) => canonicalOf(form.querySelector(selector))?.value ?? '';
    const checked = (selector) => form.querySelector(selector)?.checked ?? false;
    const appliesTo = form.querySelector(`${APPLIES_TO}:checked`)?.value;
    const picks = pickerOf(form, alpine)?.data.describe();

    return {
        mode: form.querySelector('input[name="_method"]')?.value?.toUpperCase() === 'PUT' ? 'edit' : 'create',
        kind: value('input[name="scope"]'),
        name: value('#name'),
        code: value('#code'),
        discount_type: value('#discount_type'),
        discount_value: money('#discount_value'),
        max_discount_amount: money('#max_discount_amount'),
        min_order_amount: money('#min_order_amount'),
        usage_limit: value('#usage_limit'),
        usage_limit_per_customer: value('#usage_limit_per_customer'),
        starts_at: value('#starts_at'),
        ends_at: value('#ends_at'),
        is_active: checked(CHECKBOXES.is_active),
        is_auto_apply: checked(CHECKBOXES.is_auto_apply),
        ...(appliesTo ? { applies_to: appliesTo } : {}),
        ...(picks ? { brands: picks.brands, products: picks.products } : {}),
    };
}

/**
 * Writes a discount `set_fields` reply straight into the form — nothing is
 * saved; the staff member reviews it and presses Lưu. A field the form does
 * not have is skipped.
 *
 * @param  Array<{field: string, value?: *, add?: object[], remove?: object[]}>  fields
 * @return {{applied: string[], restore: () => void}}
 */
export function applyDiscountFields(fields, form, alpine) {
    const before = snapshotForm(form, alpine);
    const applied = [];
    let picked = false;
    let setScope = false;

    for (const entry of fields) {
        const { field } = entry;
        let done = false;

        if (field in TEXT_FIELDS) {
            const el = form.querySelector(TEXT_FIELDS[field]);
            if (el) setFieldValue(el, String(entry.value ?? ''));
            done = Boolean(el);
        } else if (field in MONEY_FIELDS) {
            done = setMoneyValue(form.querySelector(MONEY_FIELDS[field]), entry.value);
        } else if (field in CHECKBOXES) {
            const el = form.querySelector(CHECKBOXES[field]);
            if (el) setCheckboxChecked(el, Boolean(entry.value));
            done = Boolean(el);
        } else if (field === 'applies_to') {
            done = setRadio(form, entry.value);
            setScope = setScope || done;
        } else if (field in PICKS) {
            const picker = pickerOf(form, alpine);
            if (picker) {
                picker.data.applyTargets(PICKS[field], entry);
                flashField(picker.root);
                picked = true;
                done = true;
            }
        }

        if (done) applied.push(DISCOUNT_FIELD_LABELS[field] ?? field);
    }

    // Naming brands or products on a voucher means it is limited to them.
    if (picked && !setScope && pickerOf(form, alpine).data.total > 0) setRadio(form, 'selected');

    return { applied, restore: () => restoreForm(before, form, alpine) };
}
