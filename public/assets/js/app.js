import {api} from './api.js';
import {openTask, saveCurrentTask} from './task-dialog.js';
import './project-actions.js';
import './workspace-sync.js';

document.addEventListener('click', event => {
    const opener = event.target.closest('[data-task-open]');
    if (opener) { event.preventDefault(); openTask(opener.dataset.taskOpen); }
});
document.querySelector('[data-task-save]')?.addEventListener('click', saveCurrentTask);

document.querySelector('[data-notifications-toggle]')?.addEventListener('click', async () => {
    const panel = document.querySelector('#notificationsPanel');
    bootstrap.Offcanvas.getOrCreateInstance(panel).show();
    const list = panel.querySelector('[data-notifications-list]');
    try {
        const data = await api('/api/notifications');
        list.innerHTML = '';
        if (!data.items.length) list.innerHTML = '<p class="text-secondary text-center py-5">No notifications.</p>';
        data.items.forEach(item => { const article = document.createElement('a'); article.className = `d-block p-3 border-bottom text-decoration-none text-body ${item.read_at ? '' : 'bg-body-tertiary'}`; article.href = item.resource_url || '#'; const title = document.createElement('strong'); title.textContent = item.title; const body = document.createElement('div'); body.className = 'small text-secondary'; body.textContent = item.body; article.append(title, body); list.append(article); });
    } catch (error) { list.textContent = error.message; }
});

