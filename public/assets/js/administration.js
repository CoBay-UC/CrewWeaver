import {api, toast} from './api.js';
import {confirmAction} from './confirmation-dialog.js';
import {bindIconPicker} from './icon-picker.js';
import {refreshPageSections} from './workspace-sync.js';

function errorText(error) {
    const details = Object.values(error.errors ?? {}).flat();
    return details.length ? details.join(' ') : error.message;
}

function showError(element, error = null) {
    element.hidden = !error;
    element.textContent = error ? errorText(error) : '';
}

const scopeEditor = document.querySelector('#scopeEditor');
if (scopeEditor) {
    const form = scopeEditor.querySelector('[data-scope-form]');
    const iconPicker = bindIconPicker(form.querySelector('[data-icon-picker]'));
    const fields = scopeEditor.querySelector('[data-scope-fields]');
    const errorBox = scopeEditor.querySelector('[data-scope-error]');
    const deletionText = scopeEditor.querySelector('[data-scope-delete-message]');
    const deletionFields = scopeEditor.querySelector('[data-scope-delete-fields]');
    const confirmation = form.elements.confirmation;
    const phrase = scopeEditor.querySelector('#scope-delete-phrase').textContent;
    const submit = scopeEditor.querySelector('[data-scope-submit]');
    const modal = bootstrap.Modal.getOrCreateInstance(scopeEditor);
    let action;
    let saving = false;
    const updateSubmit = () => {
        submit.disabled = saving || (action?.deleting && confirmation.value !== phrase);
    };
    confirmation.addEventListener('input', updateSubmit);
    scopeEditor.addEventListener('shown.bs.modal', () => {
        (action?.deleting ? confirmation : form.elements.name).focus();
    });
    scopeEditor.addEventListener('hide.bs.modal', event => {
        if (saving) event.preventDefault();
    });

    document.addEventListener('click', event => {
        const button = event.target.closest('[data-scope-new], [data-scope-edit], [data-scope-delete]');
        if (!button || saving) return;
        const type = button.dataset.scopeNew ?? button.dataset.scopeEdit ?? button.dataset.scopeDelete;
        const record = button.dataset.record ? JSON.parse(button.dataset.record) : null;
        const deleting = Boolean(button.dataset.scopeDelete);
        const noun = type === 'teams' ? 'team' : 'sub-team';
        action = {type, id: record?.id, parentId: button.dataset.parentId, deleting};

        for (const field of ['name', 'slug', 'description', 'colour', 'icon', 'display_order']) {
            const defaults = {colour: type === 'teams' ? '#4f46e5' : '#0ea5e9', icon: type === 'teams' ? 'people' : 'diagram-3', display_order: 0};
            form.elements[field].value = record?.[field] ?? defaults[field] ?? '';
        }
        form.elements.is_active.checked = record === null || Boolean(Number(record.is_active));
        iconPicker.reset();
        fields.hidden = deleting;
        fields.querySelectorAll('input, textarea').forEach(input => { input.disabled = deleting; });
        deletionFields.hidden = deletionFields.disabled = !deleting;
        confirmation.value = '';
        deletionText.textContent = deleting ? 'Permanently delete ' + record.name + '?' : '';
        scopeEditor.querySelector('[data-scope-heading]').textContent = (deleting ? 'Delete' : record ? 'Edit' : 'New') + ' ' + noun;
        submit.textContent = deleting ? 'Permanently delete' : 'Save';
        submit.className = 'btn ' + (deleting ? 'btn-danger' : 'btn-primary');
        showError(errorBox);
        updateSubmit();
        modal.show();
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (!action || saving || (action.deleting && confirmation.value !== phrase)) return;
        const path = '/api/' + action.type + (action.id ? '/' + action.id : '');
        const body = Object.fromEntries(new FormData(form));
        body.is_active = form.elements.is_active.checked;
        if (action.parentId) body.team_id = Number(action.parentId);
        saving = true;
        updateSubmit();
        showError(errorBox);
        let result;
        try {
            result = await api(path, {method: action.deleting ? 'DELETE' : action.id ? 'PATCH' : 'POST', body});
        } catch (error) {
            showError(errorBox, error);
            return;
        } finally {
            saving = false;
            updateSubmit();
        }
        modal.hide();
        if (action.deleting) {
            const {projects, tasks} = result.deleted;
            toast(`Scope deleted with ${projects} project(s) and ${tasks} task(s).`);
            if (result.pendingFiles) toast('Some uploaded files are queued for storage cleanup.', 'warning');
        } else {
            toast('Scope saved.');
        }
        refreshPageSections().catch(() => {});
    });
}

document.querySelector('[data-navigation-settings]')?.addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.currentTarget;
    const submit = form.querySelector('button[type="submit"]');
    submit.disabled = true;
    try {
        await api('/api/organization/navigation', {method: 'PATCH', body: {show_sub_teams: form.elements.show_sub_teams.checked}});
        refreshPageSections().catch(() => {});
        toast('Navigation setting saved.');
    } catch (error) { toast(errorText(error), 'danger'); }
    finally { submit.disabled = false; }
});

document.addEventListener('change', async event => {
    const select = event.target.closest('[data-user-status]');
    if (!select) return;
    select.disabled = true;
    try {
        await api('/api/users/' + select.dataset.userStatus + '/status', {method: 'PATCH', body: {status: select.value}});
        for (const option of select.options) option.defaultSelected = option.selected;
        toast('User status updated.');
    } catch (error) {
        const saved = [...select.options].find(option => option.defaultSelected);
        if (saved) select.value = saved.value;
        toast(errorText(error), 'danger');
    } finally { select.disabled = false; }
});

const membershipsEditor = document.querySelector('#membershipsEditor');
if (membershipsEditor) {
    const form = membershipsEditor.querySelector('[data-membership-form]');
    const list = membershipsEditor.querySelector('[data-membership-list]');
    const errorBox = membershipsEditor.querySelector('[data-membership-error]');
    const modal = bootstrap.Modal.getOrCreateInstance(membershipsEditor);
    const submit = form.querySelector('button[type="submit"]');
    let userId;

    function message(text) {
        const item = document.createElement('li');
        item.className = 'list-group-item text-secondary';
        item.textContent = text;
        list.replaceChildren(item);
    }

    function renderMemberships(items) {
        const existing = new Set(items.map(item => item.team_id + ':' + (item.sub_team_id ?? 0)));
        for (const option of form.elements.scope.options) {
            option.disabled = existing.has(option.value);
        }
        submit.disabled = ![...form.elements.scope.options].some(option => option.value && !option.disabled);
        list.replaceChildren();
        if (!items.length) message('No memberships in your managed scopes.');
        for (const item of items) {
            const row = document.createElement('li');
            row.className = 'list-group-item d-flex justify-content-between align-items-center gap-2';
            const label = document.createElement('span');
            label.textContent = item.team_name + (item.sub_team_name ? ' / ' + item.sub_team_name : ' (team)');
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'btn btn-sm btn-outline-danger';
            remove.textContent = 'Remove';
            remove.setAttribute('aria-label', 'Remove ' + label.textContent);
            remove.addEventListener('click', async () => {
                if (await confirmAction({
                    trigger: remove,
                    title: 'Remove membership',
                    message: 'Remove membership in ' + label.textContent + '?',
                    label: 'Remove',
                    danger: true,
                })) {
                    changeMembership('DELETE', item.team_id, item.sub_team_id, remove);
                }
            });
            row.append(label, remove);
            list.append(row);
        }
    }

    async function changeMembership(method, teamId, subTeamId, control) {
        const target = userId;
        control.disabled = true;
        showError(errorBox);
        try {
            const items = await api('/api/memberships', {
                method,
                body: {user_id: target, team_id: teamId, sub_team_id: subTeamId},
            });
            if (target !== userId) return;
            form.reset();
            renderMemberships(items);
            toast(method === 'POST' ? 'Membership saved.' : 'Membership removed.');
        } catch (error) {
            if (target === userId) showError(errorBox, error);
            control.disabled = false;
        }
    }

    form.addEventListener('submit', event => {
        event.preventDefault();
        const [teamId, subTeamId] = form.elements.scope.value.split(':').map(Number);
        if (teamId) changeMembership('POST', teamId, subTeamId || null, submit);
    });

    document.addEventListener('click', async event => {
        const button = event.target.closest('[data-user-memberships]');
        if (!button) return;
        const target = Number(button.dataset.userMemberships);
        userId = target;
        form.reset();
        submit.disabled = true;
        membershipsEditor.querySelector('.modal-title').textContent = button.dataset.userName + ' · Memberships';
        showError(errorBox);
        message('Loading memberships…');
        modal.show();
        try {
            const items = await api('/api/users/' + target + '/memberships');
            if (userId === target) renderMemberships(items);
        } catch (error) {
            if (userId !== target) return;
            list.replaceChildren();
            showError(errorBox, error);
        }
    });
}
