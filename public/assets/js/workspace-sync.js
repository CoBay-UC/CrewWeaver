import {api} from './api.js';

const channel = typeof BroadcastChannel === 'function' ? new BroadcastChannel('crewweaver-workspace') : null;
let revision;
let busy = false;
let queued = false;

async function sync() {
    if (busy) {
        queued = true;
        return;
    }
    busy = true;
    try {
        const next = await api('/api/workspace/revision', {cache: 'no-store'});
        if (next === revision) return;
        const updates = [];
        document.dispatchEvent(new CustomEvent('cw:workspace-change', {
            detail: {waitUntil: promise => updates.push(promise)},
        }));
        await Promise.all(updates);
        revision = next;
    } catch {
        // Retry on the next interval or when the page becomes active.
    } finally {
        busy = false;
        if (queued) {
            queued = false;
            sync();
        }
    }
}

const pageSections = '[data-live-section], [data-board], [data-gantt], [data-project-page]';
let pageRequest;

export function refreshPageSections() {
    if (pageRequest) return pageRequest;
    pageRequest = (async () => {
        const response = await fetch(location.href, {cache: 'no-store'});
        if (response.status === 404 && document.querySelector('[data-project-page]')) {
            location.replace(document.querySelector('[data-project-page]').dataset.projectPage);
            return;
        }
        if (response.status === 403 && document.querySelector('[data-managed-teams]')) {
            location.replace('/teams');
            return;
        }
        if (!response.ok) throw new Error('Could not update the page.');
        const page = new DOMParser().parseFromString(await response.text(), 'text/html');
        if (!page.querySelector('meta[name="csrf-token"]')) return;
        if (page.title) document.title = page.title;
        for (const replacement of page.querySelectorAll('[data-live-section]')) {
            const existing = document.querySelector('[data-live-section="' + CSS.escape(replacement.dataset.liveSection) + '"]');
            if (existing && existing.outerHTML !== replacement.outerHTML) {
                const collapsed = existing.querySelector('.collapse:not(.show)');
                if (collapsed?.id) {
                    const nextCollapse = replacement.querySelector('#' + CSS.escape(collapsed.id));
                    nextCollapse?.classList.remove('show');
                    replacement.querySelector('[aria-controls="' + CSS.escape(collapsed.id) + '"]')?.setAttribute('aria-expanded', 'false');
                }
                const focused = existing.contains(document.activeElement) ? document.activeElement : null;
                const link = focused?.closest('a[href]')?.getAttribute('href');
                existing.replaceWith(replacement);
                if (link) replacement.querySelector('a[href="' + CSS.escape(link) + '"]')?.focus({preventScroll: true});
            }
        }
        document.dispatchEvent(new CustomEvent('cw:page-snapshot', {detail: page}));
    })().finally(() => { pageRequest = null; });
    return pageRequest;
}

if (document.querySelector('meta[name="csrf-token"]') && document.body.dataset.currentUserId) {
    document.addEventListener('cw:workspace-change', event => {
        if (!document.querySelector(pageSections)) return;
        const update = pageRequest ? pageRequest.catch(() => {}).then(refreshPageSections) : refreshPageSections();
        event.detail.waitUntil(update);
    });
    document.addEventListener('cw:mutation', () => {
        channel?.postMessage('changed');
        sync();
    });
    channel?.addEventListener('message', sync);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) sync(); });
    window.addEventListener('online', sync);
    setInterval(() => { if (!document.hidden) sync(); }, 2000);
    // Defer until the view modules have attached their listeners.
    if (document.readyState === 'complete') sync();
    else window.addEventListener('load', sync, {once: true});
}
