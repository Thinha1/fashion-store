const STORAGE_KEY = 'shopping-assist-chat:v1';

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

/**
 * Splits a raw SSE byte buffer (as accumulated so far from a fetch stream
 * reader) into complete frames plus whatever incomplete tail remains for
 * the next chunk. Each frame looks like `event: <name>\ndata: <json>`,
 * frames separated by a blank line (see
 * `App\Http\Controllers\Storefront\ProductAssistStreamController::emit`).
 * Pure and DOM-free so it's unit-testable on its own.
 *
 * @return {{frames: Array<{event: string, data: unknown}>, remainder: string}}
 */
export function parseSseFrames(buffer) {
    const parts = buffer.split('\n\n');
    const remainder = parts.pop() ?? '';
    const frames = [];
    for (const part of parts) {
        if (!part.trim()) continue;
        const eventMatch = part.match(/^event: (.+)$/m);
        const dataMatch = part.match(/^data: (.+)$/m);
        if (!eventMatch || !dataMatch) continue;
        try {
            frames.push({ event: eventMatch[1], data: JSON.parse(dataMatch[1]) });
        } catch {
            // A malformed frame is skipped rather than crashing the whole stream.
        }
    }

    return { frames, remainder };
}

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
        const saved = loadPersistedState();
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
        persistState({ open: this.open, messages: this.messages });
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

        try {
            if (assistStreamUrl) {
                try {
                    await this.sendStreamed(assistStreamUrl, body, requestId);

                    return;
                } catch {
                    // Streaming failed at the transport level (unsupported
                    // browser, a buffering proxy, a drop mid-stream) — an
                    // app-level error/response returns normally instead (see
                    // `sendStreamed`), so this never masks a real server
                    // error, only falls back once to the plain JSON call.
                    if (requestId !== this.requestId) return;
                }
            }
            await this.sendJson(assistUrl, body, requestId);
        } catch {
            if (requestId === this.requestId) this.error = 'Không kết nối được tới trợ lý. Vui lòng thử lại.';
        } finally {
            if (requestId === this.requestId) {
                this.loading = false;
                this.streamStatus = '';
            }
        }
    },

    /** The classic request/response call — also the fallback when streaming itself can't be used. */
    async sendJson(url, body, requestId) {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body,
        });
        const payload = await response.json().catch(() => null);
        if (requestId !== this.requestId) return;

        if (!response.ok) {
            this.error = response.status === 429
                ? 'Bạn thao tác quá nhanh. Vui lòng thử lại sau một phút.'
                : payload?.message || 'Chưa thể gợi ý lúc này. Vui lòng thử lại.';
            return;
        }

        this.applyAssistPayload(payload);
    },

    /**
     * Reads the SSE stream from ProductAssistStreamController: `delta`
     * events update `streamStatus` live, a `done` event carries the exact
     * same `{reply, products, raw}` shape `sendJson` gets and is handled
     * the same way (`applyAssistPayload`), and an `error` event surfaces
     * like any other app-level failure.
     */
    async sendStreamed(url, body, requestId) {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'text/event-stream',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body,
        });
        if (requestId !== this.requestId) return;

        if (!response.ok) {
            this.error = response.status === 429
                ? 'Bạn thao tác quá nhanh. Vui lòng thử lại sau một phút.'
                : 'Chưa thể gợi ý lúc này. Vui lòng thử lại.';
            return;
        }
        if (!response.body?.getReader) throw new Error('Streaming is not supported in this browser.');

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';
        let rawSoFar = '';

        while (true) {
            const { done, value } = await reader.read();
            if (requestId !== this.requestId) return;
            if (done) break;

            buffer += decoder.decode(value, { stream: true });
            const parsed = parseSseFrames(buffer);
            buffer = parsed.remainder;

            for (const frame of parsed.frames) {
                if (frame.event === 'delta' && typeof frame.data?.text === 'string') {
                    rawSoFar += frame.data.text;
                    this.streamStatus = describeStreamProgress(rawSoFar) ?? '';
                } else if (frame.event === 'done') {
                    this.applyAssistPayload(frame.data);

                    return;
                } else if (frame.event === 'error') {
                    this.error = frame.data?.message || 'Chưa thể gợi ý lúc này. Vui lòng thử lại.';

                    return;
                }
            }
        }
        // The stream ended (connection closed) without ever sending a
        // "done"/"error" event — a transport-level failure, so this throws
        // to trigger the `sendJson` fallback in `send()`.
        throw new Error('Stream ended without a result.');
    },

    /** Applies a resolved `{reply, products, raw}` payload — shared by `sendJson` and `sendStreamed`'s "done" event. */
    applyAssistPayload(payload) {
        this.messages.push({
            role: 'assistant',
            reply: typeof payload?.reply === 'string' ? payload.reply : '',
            raw: typeof payload?.raw === 'string' ? payload.raw : '',
            products: Array.isArray(payload?.products) ? payload.products : [],
        });
    },
});
