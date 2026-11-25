import {api, toast} from './api.js';
import {confirmAction} from './confirmation-dialog.js';
import {openTask} from './task-dialog.js';
import {saveVersioned} from './versioned-edit.js';
import {refreshPageSections} from './workspace-sync.js';

let projectBaseline;

function localToday() {
    const today = new Date();
    return [today.getFullYear(), today.getMonth() + 1, today.getDate()]
        .map(part => String(part).padStart(2, '0')).join('-');
}

function bindEditor(dialog, selector, prepare, save, onSave) {
    if (!dialog) return;
    const form = dialog.querySelector('form');
    const errorBox = form.querySelector('[data-dialog-error]');
    const submit = form.querySelector('[type="submit"]');
    const cancelButtons = form.querySelectorAll('[data-dialog-cancel]');
    let trigger;
    let saving = false;

    document.addEventListener('click', event => {
        const button = event.target.closest(selector);
        if (!button) return;
        if (dialog.open) return;
        trigger = button;
        form.reset();
        errorBox.hidden = true;
        errorBox.textContent = '';
        prepare(form, button);
        dialog.showModal();
    });
    cancelButtons.forEach(button => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('cancel', event => {
        if (saving) event.preventDefault();
    });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (saving || !trigger) return;
        const title = form.elements.title;
        if (!title.value.trim()) {
            title.value = '';
            title.reportValidity();
            return;
        }
        saving = submit.disabled = true;
        cancelButtons.forEach(button => { button.disabled = true; });
        form.setAttribute('aria-busy', 'true');
        errorBox.hidden = true;
        let result;
        try {
            const body = Object.fromEntries(new FormData(form));
            body.title = title.value.trim();
            result = await save(body, trigger, form);
        } catch (error) {
            const details = Object.values(error.errors ?? {}).flat();
            errorBox.textContent = details.length ? details.join(' ') : error.message;
            errorBox.hidden = false;
            errorBox.focus();
            return;
        } finally {
            saving = submit.disabled = false;
            cancelButtons.forEach(button => { button.disabled = false; });
            form.removeAttribute('aria-busy');
        }
        dialog.close();
        onSave(result, trigger);
    });
}

function prepareProject(form, button) {
    const copying = Boolean(button.dataset.projectCopy);
    const editing = Boolean(button.dataset.projectEdit);
    projectBaseline = {
        title: button.dataset.projectTitle,
        description: button.dataset.projectDescription,
        version: button.dataset.projectVersion,
    };
    form.querySelector('#project-editor-title').textContent = editing ? 'Edit project' : copying ? 'Copy project' : 'New project';
    form.querySelector('[data-project-editor-scope]').textContent = button.dataset.scopeName;
    form.querySelector('[data-project-editor-submit]').textContent = editing ? 'Save changes' : copying ? 'Copy project' : 'Create project';
    form.elements.title.value = editing ? projectBaseline.title : copying ? (button.dataset.projectTitle + ' (copy)').slice(0, 240) : '';
    form.elements.description.value = editing ? projectBaseline.description : '';
    form.querySelector('[data-project-start-fields]').hidden = editing;
    form.elements.planned_start.disabled = editing;
    form.elements.planned_start.value = localToday();
    const options = form.querySelector('[data-project-copy-fields]');
    options.hidden = options.disabled = !copying;
    form.querySelector('[data-project-create-fields]').hidden = copying;
    form.elements.description.disabled = copying;
}

function saveProject(body, trigger, form) {
    if (trigger.dataset.projectEdit) {
        const path = '/api/projects/' + trigger.dataset.projectEdit;
        return saveVersioned(path, projectBaseline, body, (latest, draft) => {
            projectBaseline = latest;
            form.elements.title.value = draft.title ?? latest.title;
            form.elements.description.value = draft.description ?? latest.description ?? '';
        });
    }
    body.team_id = Number(trigger.dataset.teamId);
    body.sub_team_id = Number(trigger.dataset.subTeamId) || null;
    if (!trigger.dataset.projectCopy) {
        return api('/api/projects', {method: 'POST', body: {...body, status: 'Planned'}});
    }
    for (const input of form.querySelectorAll('[data-project-copy-fields] input')) {
        body[input.name] = input.checked;
    }
    return api('/api/projects/' + trigger.dataset.projectCopy + '/copy', {method: 'POST', body})
        .then(result => result.project);
}

function appendTask(task) {
    const list = document.querySelector('[data-project-tasks]');
    if (list) {
        const row = document.createElement('button');
        row.type = 'button';
        row.className = 'list-group-item list-group-item-action d-flex align-items-center gap-3 py-3';
        row.dataset.taskOpen = task.id;
        const title = document.createElement('strong');
        title.className = 'flex-grow-1 text-start';
        title.textContent = task.title;
        const status = document.createElement('span');
        status.className = 'badge cw-status';
        status.textContent = task.status;
        row.append(title, status);
        list.append(row);
    }
    toast('Task created.');
    openTask(task.id);
}

bindEditor(document.querySelector('#projectEditor'), '[data-project-create], [data-project-copy], [data-project-edit]',
    prepareProject, saveProject, (project, trigger) => {
        if (trigger.dataset.projectEdit) {
            if (project) toast('Project updated.');
            refreshPageSections().catch(() => {});
        } else {
            location.href = '/projects/' + project.id;
        }
    });

bindEditor(document.querySelector('#taskCreateDialog'), '[data-task-create]',
    () => {},
    (body, trigger) => api('/api/tasks', {
        method: 'POST',
        body: {...body, project_id: Number(trigger.dataset.projectId), estimated_duration_days: Number(body.estimated_duration_days)},
    }),
    result => appendTask(result.task));

document.addEventListener('click', async event => {
        const trigger = event.target.closest('[data-project-archive], [data-project-restore], [data-project-delete]');
        if (!trigger) return;
        const deleting = Boolean(trigger.dataset.projectDelete);
        const archiving = Boolean(trigger.dataset.projectArchive);
        const verb = deleting ? 'Delete' : archiving ? 'Archive' : 'Restore';
        if (!await confirmAction({
            trigger,
            title: verb + ' project',
            message: deleting ? `Permanently delete “${trigger.dataset.projectTitle}” and all its tasks, files, comments, and Important Dates?`
                : archiving ? 'Archive this project and remove it from active views?' : 'Restore this project to active views?',
            label: deleting ? 'Permanently delete' : verb,
            danger: archiving || deleting,
            phrase: trigger.dataset.deletePhrase,
        })) return;
        trigger.disabled = true;
        const projectId = archiving ? trigger.dataset.projectArchive : trigger.dataset.projectRestore;
        try {
            if (deleting) {
                const result = await api('/api/projects/' + trigger.dataset.projectDelete, {
                    method: 'DELETE', body: {confirmation: trigger.dataset.deletePhrase},
                });
                if (result.pendingFiles) {
                    toast('Project deleted. Some uploaded files are queued for storage cleanup.', 'warning');
                    setTimeout(() => { location.href = trigger.dataset.scopeUrl; }, 2000);
                } else {
                    location.href = trigger.dataset.scopeUrl;
                }
                return;
            }
            await api('/api/projects/' + projectId + '/' + verb.toLowerCase(), {method: 'POST'});
            if (archiving) {
                location.href = '/archive';
            } else {
                toast('Project restored.');
                refreshPageSections().catch(() => {});
            }
        } catch (error) {
            trigger.disabled = false;
            toast(error.message, 'danger');
        }
});
