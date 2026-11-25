export class ApiError extends Error {
    constructor(message, status, errors = {}) {
        super(message);
        this.status = status;
        this.errors = errors;
    }
}

export async function api(path, options = {}) {
    const headers = new Headers(options.headers || {});
    headers.set('Accept', 'application/json');
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    if (token && !['GET', 'HEAD'].includes((options.method || 'GET').toUpperCase())) headers.set('X-CSRF-Token', token);
    if (options.body && !(options.body instanceof FormData) && typeof options.body !== 'string') {
        headers.set('Content-Type', 'application/json');
        options.body = JSON.stringify(options.body);
    }
    const response = await fetch(path, {credentials: 'same-origin', ...options, headers});
    const payload = await response.json().catch(() => ({success: false, message: 'The server returned an unreadable response.'}));
    if (!response.ok || !payload.success) throw new ApiError(payload.message || 'Request failed.', response.status, payload.errors || {});
    if (response.headers.has('X-CrewWeaver-Changed')) document.dispatchEvent(new Event('cw:mutation'));
    return payload.data;
}

export function toast(message, type = 'success') {
    const container = document.querySelector('[data-toast-container]');
    if (!container) return;
    const element = document.createElement('div');
    element.className = `toast align-items-center text-bg-${type} border-0`;
    element.setAttribute('role', 'status');
    element.innerHTML = '<div class="d-flex"><div class="toast-body"></div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div>';
    element.querySelector('.toast-body').textContent = message;
    container.append(element);
    const instance = bootstrap.Toast.getOrCreateInstance(element, {delay: 4000});
    element.addEventListener('hidden.bs.toast', () => element.remove());
    instance.show();
}

export function debounce(callback, delay = 250) {
    let timer;
    return (...args) => { clearTimeout(timer); timer = setTimeout(() => callback(...args), delay); };
}
