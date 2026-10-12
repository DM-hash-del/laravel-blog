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

export async function postJson(url, data, { signal } = {}) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify(data),
        credentials: 'same-origin',
        signal,
    });

    const body = await response.json().catch(() => null);

    if (!response.ok) {
        const retryAfter = Number.parseInt(response.headers.get('Retry-After') ?? '', 10);

        throw new HttpError(response.status, body, Number.isNaN(retryAfter) ? null : retryAfter);
    }

    return body;
}
