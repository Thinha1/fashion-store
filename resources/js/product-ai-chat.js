const MAX_VARIANT_ROWS = 12;
const MAX_IMAGES_PER_SELECTION = 6;

function toBase64(dataUrl) {
    const commaIndex = dataUrl.indexOf(',');

    return commaIndex === -1 ? dataUrl : dataUrl.slice(commaIndex + 1);
}

function dataUrlToFile(dataUrl, filename) {
    const [header, base64] = dataUrl.split(',');
    const mediaType = header.match(/data:(.*);base64/)?.[1] ?? 'image/jpeg';
    const binary = atob(base64);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);

    return new File([bytes], filename, { type: mediaType });
}

/**
 * Sets a form field's value through its native setter (not just `.value =`)
 * so frameworks/plugins hooking the native input event — Alpine's x-model,
 * the currency mask plugin — see the change, then fires input+change for
 * anything listening the plain way. Works whether the field belongs to a
 * plain PHP-rendered form or one later rewritten with a JS framework.
 */
function setFieldValue(el, value) {
    if (!el) return;
    const proto = el.tagName === 'TEXTAREA' ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype;
    Object.getOwnPropertyDescriptor(proto, 'value').set.call(el, value);
    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));
}

/**
 * The AI returns price as a free-form string ("350000", "350.000đ", "350k"...).
 * Extracts the amount as a plain integer of VND (this shop has no cents), or
 * null when nothing numeric could be found.
 */
export function parsePriceToInteger(text) {
    const trimmed = String(text ?? '').trim().toLowerCase();
    if (!trimmed) return null;

    const shorthand = trimmed.match(/^([\d.,]+)\s*k$/);
    if (shorthand) {
        const amount = parseFloat(shorthand[1].replace(',', '.'));

        return Number.isFinite(amount) ? Math.round(amount * 1000) : null;
    }

    const digits = trimmed.replace(/\D/g, '');

    return digits ? parseInt(digits, 10) : null;
}

function groupThousands(amount) {
    return String(amount).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

/**
 * The base-price field isn't a plain input — it's the `<x-currency-input>`
 * component. Its visible field is driven by an input-masking plugin built to
 * diff one keystroke at a time; feeding it a whole value through a fired
 * `input` event mangles it (confirmed against the real component: it turns
 * "199000" into garbage like ",2"). Writing straight into the component's
 * Alpine `display` state instead doesn't help either — Alpine only re-syncs
 * the DOM from that state on the next reactive tick, and nothing in this
 * flow triggers one.
 *
 * So this bypasses both: it writes the DOM values directly — the visible
 * field for the staff member to see, and the hidden "canonical" input for
 * what actually gets submitted (its `name` was moved there by the
 * component's own init(), exactly like the visible field carries no name
 * once Alpine has booted).
 */
function setCurrencyFieldValue(input, priceText) {
    if (!input) return;
    const amount = parsePriceToInteger(priceText);
    if (amount === null) return;

    // No dispatched events here on purpose (see comment above) — both the
    // mask plugin and Alpine's own reactive re-render would fight this
    // direct write. If the staff member edits the field afterwards, typing
    // reads the live DOM value, so it self-corrects from there.
    input.value = groupThousands(amount);
    const canonical = input.closest('.currency-input')?.querySelector('input[type="hidden"]');
    if (canonical) canonical.value = String(amount);
}

function normalizeLabel(text) {
    return String(text ?? '').trim().toLowerCase();
}

/**
 * `#category_id` is a plain `<select>` — matching its option by visible text
 * and firing `change` is all a real click would do.
 */
function selectPlainOptionByLabel(select, label) {
    if (!select || !label) return;
    const target = normalizeLabel(label);
    const match = [...select.options].find((option) => normalizeLabel(option.textContent) === target);
    if (!match) return;
    select.value = match.value;
    select.dispatchEvent(new Event('change', { bubbles: true }));
}

/**
 * The brand field is the `<x-image-select>` component: a hidden native
 * `<select>` plus a custom Alpine-driven listbox. Its own `choose(index)`
 * method already does the right thing (updates its state, the native
 * select, and fires `change`) — reuse it instead of reimplementing it.
 */
function selectImageOptionByLabel(nativeSelect, label, alpine) {
    if (!nativeSelect || !label) return;
    const wrapper = nativeSelect.closest('.image-select');
    if (!wrapper) return;
    const component = alpine.$data(wrapper);
    const target = normalizeLabel(label);
    const index = component.options.findIndex((option) => normalizeLabel(option.label) === target);
    if (index === -1) return;
    component.choose(index);
}

/** Moves a photo from the chat into a file input via DataTransfer, firing `change` so any preview listener picks it up. */
function assignImageFile(input, dataUrl, filename) {
    if (!input || !dataUrl) return;
    const transfer = new DataTransfer();
    transfer.items.add(dataUrlToFile(dataUrl, filename));
    input.files = transfer.files;
    input.dispatchEvent(new Event('change', { bubbles: true }));
}

/**
 * Every image attached during the conversation carries a stable `imageIndex`
 * (assigned once, when the staff member picks the file — see `onFileChange`)
 * matching the "Ảnh số N:" label sent to the AI alongside it. This walks the
 * whole conversation and lays every image out by that index, so the fill
 * step can look photos up the same way the AI referenced them. No DOM
 * access, so it's unit-testable on its own.
 */
export function collectImageGallery(messages) {
    const gallery = [];
    for (const message of messages) {
        for (const block of message.blocks) {
            if (block.type === 'image' && typeof block.imageIndex === 'number') {
                gallery[block.imageIndex] = block.dataUrl;
            }
        }
    }

    return gallery;
}

/**
 * Pure planning step: turns a parsed AI draft into the flat list of writes
 * the real form needs. No DOM access, so this is unit-testable on its own —
 * the product form has no dedicated bullets/SEO-title fields, so those are
 * folded into the description text instead of being dropped.
 */
export function buildFillPlan(draft) {
    const bullets = Array.isArray(draft.bullets) ? draft.bullets.filter(Boolean) : [];
    const seoTitle = draft.seo_title?.trim() ?? '';
    const description = [
        draft.description?.trim() ?? '',
        bullets.length ? bullets.map((bullet) => `• ${bullet}`).join('\n') : '',
        seoTitle ? `SEO: ${seoTitle}` : '',
    ].filter(Boolean).join('\n\n');

    const sizes = Array.isArray(draft.sizes) ? draft.sizes.filter(Boolean) : [];
    const colors = Array.isArray(draft.colors) ? draft.colors.filter(Boolean) : [];

    const imageIndexByColor = new Map();
    if (Array.isArray(draft.variant_images)) {
        for (const entry of draft.variant_images) {
            const color = normalizeLabel(entry?.color);
            if (color && Number.isInteger(entry?.image_index)) imageIndexByColor.set(color, entry.image_index);
        }
    }

    const variantRows = [];
    if (sizes.length && colors.length) {
        outer: for (const size of sizes) {
            for (const color of colors) {
                if (variantRows.length >= MAX_VARIANT_ROWS) break outer;
                variantRows.push({ size, color, imageIndex: imageIndexByColor.get(normalizeLabel(color)) });
            }
        }
    } else if (sizes.length) {
        for (const size of sizes.slice(0, MAX_VARIANT_ROWS)) variantRows.push({ size, color: '', imageIndex: undefined });
    }

    return {
        name: draft.name?.trim() ?? '',
        description,
        price: draft.price?.trim() ?? '',
        category: draft.category?.trim() ?? '',
        brand: draft.brand?.trim() ?? '',
        variantRows,
    };
}

/**
 * Applies a fill plan to the real product form. Never submits the form —
 * the staff member reviews and submits it themselves. `gallery` (see
 * `collectImageGallery`) supplies the photos: index 0 always goes to the
 * shared "general photos" input, and any other index referenced by a
 * variant row goes to that row's own per-variant photo input.
 */
export function applyFillPlan(plan, form, alpine, gallery = []) {
    if (plan.name) setFieldValue(form.querySelector('#name'), plan.name);
    if (plan.description) setFieldValue(form.querySelector('#description'), plan.description);
    if (plan.price) setCurrencyFieldValue(form.querySelector('#base_price'), plan.price);
    if (plan.category) selectPlainOptionByLabel(form.querySelector('#category_id'), plan.category);
    if (plan.brand) selectImageOptionByLabel(form.querySelector('#brand_id-native'), plan.brand, alpine);
    if (gallery[0]) assignImageFile(form.querySelector('#images'), gallery[0], 'ai-chat-anh-0.jpg');

    if (!plan.variantRows.length) return;
    const component = alpine.$data(form);
    const list = form.querySelector('#variants-list');
    for (const { size, color, imageIndex } of plan.variantRows) {
        component.addVariant();
        const row = list?.lastElementChild;
        if (!row) continue;
        const sizeSelect = row.querySelector('select[name$="[size]"]');
        const colorInput = row.querySelector('input[name$="[color]"]');
        if (sizeSelect) {
            sizeSelect.value = size;
            sizeSelect.dispatchEvent(new Event('change', { bubbles: true }));
        }
        if (color) setFieldValue(colorInput, color);
        if (typeof imageIndex === 'number' && gallery[imageIndex]) {
            assignImageFile(row.querySelector('input[type="file"]'), gallery[imageIndex], `ai-chat-anh-${imageIndex}.jpg`);
        }
    }
}

const STORAGE_KEY = 'product-ai-chat:v1';

/**
 * This is a multi-page (server-rendered) app — every navigation between
 * admin pages is a full reload that would otherwise wipe the widget's
 * conversation. sessionStorage survives navigation within the same browser
 * tab (and is cleared when the tab closes), so staff can start a chat on
 * one page and finish filling the form on another without losing it.
 */
function loadPersistedState() {
    try {
        const raw = sessionStorage.getItem(STORAGE_KEY);

        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
}

function persistState(state) {
    try {
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify(state));
    } catch {
        // Storage full or unavailable (e.g. private browsing) — the conversation just won't survive navigation.
    }
}

function toContentBlocks(blocks) {
    return blocks.map((block) => (block.type === 'image'
        ? { type: 'image', media_type: block.mediaType, data: block.data }
        : { type: 'text', text: block.text }));
}

/**
 * The AI has no memory between calls, so every request resends the full
 * conversation. Anthropic's Messages API additionally requires strictly
 * alternating roles, so consecutive same-role turns (e.g. a failed attempt
 * followed by a retry) are merged into one before sending.
 */
export function toWireMessages(messages) {
    const merged = [];
    for (const { role, blocks } of messages) {
        const previous = merged.at(-1);
        if (previous && previous.role === role) {
            previous.content.push(...toContentBlocks(blocks));
        } else {
            merged.push({ role, content: toContentBlocks(blocks) });
        }
    }

    return merged;
}

export default (assistUrl, productCreateUrl) => ({
    open: false,
    messages: [],
    input: '',
    attachedImages: [],
    loading: false,
    error: '',
    draft: null,
    // { key, label, url } resolved server-side against the fixed page
    // directory — never a raw model-provided URL, see AdminPageDirectory.
    navigate: null,
    requestId: 0,
    // Assigned once per attached photo, for the whole life of the
    // conversation — this is the "Ảnh số N" the AI is told to reference.
    imageCounter: 0,
    // Set right before navigating away to go find a product form; consumed
    // once the destination page has loaded, so the fill survives the reload.
    pendingFill: false,

    init() {
        const saved = loadPersistedState();
        if (saved) {
            this.open = saved.open ?? false;
            this.messages = saved.messages ?? [];
            this.draft = saved.draft ?? null;
            this.navigate = saved.navigate ?? null;
            this.pendingFill = saved.pendingFill ?? false;
            this.imageCounter = saved.imageCounter ?? 0;
        }
        this.$watch('open', () => this.persist());
        this.$watch('messages', () => this.persist());
        this.$watch('draft', () => this.persist());
        this.$watch('navigate', () => this.persist());
        // New message, draft card, navigate card, or the "thinking" bubble
        // appearing should never be hidden below the fold.
        this.$watch('messages', () => this.scrollToBottom());
        this.$watch('draft', () => this.scrollToBottom());
        this.$watch('navigate', () => this.scrollToBottom());
        this.$watch('loading', () => this.scrollToBottom());

        if (this.pendingFill) {
            const form = document.getElementById('product-form');
            if (form) {
                this.open = true;
                this.applyDraftToForm(form);
            }
        }
    },

    scrollToBottom() {
        this.$nextTick(() => {
            const list = this.$refs.messageList;
            if (list) list.scrollTop = list.scrollHeight;
        });
    },

    persist() {
        persistState({
            open: this.open, messages: this.messages, draft: this.draft, navigate: this.navigate,
            pendingFill: this.pendingFill, imageCounter: this.imageCounter,
        });
    },

    startNewConversation() {
        this.messages = [];
        this.draft = null;
        this.navigate = null;
        this.error = '';
        this.attachedImages = [];
        this.input = '';
        this.pendingFill = false;
        this.imageCounter = 0;
    },

    toggle() {
        this.open = !this.open;
    },

    onFileChange(event) {
        const files = [...(event.target.files ?? [])].filter((file) => file.type.startsWith('image/'));
        event.target.value = '';
        if (!files.length) {
            this.error = 'Vui lòng chọn ít nhất một file ảnh.';
            return;
        }
        if (files.length > MAX_IMAGES_PER_SELECTION) {
            this.error = `Chỉ chọn tối đa ${MAX_IMAGES_PER_SELECTION} ảnh mỗi lần.`;
            return;
        }
        this.error = '';
        for (const file of files) {
            const imageIndex = this.imageCounter++;
            const reader = new FileReader();
            reader.onload = () => {
                this.attachedImages.push({
                    dataUrl: reader.result, mediaType: file.type, data: toBase64(reader.result), name: file.name, imageIndex,
                });
            };
            reader.readAsDataURL(file);
        }
    },

    removeAttachedImage(index) {
        this.attachedImages.splice(index, 1);
    },

    async send() {
        if (this.loading) return;
        const text = this.input.trim();
        if (!text && !this.attachedImages.length) return;

        const blocks = [];
        for (const image of this.attachedImages) {
            // Labels the image with the exact index the AI is told to use in "variant_images".
            blocks.push({ type: 'text', text: `Ảnh số ${image.imageIndex}:` });
            blocks.push({
                type: 'image', mediaType: image.mediaType, data: image.data, dataUrl: image.dataUrl, imageIndex: image.imageIndex,
            });
        }
        if (text) blocks.push({ type: 'text', text });

        this.messages.push({ role: 'user', blocks });
        this.input = '';
        this.attachedImages = [];
        this.error = '';
        this.loading = true;
        this.requestId++;
        const requestId = this.requestId;

        try {
            const response = await fetch(assistUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({ messages: toWireMessages(this.messages) }),
            });
            const payload = await response.json().catch(() => null);
            if (requestId !== this.requestId) return;

            if (!response.ok) {
                this.error = response.status === 429
                    ? 'Bạn thao tác quá nhanh. Vui lòng thử lại sau một phút.'
                    : payload?.message || 'Chưa thể tạo nội dung gợi ý. Vui lòng thử lại.';
                return;
            }

            const draft = payload?.data;
            if (!draft || typeof draft.name !== 'string') {
                this.error = 'Phản hồi từ AI không hợp lệ.';
                return;
            }

            // Only surface the draft card when the AI actually composed a
            // product (non-empty name) — otherwise, e.g. the staff member
            // only asked to navigate somewhere, it would show a useless
            // empty "form" card every turn. Disabled for now on purpose.
            this.draft = draft.name.trim() ? draft : null;
            // Overwrites every turn, including back to null — the prompt
            // tells the model not to repeat "navigate" unless asked again,
            // so a stale suggestion from an earlier turn must not linger.
            this.navigate = payload?.navigate ?? null;
            // Carries the resolved page label onto the message itself so the
            // bubble can say exactly where it went, instead of the generic
            // "updated the draft below" text that doesn't apply here.
            this.messages.push({
                role: 'assistant',
                blocks: [{ type: 'text', text: payload.raw ?? JSON.stringify(draft) }],
                navigateLabel: this.navigate?.label ?? null,
            });

            // Navigating is just a page jump (no data write), so it happens
            // right away instead of waiting for a confirmation click.
            if (this.navigate) {
                this.persist();
                this.goToPage();
            }
        } catch {
            if (requestId === this.requestId) this.error = 'Không kết nối được tới dịch vụ AI. Vui lòng thử lại.';
        } finally {
            if (requestId === this.requestId) this.loading = false;
        }
    },

    /** The URL is already resolved server-side against the fixed page directory — never model-provided. */
    goToPage() {
        if (!this.navigate) return;
        window.location.href = this.navigate.url;
    },

    fillForm() {
        if (!this.draft) return;
        const form = document.getElementById('product-form');
        if (!form) {
            if (!productCreateUrl) {
                this.error = 'Không tìm thấy form thêm/sửa sản phẩm trên trang này.';
                return;
            }
            // No product form on this page — remember the intent, then jump
            // to the "add product" page and finish the fill once it loads.
            this.pendingFill = true;
            this.persist();
            window.location.href = productCreateUrl;
            return;
        }
        this.applyDraftToForm(form);
    },

    applyDraftToForm(form) {
        const gallery = collectImageGallery(this.messages);
        applyFillPlan(buildFillPlan(this.draft), form, window.Alpine, gallery);
        this.pendingFill = false;
    },
});
