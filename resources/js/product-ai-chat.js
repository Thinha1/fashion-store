const MAX_VARIANT_ROWS = 12;

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
    const variantRows = [];
    if (sizes.length && colors.length) {
        outer: for (const size of sizes) {
            for (const color of colors) {
                if (variantRows.length >= MAX_VARIANT_ROWS) break outer;
                variantRows.push({ size, color });
            }
        }
    } else if (sizes.length) {
        for (const size of sizes.slice(0, MAX_VARIANT_ROWS)) variantRows.push({ size, color: '' });
    }

    return { name: draft.name?.trim() ?? '', description, price: draft.price?.trim() ?? '', variantRows };
}

/**
 * Applies a fill plan to the real product form. Never submits the form —
 * the staff member reviews and submits it themselves.
 */
export function applyFillPlan(plan, form, alpine) {
    if (plan.name) setFieldValue(form.querySelector('#name'), plan.name);
    if (plan.description) setFieldValue(form.querySelector('#description'), plan.description);
    if (plan.price) setFieldValue(form.querySelector('#base_price'), plan.price);

    if (!plan.variantRows.length) return;
    const component = alpine.$data(form);
    const list = form.querySelector('#variants-list');
    for (const { size, color } of plan.variantRows) {
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
    }
}

/** Moves the photo attached in chat into the form's shared image input via DataTransfer. */
export function applyImageToForm(form, dataUrl, filename = 'ai-chat-image.jpg') {
    const input = form.querySelector('#images');
    if (!input) return;
    const transfer = new DataTransfer();
    transfer.items.add(dataUrlToFile(dataUrl, filename));
    input.files = transfer.files;
    input.dispatchEvent(new Event('change', { bubbles: true }));
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

export default (assistUrl) => ({
    open: false,
    messages: [],
    input: '',
    attachedImage: null,
    loading: false,
    error: '',
    draft: null,
    requestId: 0,

    init() {
        const saved = loadPersistedState();
        if (saved) {
            this.open = saved.open ?? false;
            this.messages = saved.messages ?? [];
            this.draft = saved.draft ?? null;
        }
        this.$watch('open', () => this.persist());
        this.$watch('messages', () => this.persist());
        this.$watch('draft', () => this.persist());
    },

    persist() {
        persistState({ open: this.open, messages: this.messages, draft: this.draft });
    },

    startNewConversation() {
        this.messages = [];
        this.draft = null;
        this.error = '';
        this.attachedImage = null;
        this.input = '';
    },

    toggle() {
        this.open = !this.open;
    },

    onFileChange(event) {
        const file = event.target.files?.[0];
        event.target.value = '';
        if (!file) return;
        if (!file.type.startsWith('image/')) {
            this.error = 'Vui lòng chọn một file ảnh.';
            return;
        }
        const reader = new FileReader();
        reader.onload = () => {
            this.attachedImage = { dataUrl: reader.result, mediaType: file.type, data: toBase64(reader.result), name: file.name };
        };
        reader.readAsDataURL(file);
    },

    removeAttachedImage() {
        this.attachedImage = null;
    },

    async send() {
        if (this.loading) return;
        const text = this.input.trim();
        if (!text && !this.attachedImage) return;

        const blocks = [];
        if (this.attachedImage) {
            blocks.push({ type: 'image', mediaType: this.attachedImage.mediaType, data: this.attachedImage.data, dataUrl: this.attachedImage.dataUrl });
        }
        if (text) blocks.push({ type: 'text', text });

        this.messages.push({ role: 'user', blocks });
        this.input = '';
        this.attachedImage = null;
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

            this.draft = draft;
            this.messages.push({ role: 'assistant', blocks: [{ type: 'text', text: payload.raw ?? JSON.stringify(draft) }] });
        } catch {
            if (requestId === this.requestId) this.error = 'Không kết nối được tới dịch vụ AI. Vui lòng thử lại.';
        } finally {
            if (requestId === this.requestId) this.loading = false;
        }
    },

    fillForm() {
        if (!this.draft) return;
        const form = document.getElementById('product-form');
        if (!form) {
            this.error = 'Không tìm thấy form thêm/sửa sản phẩm trên trang này.';
            return;
        }
        applyFillPlan(buildFillPlan(this.draft), form, window.Alpine);
        const lastImage = [...this.messages].reverse()
            .flatMap((message) => message.blocks)
            .find((block) => block.type === 'image');
        if (lastImage) applyImageToForm(form, lastImage.dataUrl);
    },
});
