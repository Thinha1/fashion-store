import { createSessionStore, sendAssistTurn } from './ai-chat-transport.js';

const STORAGE_KEY = 'shopping-assist-chat:v1';
const sessionStore = createSessionStore(STORAGE_KEY);

// Shown as one-tap chips before the shopper has typed anything — solves the
// "blank screen, don't know what to ask" problem a free-text-only box has.
// Written as natural requests (not bare category names) since that's what
// the prompt/filter extraction (see ShoppingAssistPromptBuilder) is tuned
// to parse well.
export const QUICK_PROMPTS = [
    'Áo thun nam form rộng',
    'Váy dự tiệc dưới 1 triệu',
    'Đồ mặc nhà mùa hè',
    'Giày sneaker màu trắng',
];

// Shown under the latest assistant reply so refining a search is a tap
// instead of retyping the whole request — the prompt already explicitly
// tells the model to apply this kind of follow-up onto the filter it
// already inferred (see ShoppingAssistPromptBuilder), so these read exactly
// like the examples given there.
export const REFINE_PROMPTS = [
    'Rẻ hơn nữa xem',
    'Còn màu khác không?',
    'Xem thêm lựa chọn khác',
];

// Mirrors the server's own `services.ai.shopping_assist_max_history_messages`
// default (see config/services.php) — trimming client-side before hitting
// that hard limit avoids ever bouncing a turn with a validation error.
const MAX_MESSAGES = 20;

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

// Re-exported for backward compatibility — existing tests import it from
// this module; the implementation now lives in ai-chat-transport.js since
// it's shared verbatim with the admin widget.
export { parseSseFrames } from './ai-chat-transport.js';

// Ordered so the status line reads as a natural sentence of what's been
// figured out so far — checked against the raw JSON text streamed in so
// far (see describeStreamProgress), not the parsed filter (which doesn't
// exist until the stream ends).
const STREAM_PROGRESS_STEPS = [
    { pattern: /"category"\s*:\s*"[^"]+"/, label: 'đã chọn danh mục' },
    { pattern: /"price_(?:min|max)"\s*:\s*"[^"]+"/, label: 'đang tính mức giá' },
    { pattern: /"(?:sizes|colors)"\s*:\s*\[[^\]]+\]/, label: 'đang lọc size/màu' },
    { pattern: /"keywords"\s*:\s*\[[^\]]+\]/, label: 'đang tìm từ khóa phù hợp' },
];

/**
 * Turns the raw JSON text accumulated so far from the stream into a short
 * Vietnamese status line — never the raw JSON itself (the model's whole
 * reply IS the JSON filter object, so showing it live would just flash
 * broken JSON at the shopper).
 */
export function describeStreamProgress(rawSoFar) {
    if (!rawSoFar) return null;
    const seen = STREAM_PROGRESS_STEPS.filter(({ pattern }) => pattern.test(rawSoFar));

    return seen.length ? seen.map(({ label }) => label).join(', ') + '…' : null;
}

export default (assistUrl, assistStreamUrl) => ({
    open: false,
    messages: [],
    input: '',
    loading: false,
    error: '',
    requestId: 0,
    quickPrompts: QUICK_PROMPTS,
    refinePrompts: REFINE_PROMPTS,
    // Short Vietnamese status line updated live while a streamed reply is
    // still coming in — never populated on the classic non-streaming path.
    streamStatus: '',
    // The product the shopper is currently looking at, if any (see the
    // `data-current-product-id` attribute in layouts/app.blade.php) — sent
    // once per turn so the AI can reference it (e.g. "so với áo bạn đang
    // xem..."). Populated in init() (reading document.body); stays null in
    // any test/usage that skips init(), which is a harmless no-op value.
    contextProductId: null,

    init() {
        const saved = sessionStore.load();
        if (saved) {
            this.open = saved.open ?? false;
            this.messages = saved.messages ?? [];
        }
        const rawId = document.body?.dataset?.currentProductId;
        this.contextProductId = rawId ? Number(rawId) : null;

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
        sessionStore.save({ open: this.open, messages: this.messages });
    },

    startNewConversation() {
        this.messages = [];
        this.input = '';
        this.error = '';
        this.streamStatus = '';
    },

    toggle() {
        this.open = !this.open;
    },

    /** A quick-reply/refine chip is a one-tap shortcut — fills and sends immediately rather than just prefilling the box. */
    sendQuickReply(text) {
        this.input = text;

        return this.send();
    },

    async send() {
        if (this.loading) return;
        const text = this.input.trim();
        if (!text) return;

        this.messages.push({ role: 'user', text });
        this.messages = trimHistory(this.messages);
        this.input = '';
        this.error = '';
        this.streamStatus = '';
        this.loading = true;
        this.requestId++;
        const requestId = this.requestId;
        const body = JSON.stringify({ messages: toWireMessages(this.messages), context_product_id: this.contextProductId });

        await sendAssistTurn({
            jsonUrl: assistUrl,
            streamUrl: assistStreamUrl,
            body,
            requestId,
            isCurrent: (id) => id === this.requestId,
            rateLimitMessage: 'Bạn thao tác quá nhanh. Vui lòng thử lại sau một phút.',
            failureMessage: 'Chưa thể gợi ý lúc này. Vui lòng thử lại.',
            connectionErrorMessage: 'Không kết nối được tới trợ lý. Vui lòng thử lại.',
            onDeltaText: (rawSoFar) => { this.streamStatus = describeStreamProgress(rawSoFar) ?? ''; },
            onPayload: (payload) => this.applyAssistPayload(payload),
            setError: (message) => { this.error = message; },
            setLoading: (loading) => { this.loading = loading; },
            setStreamStatus: (status) => { this.streamStatus = status; },
        });
    },

    /** Applies a resolved `{reply, products, raw}` payload — shared by the JSON response and the stream's "done" event. */
    applyAssistPayload(payload) {
        this.messages.push({
            role: 'assistant',
            reply: typeof payload?.reply === 'string' ? payload.reply : '',
            raw: typeof payload?.raw === 'string' ? payload.raw : '',
            products: Array.isArray(payload?.products) ? payload.products : [],
        });
    },
});
