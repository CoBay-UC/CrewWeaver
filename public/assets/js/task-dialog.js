import {api, toast} from './api.js';
import {changedFields, saveVersioned} from './versioned-edit.js';

let currentTask = null;
let loadSequence = 0;
let saving = false;
let milestoneDraft = false;
let deferredUpdate = false;
const modalElement = document.querySelector('#taskModal');

function escapeHtml(value) {
    const element = document.createElement('span');
    element.textContent = value ?? '';
    return element.innerHTML.replaceAll('"', '&quot;').replaceAll("'", '&#39;');
}

function option(value, current) {
    return `<option ${value === current ? 'selected' : ''}>${escapeHtml(value)}</option>`;
}

function renderOverview(task) {
    const readonly = !task.permissions.edit ? 'disabled' : '';
    const statuses = ['Not Started', 'Researching', 'In-Progress', 'Needs Outside Input', 'Needs Review', 'Done'];
    const priorities = ['low', 'normal', 'high', 'critical'];
    return `<form data-task-form><div class="row g-3"><div class="col-12"><label class="form-label">Title</label><input class="form-control" name="title" value="${escapeHtml(task.title)}" ${readonly}></div><div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="5" ${readonly}>${escapeHtml(task.description)}</textarea></div><div class="col-md-6"><label class="form-label">Status</label><select class="form-select" name="status" ${readonly}>${statuses.map(value => option(value, task.status)).join('')}</select></div><div class="col-md-6"><label class="form-label">Priority</label><select class="form-select" name="priority" ${readonly}>${priorities.map(value => option(value, task.priority)).join('')}</select></div><div class="col-md-6"><label class="form-label" for="task-progress">Progress</label><input id="task-progress" class="form-range" name="progress" type="range" min="0" max="100" value="${task.progress}" ${readonly}><output for="task-progress" data-task-progress>${task.progress}%</output></div><div class="col-md-6"><label class="form-label">Owner</label><div class="form-control-plaintext">${escapeHtml(task.owner_name || 'Unowned')}</div>${task.permissions.claim && !task.owner_user_id ? '<button type="button" class="btn btn-sm btn-outline-primary" data-task-claim>Take ownership</button>' : ''}</div></div><input type="hidden" name="version" value="${task.version}"></form>`;
}

function renderTimeline(task) {
    const milestones = task.milestones.map((item, index) => `<div class="row g-2 align-items-center mb-2" data-milestone data-id="${item.id}"><div class="col"><input class="form-control form-control-sm" name="title" value="${escapeHtml(item.title)}" aria-label="Milestone title"></div><div class="col-3"><div class="input-group input-group-sm"><input class="form-control" name="duration" type="number" min="0" value="${item.duration_days}" aria-label="Duration"><span class="input-group-text">days</span></div></div><div class="col-auto"><button type="button" class="btn btn-sm btn-outline-danger" data-milestone-remove aria-label="Remove milestone"><i class="bi bi-trash"></i></button></div><div class="col-12 small text-secondary">Task-relative day ${item.cumulative_offset_days} · ${escapeHtml(item.calculated_date || 'not scheduled')}</div></div>`).join('');
    return `<div class="row g-3 mb-4"><div class="col-md-4"><span class="small text-secondary d-block">Start boundary</span><strong>${escapeHtml(task.planned_start || '—')}</strong></div><div class="col-md-4"><span class="small text-secondary d-block">Finish boundary</span><strong>${escapeHtml(task.calculated_finish || '—')}</strong></div><div class="col-md-4"><span class="small text-secondary d-block">Manual deadline</span><strong>${escapeHtml(task.manual_due_date || '—')}</strong></div></div><div class="d-flex justify-content-between align-items-center mb-2"><h3 class="h6 mb-0">Milestones</h3>${task.permissions.edit ? '<button class="btn btn-sm btn-outline-primary" type="button" data-milestone-add><i class="bi bi-plus-lg"></i> Stage</button>' : ''}</div><div data-milestones>${milestones || '<p class="text-secondary">No milestones; task duration is used.</p>'}</div>${task.permissions.edit ? '<button class="btn btn-sm btn-primary mt-3" type="button" data-milestones-save>Save milestones & recalculate</button>' : ''}`;
}

function renderResearch(task) {
    const items = task.research.map(item => `<article class="border cw-inline-panel p-3 mb-2"><div class="d-flex justify-content-between"><strong>${escapeHtml(item.title)}</strong><span class="badge text-bg-light">${escapeHtml(item.type)}</span></div>${item.url ? `<a href="${escapeHtml(item.url)}" target="_blank" rel="noopener noreferrer">${escapeHtml(item.url)}</a>` : ''}<p class="mb-0 mt-2">${escapeHtml(item.content || item.description || '')}</p><small class="text-secondary">${escapeHtml(item.author_name || 'Historical author')}</small></article>`).join('');
    const files = task.attachments.map(file => `<a class="d-flex justify-content-between border cw-inline-panel p-2 mb-2 text-decoration-none" href="/files/${file.id}/download"><span><i class="bi bi-paperclip me-2"></i>${escapeHtml(file.original_name)}</span><small>${Math.ceil(file.size_bytes / 1024)} KB</small></a>`).join('');
    return `<div data-research-items>${items || '<p class="text-secondary">No research items.</p>'}</div><h3 class="h6 mt-4">Supporting files</h3><div>${files || '<p class="text-secondary">No files attached.</p>'}</div>${task.permissions.contribute ? '<form class="border-top pt-3 mt-3" data-upload-form><div class="input-group"><input class="form-control" name="file" type="file" required><button class="btn btn-outline-primary" type="submit">Upload</button></div></form><form class="border-top pt-3 mt-3" data-research-form><h3 class="h6">Add research</h3><div class="row g-2"><div class="col-md-3"><select class="form-select" name="type"><option value="link">Link</option><option value="note">Note</option></select></div><div class="col-md-9"><input class="form-control" name="title" placeholder="Title" required></div><div class="col-12"><input class="form-control" name="url" type="url" placeholder="https:// (for links)"></div><div class="col-12"><textarea class="form-control" name="content" placeholder="Notes"></textarea></div><div class="col-12"><button class="btn btn-primary" type="submit">Add research</button></div></div></form>' : ''}`;
}

function renderComments(task) {
    const items = task.comments.map(item => `<article class="d-flex gap-2 mb-3"><span class="cw-avatar cw-avatar-sm">${escapeHtml((item.display_name || '?')[0].toUpperCase())}</span><div class="flex-grow-1"><div><strong>${escapeHtml(item.display_name || 'Former member')}</strong> <small class="text-secondary">${escapeHtml(item.created_at)}${item.edited_at ? ' · edited' : ''}</small></div><div class="cw-comment">${escapeHtml(item.body)}</div></div></article>`).join('');
    return `<div data-comments-list>${items || '<p class="text-secondary">No comments yet.</p>'}</div>${task.permissions.contribute ? '<form class="d-flex gap-2 border-top pt-3" data-comment-form><textarea class="form-control" name="body" rows="2" placeholder="Add a comment" required></textarea><button class="btn btn-primary align-self-end" type="submit">Send</button></form>' : ''}`;
}

function renderTaskOverview(task, draft = {}) {
    const modal = document.querySelector('#taskModal');
    modal.querySelector('[data-task-title]').textContent = task.title;
    modal.querySelector('[data-task-project]').textContent = task.project_title;
    modal.querySelector('[data-task-overview]').innerHTML = renderOverview(task);
    modal.querySelector('[data-task-version]').textContent = `Version ${task.version} · Updated ${task.updated_at}`;
    modal.querySelector('[data-task-save]').hidden = !task.permissions.edit;
    const form = modal.querySelector('[data-task-form]');
    for (const [name, value] of Object.entries(draft)) form.elements[name].value = value;
    const progress = modal.querySelector('#task-progress');
    const progressLabel = modal.querySelector('[data-task-progress]');
    progressLabel.value = progress.value + '%';
    progress.addEventListener('input', () => {
        progressLabel.value = progress.value + '%';
    });
    modal.querySelector('[data-task-claim]')?.addEventListener('click', async () => {
        try { currentTask = await api(`/api/tasks/${currentTask.id}/owner`, {method: 'PATCH', body: {owner_user_id: document.body.dataset.currentUserId}}); toast('Ownership claimed.'); await openTask(currentTask.id); } catch (error) { toast(error.message, 'danger'); }
    });
}

function bindTaskSections() {
    const modal = document.querySelector('#taskModal');
    modal.querySelector('[data-milestone-add]')?.addEventListener('click', () => {
        milestoneDraft = true;
        const holder = modal.querySelector('[data-milestones]');
        if (holder.querySelector('p')) holder.innerHTML = '';
        holder.insertAdjacentHTML('beforeend', '<div class="row g-2 align-items-center mb-2" data-milestone><div class="col"><input class="form-control form-control-sm" name="title" placeholder="Stage title" required></div><div class="col-3"><input class="form-control form-control-sm" name="duration" type="number" min="0" value="1"></div><div class="col-auto"><button type="button" class="btn btn-sm btn-outline-danger" data-milestone-remove><i class="bi bi-trash"></i></button></div></div>');
    });
    modal.querySelector('[data-milestones]')?.addEventListener('click', event => {
        if (event.target.closest('[data-milestone-remove]')) {
            milestoneDraft = true;
            event.target.closest('[data-milestone]').remove();
        }
    });
    modal.querySelector('[data-milestones]')?.addEventListener('input', () => { milestoneDraft = true; });
    modal.querySelector('[data-milestones-save]')?.addEventListener('click', async () => {
        const milestones = [...modal.querySelectorAll('[data-milestone]')].map((row, index) => ({id: row.dataset.id || undefined, title: row.querySelector('[name="title"]').value, duration_days: Number(row.querySelector('[name="duration"]').value), display_order: (index + 1) * 10}));
        try { await api(`/api/tasks/${currentTask.id}/milestones`, {method: 'PUT', body: {milestones}}); toast('Milestones saved.'); await openTask(currentTask.id); } catch (error) { toast(error.message, 'danger'); }
    });
    modal.querySelector('[data-comment-form]')?.addEventListener('submit', async event => { event.preventDefault(); try { await api(`/api/tasks/${currentTask.id}/comments`, {method: 'POST', body: Object.fromEntries(new FormData(event.target))}); await openTask(currentTask.id); } catch (error) { toast(error.message, 'danger'); } });
    modal.querySelector('[data-research-form]')?.addEventListener('submit', async event => { event.preventDefault(); try { await api(`/api/tasks/${currentTask.id}/research`, {method: 'POST', body: Object.fromEntries(new FormData(event.target))}); await openTask(currentTask.id); } catch (error) { toast(error.message, 'danger'); } });
    modal.querySelector('[data-upload-form]')?.addEventListener('submit', async event => { event.preventDefault(); try { await api(`/api/tasks/${currentTask.id}/attachments`, {method: 'POST', body: new FormData(event.target)}); toast('File uploaded.'); await openTask(currentTask.id); } catch (error) { toast(error.message, 'danger'); } });
}

function renderTask(task) {
    currentTask = task;
    renderTaskOverview(task);
    modalElement.querySelector('[data-task-timeline]').innerHTML = renderTimeline(task);
    modalElement.querySelector('[data-task-research]').innerHTML = renderResearch(task);
    modalElement.querySelector('[data-task-comments]').innerHTML = renderComments(task);
    modalElement.querySelector('[data-task-activity]').innerHTML = task.activity.length
        ? '<ul class="list-group">' + task.activity.map(entry => `<li class="list-group-item"><strong>${escapeHtml(entry.action.replaceAll('.', ' '))}</strong><div class="small text-secondary">${escapeHtml(entry.actor_name || 'System')} · ${escapeHtml(entry.created_at)} UTC</div></li>`).join('') + '</ul>'
        : '<p class="text-secondary">No recorded task activity.</p>';
    bindTaskSections();
}

function overviewFields() {
    const form = modalElement?.querySelector('[data-task-form]');
    if (!form) return {};
    const {version, ...fields} = Object.fromEntries(new FormData(form));
    return fields;
}

function hasSectionDraft() {
    return milestoneDraft || Boolean(modalElement.querySelector('[data-comment-form] textarea')?.value.trim())
        || [...modalElement.querySelectorAll('[data-research-form] input, [data-research-form] textarea')].some(input => input.value.trim())
        || Boolean(modalElement.querySelector('[data-upload-form] input')?.files.length);
}

function hasDraft() {
    return saving || hasSectionDraft() || Object.keys(changedFields(overviewFields(), currentTask)).length > 0
        || (modalElement.contains(document.activeElement) && document.activeElement.matches('input, textarea, select'));
}

async function refreshTask() {
    if (!currentTask || !modalElement.classList.contains('show')) return;
    if (hasDraft()) {
        deferredUpdate = true;
        return;
    }
    deferredUpdate = false;
    const sequence = loadSequence;
    const baseline = currentTask;
    let task;
    try {
        task = await api('/api/tasks/' + baseline.id, {cache: 'no-store'});
    } catch (error) {
        if (error.status !== 404) throw error;
        if (sequence === loadSequence && currentTask === baseline && !hasDraft()) {
            bootstrap.Modal.getInstance(modalElement)?.hide();
            toast('This task has been deleted.', 'warning');
        }
        return;
    }
    if (sequence === loadSequence && currentTask === baseline && modalElement.classList.contains('show')) {
        if (hasDraft()) deferredUpdate = true;
        else if (JSON.stringify(task) !== JSON.stringify(baseline)) renderTask(task);
    }
}

document.addEventListener('cw:workspace-change', event => event.detail.waitUntil(refreshTask()));
modalElement?.addEventListener('focusout', () => queueMicrotask(() => {
    if (deferredUpdate) refreshTask().catch(() => {});
}));
modalElement?.addEventListener('hidden.bs.modal', () => { loadSequence++; currentTask = null; });

export async function openTask(id) {
    if (!modalElement) return;
    const sequence = ++loadSequence;
    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    modal.show();
    modalElement.querySelector('[data-task-title]').textContent = 'Loading…';
    modalElement.querySelector('[data-task-save]').hidden = true;
    modalElement.querySelector('[data-task-project]').textContent = '';
    modalElement.querySelector('[data-task-version]').textContent = '';
    modalElement.querySelector('[data-task-save-error]').hidden = true;
    for (const panel of modalElement.querySelectorAll('[role="tabpanel"]')) {
        panel.replaceChildren();
    }
    currentTask = null;
    milestoneDraft = false;
    deferredUpdate = false;
    try {
        const task = await api(`/api/tasks/${id}`);
        if (sequence !== loadSequence) return;
        renderTask(task);
    } catch (error) {
        if (sequence !== loadSequence) return;
        modalElement.querySelector('[data-task-title]').textContent = 'Could not load task';
        modalElement.querySelector('[data-task-overview]').textContent = error.message;
        toast(error.message, 'danger');
    }
}

export async function saveCurrentTask() {
    if (saving || !currentTask?.permissions.edit) return;
    const baseline = currentTask;
    const sequence = loadSequence;
    const button = modalElement.querySelector('[data-task-save]');
    const errorBox = modalElement.querySelector('[data-task-save-error]');
    saving = button.disabled = true;
    errorBox.hidden = true;
    try {
        const result = await saveVersioned('/api/tasks/' + baseline.id, baseline, overviewFields(), (latest, draft) => {
            if (sequence !== loadSequence) return;
            currentTask = latest;
            renderTaskOverview(latest, draft);
        });
        if (result) toast('Task updated.');
        if (sequence === loadSequence && result) {
            const saved = await api('/api/tasks/' + baseline.id);
            if (sequence !== loadSequence) return;
            currentTask = saved;
            if (hasSectionDraft()) renderTaskOverview(saved);
            else renderTask(saved);
        }
    } catch (error) {
        if (sequence === loadSequence) {
            errorBox.textContent = error.message;
            errorBox.hidden = false;
        }
    } finally {
        saving = button.disabled = false;
    }
}
