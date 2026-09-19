const STORAGE_KEY = 'shopping-assist-chat:v1';

// Mirrors the server's own `services.ai.shopping_assist_max_history_messages`
// default (see config/services.php) — trimming client-side before hitting
// that hard limit avoids ever bouncing a turn with a validation error.
const MAX_MESSAGES = 20;

/**
 * This is a normal server-rendered, multi-page storefront — sessionStorage
 * survives navigation within the same tab, so a shopper can keep chatting
 * while browsing from page to page (same idea as the admin widget's own
 * persistence).
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

/**
 * Keeps the conversation from growing without bound. Unlike the admin
 * widget's `compactMessages`, there are no photos to preserve and no draft
 * worth summarizing — this domain's turns are short filter requests, so a
 * plain "keep the most recent N" is enough.
 */
export function trimHistory(messages, max = MAX_MESSAGES) {
    return messages.length > max ? messages.slice(messages.length - max) : messages;
}

/**
 * The AI has no memory between calls, so every request resends the full
 * conversation. An assistant turn resends the model's own raw JSON reply
 * (`raw`), not the human-friendly `reply` text shown in the bubble — the
 * next turn needs to see exactly what filter it committed to before, the
 * same way the admin widget resends its own `raw` model output.
 */
export function toWireMessages(messages) {
    return messages.map((message) => ({
        role: message.role,
        content: message.role === 'assistant' ? (message.raw ?? '') : message.text,
    }));
}

export default (assistUrl) => ({
    open: false,
    messages: [],
    input: '',
    loading: false,
    error: '',
    requestId: 0,

    init() {
        const saved = loadPersistedState();
        if (saved) {
            this.open = saved.open ?? false;
            this.messages = saved.messages ?? [];
        }
        this.$watch('open', () => this.persist());
        this.$watch('messages', () => this.persist());
        this.$watch('messages', () => this.scrollToBottom());
        this.$watch('loading', () => this.scrollToBottom());
        // $watch only fires on FUTURE changes, not the restore above — so a
        // conversation reopened after navigating would otherwise render
        // scrolled to the top.
        this.scrollToBottom();
    },

    scrollToBottom() {
        this.$nextTick(() => {
            const list = this.$refs.messageList;
            if (list) list.scrollTop = list.scrollHeight;
        });
    },

    persist() {
        persistState({ open: this.open, messages: this.messages });
    },

    startNewConversation() {
        this.messages = [];
        this.input = '';
        this.error = '';
    },

    toggle() {
        this.open = !this.open;
    },

    async send() {
        if (this.loading) return;
        const text = this.input.trim();
        if (!text) return;

        this.messages.push({ role: 'user', text });
        this.messages = trimHistory(this.messages);
        this.input = '';
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
                    : payload?.message || 'Chưa thể gợi ý lúc này. Vui lòng thử lại.';
                return;
            }

            this.messages.push({
                role: 'assistant',
                reply: typeof payload?.reply === 'string' ? payload.reply : '',
                raw: typeof payload?.raw === 'string' ? payload.raw : '',
                products: Array.isArray(payload?.products) ? payload.products : [],
            });
        } catch {
            if (requestId === this.requestId) this.error = 'Không kết nối được tới trợ lý. Vui lòng thử lại.';
        } finally {
            if (requestId === this.requestId) this.loading = false;
        }
    },
});
