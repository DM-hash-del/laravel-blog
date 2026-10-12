/**
 * Shared JSON requests for the admin pages.
 *
 * Sends the CSRF token from <meta name="csrf-token"> and asks for JSON. A non-2xx
 * reply throws an HttpError with its status, parsed body and Retry-After seconds.
 * Pass an AbortSignal to cancel; a cancelled request rejects with an AbortError.
 */
export class HttpError extends Error {
    constructor(status, data, retryAfter) {
        super(`Request failed with status ${status}`);
        this.name = 'HttpError';
        this.status = status;
        this.data = data;
        this.retryAfter = retryAfter;
    }
}

export function isAbortError(error) {
    return error instanceof DOMException && error.name === 'AbortError';
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function send(url, body, extraHeaders, signal) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
            ...extraHeaders,
        },
        body,
        credentials: 'same-origin',
        signal,
    });

    const data = await response.json().catch(() => null);

    if (!response.ok) {
        const retryAfter = Number.parseInt(response.headers.get('Retry-After') ?? '', 10);

        throw new HttpError(response.status, data, Number.isNaN(retryAfter) ? null : retryAfter);
    }

    return data;
}

/**
 * Send data as JSON.
 */
export function postJson(url, data, { signal } = {}) {
    return send(url, JSON.stringify(data), { 'Content-Type': 'application/json' }, signal);
}

/**
 * Send a whole form's data (including _method and array fields) exactly as a normal submit would.
 */
export function postForm(url, formData, { signal } = {}) {
    return send(url, formData, {}, signal);
}
