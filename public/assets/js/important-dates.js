import {api, toast} from './api.js';
import {confirmAction} from './confirmation-dialog.js';
import {refreshPageSections} from './workspace-sync.js';

const editor = document.querySelector('#importantDateEditor');
const form = editor.querySelector('form');
const errorBox = form.querySelector('[data-date-error]');
const submit = form.querySelector('[type="submit"]');
const cancelButtons = form.querySelectorAll('[data-date-cancel]');
let dateId = null;
let saving = false;

async function reloadAfterSave(message) {
    toast(message);
    await refreshPageSections().catch(() => {});
}

function openEditor(item) {
    dateId = item?.id ?? null;
    form.reset();
    errorBox.hidden = true;
    const selectedScope = item?.scope_key ?? document.querySelector('#dates-scope').value;
    form.elements.scope.value = [...form.elements.scope.options].some(option => option.value === selectedScope)
        ? selectedScope : '';
    if (item) {
        for (const field of ['title', 'description', 'event_date', 'end_date', 'priority', 'colour']) {
            form.elements[field].value = item[field] ?? '';
        }
        form.elements.event_time.value = Number(item.is_all_day) ? '' : item.event_time?.slice(0, 5) ?? '';
        form.elements.show_countdown.checked = Boolean(Number(item.show_countdown));
        form.elements.is_active.checked = Boolean(Number(item.is_active));
    }
    editor.querySelector('h2').textContent = item ? 'Edit Important Date' : 'New Important Date';
    editor.showModal();
}

for (const button of cancelButtons) button.addEventListener('click', () => editor.close());
editor.addEventListener('cancel', event => { if (saving) event.preventDefault(); });
form.addEventListener('submit', async event => {
    event.preventDefault();
    if (saving) return;
    const {scope, ...body} = Object.fromEntries(new FormData(form));
    const [type, id] = scope.split(':');
    body.scope_type = type;
    if (id) body[type + '_id'] = Number(id);
    body.is_all_day = !body.event_time;
    body.is_active = form.elements.is_active.checked;
    body.show_countdown = form.elements.show_countdown.checked;
    saving = submit.disabled = true;
    cancelButtons.forEach(button => { button.disabled = true; });
    errorBox.hidden = true;
    try {
        await api('/api/important-dates' + (dateId ? '/' + dateId : ''), {method: dateId ? 'PATCH' : 'POST', body});
    } catch (error) {
        const details = Object.values(error.errors ?? {}).flat();
        errorBox.textContent = details.length ? details.join(' ') : error.message;
        errorBox.hidden = false;
        errorBox.focus();
        return;
    } finally {
        saving = submit.disabled = false;
        cancelButtons.forEach(button => { button.disabled = false; });
    }
    editor.close();
    await reloadAfterSave('Important date saved.');
});

document.addEventListener('click', async event => {
    const trigger = event.target.closest('[data-date-new], [data-date-edit], [data-date-delete]');
    if (!trigger || saving || editor.open) return;
    if (!trigger.dataset.dateDelete) {
        openEditor(trigger.dataset.dateEdit ? JSON.parse(trigger.dataset.dateEdit) : null);
        return;
    }
    if (!await confirmAction({
        trigger,
        title: 'Delete Important Date',
        message: 'Delete “' + trigger.dataset.dateTitle + '”?',
        label: 'Delete',
        danger: true,
    })) return;
    trigger.disabled = true;
    try {
        await api('/api/important-dates/' + trigger.dataset.dateDelete, {method: 'DELETE'});
        await reloadAfterSave('Important date deleted.');
    } catch (error) {
        trigger.disabled = false;
        toast(error.message, 'danger');
    }
});
