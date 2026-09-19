/**
 * Shared plumbing between the admin (`product-ai-chat.js`) and storefront
 * (`shopping-assist-chat.js`) AI chat widgets: session persistence, SSE
 * frame parsing, and the "POST JSON" / "read SSE stream, fall back to JSON"
 * request bodies. Each widget owns its own state shape and what a resolved
 * payload means — this only factors out the parts that are byte-for-byte
 * identical between them.
 */

/**
 * This is a multi-page (server-rendered) app — every navigation is a full
 * reload that would otherwise wipe a widget's conversation. sessionStorage
 * survives navigation within the same browser tab (and is cleared when the
 * tab closes), so a conversation can carry on from one page to the next.
 */
export function createSessionStore(key) {
    return {
        load() {
            try {
                const raw = sessionStorage.getItem(key);

                return raw ? JSON.parse(raw) : null;
            } catch {
                return null;
            }
        },
        save(state) {
            try {
                sessionStorage.setItem(key, JSON.stringify(state));
            } catch {
                // Storage full or unavailable (e.g. private browsing) — state just won't survive navigation.
            }
        },
    };
}

/**
 * Splits a raw SSE byte buffer (as accumulated so far from a fetch stream
 * reader) into complete frames plus whatever incomplete tail remains for the
 * next chunk. Each frame looks like `event: <name>\ndata: <json>`, frames
 * separated by a blank line (see the `emit()` helper shared by the admin and
 * storefront stream controllers). Pure and DOM-free so it's unit-testable on
 * its own.
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

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

/**
 * POSTs `body` as JSON and hands the parsed response to the caller — the
 * classic request/response call, and the fallback when streaming itself
 * can't be used. `isCurrent(requestId)` guards against a slower, superseded
 * request clobbering state after a newer one already resolved (both widgets
 * key this off their own incrementing `requestId`).
 *
 * @param {{requestId: number, isCurrent: (id: number) => boolean, rateLimitMessage: string, failureMessage: string, onPayload: (payload: unknown) => void, setError: (message: string) => void}} handlers
 */
export async function requestJson(url, body, { requestId, isCurrent, rateLimitMessage, failureMessage, onPayload, setError }) {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body,
    });
    const payload = await response.json().catch(() => null);
    if (!isCurrent(requestId)) return;

    if (!response.ok) {
        setError(response.status === 429 ? rateLimitMessage : (payload?.message || failureMessage));
        return;
    }

    onPayload(payload);
}

/**
 * Reads an SSE stream frame by frame: each `delta` frame's accumulated raw
 * text so far is handed to `onDeltaText` (for a live status line), a `done`
 * frame is handed to `onDone` and ends the read normally, and an `error`
 * frame calls `setError` and also ends normally. If the connection drops
 * before either a `done` or an `error` frame ever arrives, this throws — a
 * transport-level failure distinct from an app-level one — so the caller can
 * fall back to `requestJson` the same way both widgets already do.
 *
 * @param {{requestId: number, isCurrent: (id: number) => boolean, rateLimitMessage: string, failureMessage: string, onDeltaText: (rawSoFar: string) => void, onDone: (payload: unknown) => void, setError: (message: string) => void}} handlers
 */
export async function requestStream(url, body, {
    requestId, isCurrent, rateLimitMessage, failureMessage, onDeltaText, onDone, setError,
}) {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'text/event-stream',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body,
    });
    if (!isCurrent(requestId)) return;

    if (!response.ok) {
        setError(response.status === 429 ? rateLimitMessage : failureMessage);
        return;
    }
    if (!response.body?.getReader) throw new Error('Streaming is not supported in this browser.');

    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';
    let rawSoFar = '';

    while (true) {
        const { done, value } = await reader.read();
        if (!isCurrent(requestId)) return;
        if (done) break;

        buffer += decoder.decode(value, { stream: true });
        const parsed = parseSseFrames(buffer);
        buffer = parsed.remainder;

        for (const frame of parsed.frames) {
            if (frame.event === 'delta' && typeof frame.data?.text === 'string') {
                rawSoFar += frame.data.text;
                onDeltaText(rawSoFar);
            } else if (frame.event === 'done') {
                onDone(frame.data);

                return;
            } else if (frame.event === 'error') {
                setError(frame.data?.message || failureMessage);

                return;
            }
        }
    }
    // The stream ended (connection closed) without ever sending a
    // "done"/"error" event — a transport-level failure, so this throws to
    // trigger the `requestJson` fallback in the caller's `send()`.
    throw new Error('Stream ended without a result.');
}

/**
 * Orchestrates one full assist turn for both widgets' `send()`: try SSE
 * streaming first when a stream URL is configured, falling back once to the
 * plain JSON endpoint only on a transport-level failure — `done`/`error`
 * frames from `requestStream` already resolve normally and never reach this
 * fallback (see `requestStream`). Also owns the turn's loading/error/status
 * bookkeeping via the passed setters, so neither widget has to repeat the
 * try/catch/finally dance itself.
 *
 * @param {{jsonUrl: string, streamUrl?: string|null, body: string, requestId: number, isCurrent: (id: number) => boolean, rateLimitMessage: string, failureMessage: string, connectionErrorMessage: string, onDeltaText: (rawSoFar: string) => void, onPayload: (payload: unknown) => void, setError: (message: string) => void, setLoading: (loading: boolean) => void, setStreamStatus: (status: string) => void}} options
 */
export async function sendAssistTurn({
    jsonUrl, streamUrl, body, requestId, isCurrent, rateLimitMessage, failureMessage, connectionErrorMessage,
    onDeltaText, onPayload, setError, setLoading, setStreamStatus,
}) {
    try {
        if (streamUrl) {
            try {
                await requestStream(streamUrl, body, {
                    requestId, isCurrent, rateLimitMessage, failureMessage, onDeltaText, onDone: onPayload, setError,
                });

                return;
            } catch {
                if (!isCurrent(requestId)) return;
            }
        }
        await requestJson(jsonUrl, body, {
            requestId, isCurrent, rateLimitMessage, failureMessage, onPayload, setError,
        });
    } catch {
        if (isCurrent(requestId)) setError(connectionErrorMessage);
    } finally {
        if (isCurrent(requestId)) {
            setLoading(false);
            setStreamStatus('');
        }
    }
}
