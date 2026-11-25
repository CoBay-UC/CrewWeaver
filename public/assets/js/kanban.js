import {api, debounce, toast} from './api.js';
import {refreshPageSections} from './workspace-sync.js';

let board = document.querySelector('[data-board]');
const filters = document.querySelector('[data-board-filters]');
let lastSnapshot = board.outerHTML;
let sortables = [];
let dragging = false;
let saving = false;
let deferred = false;
let filterSequence = 0;

async function applyFilters() {
    const sequence = ++filterSequence;
    const query = new URLSearchParams(new FormData(filters));
    const filtered = [...query.values()].some(Boolean);
    try {
        const data = filtered ? await api(`/api/boards/${board.dataset.scope}/${board.dataset.scopeId}?${query}`) : null;
        if (sequence !== filterSequence) return;
        const visible = data && new Set(data.tasks.map(task => String(task.id)));
        board.querySelectorAll('[data-task-id]').forEach(card => {
            card.hidden = visible !== null && !visible.has(card.dataset.taskId);
        });
        for (const count of board.querySelectorAll('[data-column-count]')) {
            const columns = board.querySelectorAll(`[data-status="${CSS.escape(count.dataset.columnCount)}"]`);
            count.textContent = Array.from(columns).reduce((sum, column) => sum + column.querySelectorAll('[data-task-id]:not([hidden])').length, 0);
        }
    } catch (error) {
        toast(error.message, 'danger');
    }
}

function settle() {
    if (!deferred) return;
    deferred = false;
    refreshPageSections().catch(() => { deferred = true; });
}

async function persist(card, oldColumn, oldNext) {
    const column = card.closest('[data-status]');
    const status = column.dataset.status;
    const previous = Number(card.previousElementSibling?.dataset.order);
    const next = Number(card.nextElementSibling?.dataset.order);
    const order = Number.isFinite(previous)
        ? (Number.isFinite(next) ? (previous + next) / 2 : previous + 1000)
        : (Number.isFinite(next) ? next - 1000 : 1000);
    saving = true;
    const select = card.querySelector('[data-status-select]');
    select.value = status;
    try {
        const path = '/api/tasks/' + card.dataset.taskId;
        const save = version => api(path + '/status', {method: 'PATCH', body: {status, order, version}});
        let task;
        try {
            task = await save(Number(card.dataset.version));
        } catch (error) {
            if (error.status !== 409) throw error;
            const latest = await api(path, {cache: 'no-store'});
            if (latest.status !== oldColumn.dataset.status || Number(latest.kanban_order) !== Number(card.dataset.order)) throw error;
            task = await save(Number(latest.version));
        }
        card.dataset.version = task.version;
        card.dataset.order = task.kanban_order;
        toast('Task position saved.');
    } catch (error) {
        oldColumn.insertBefore(card, oldNext);
        select.value = oldColumn.dataset.status;
        toast(error.status === 409 ? 'The task changed. The board is updating with its current position.' : error.message, 'danger');
    } finally {
        saving = false;
        deferred = true;
        settle();
    }
}

function setLane(button, collapsed) {
    button.parentElement.querySelector('.cw-swimlane-columns').hidden = collapsed;
    button.setAttribute('aria-expanded', String(!collapsed));
    button.querySelector('i').className = `bi bi-chevron-${collapsed ? 'right' : 'down'}`;
}

function bindColumns() {
    if (!window.Sortable) return;
    sortables = [...board.querySelectorAll('.cw-kanban-column')].map(column => {
        let oldNext;
        return new Sortable(column, {
            group: {name: `lane-${column.dataset.lane}`, pull: true, put: (_to, from) => from.el.dataset.lane === column.dataset.lane},
            animation: 160,
            draggable: '.cw-task-card[data-task-editable]',
            ghostClass: 'cw-task-ghost',
            filter: 'select',
            preventOnFilter: false,
            onChoose(event) {
                dragging = true;
                oldNext = event.item.nextElementSibling;
            },
            onUnchoose() {
                dragging = false;
                queueMicrotask(settle);
            },
            onMove: () => !saving,
            onEnd(event) {
                if (event.from !== event.to || event.oldIndex !== event.newIndex) {
                    if (!saving) persist(event.item, event.from, oldNext);
                    else event.from.insertBefore(event.item, oldNext);
                }
            },
        });
    });
}

document.addEventListener('change', event => {
    const select = event.target.closest('[data-status-select]');
    if (!select) return;
    const card = select.closest('[data-task-id]');
    const oldColumn = card.closest('[data-status]');
    if (saving || dragging) {
        select.value = oldColumn.dataset.status;
        return;
    }
    const target = [...oldColumn.parentElement.querySelectorAll('[data-status]')].find(column => column.dataset.status === select.value);
    if (!target || target === oldColumn) return;
    const oldNext = card.nextElementSibling;
    target.append(card);
    persist(card, oldColumn, oldNext);
});
document.addEventListener('click', event => {
    const button = event.target.closest('[data-swimlane-toggle]');
    if (button) setLane(button, button.getAttribute('aria-expanded') === 'true');
});
filters?.addEventListener('input', debounce(applyFilters));

document.addEventListener('cw:page-snapshot', event => {
    if (dragging || saving) {
        deferred = true;
        return;
    }
    const replacement = event.detail.querySelector('[data-board]');
    if (!replacement) return;
    const snapshot = replacement.outerHTML;
    if (snapshot === lastSnapshot) {
        applyFilters();
        return;
    }
    lastSnapshot = snapshot;
    const collapsed = new Set([...board.querySelectorAll('[data-swimlane-toggle][aria-expanded="false"]')].map(button => button.textContent.trim()));
    sortables.forEach(sortable => sortable.destroy());
    const left = board.scrollLeft;
    const focused = board.contains(document.activeElement) ? document.activeElement : null;
    const id = focused?.closest('[data-task-id]')?.dataset.taskId;
    board.replaceWith(replacement);
    board = replacement;
    board.scrollLeft = left;
    if (id) {
        const card = board.querySelector(`[data-task-id="${CSS.escape(id)}"]`);
        (card?.querySelector(focused.matches('[data-status-select]') ? 'select' : 'button') ?? card)?.focus({preventScroll: true});
    }
    board.querySelectorAll('[data-swimlane-toggle]').forEach(button => setLane(button, collapsed.has(button.textContent.trim())));
    bindColumns();
    applyFilters();
});
bindColumns();
