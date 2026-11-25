import {api, toast} from './api.js';
import {openTask} from './task-dialog.js';
import {renderCalendarList} from './calendar-list.js';

const element = document.querySelector('[data-calendar]');
if (element && window.FullCalendar?.Calendar) {
    const panel = element.closest('[data-calendar-panel]');
    const listAll = panel.querySelector('[data-calendar-list-all]');
    const buttons = [...panel.querySelectorAll('[data-calendar-view]')];
    const endpoint = `/api/calendar/${element.dataset.scope}/${element.dataset.scopeId}`;
    let allRequest;
    let allSignature;

    function selectView(view) {
        buttons.forEach(button => {
            const selected = button.dataset.calendarView === view;
            button.classList.toggle('active', selected);
            button.setAttribute('aria-pressed', String(selected));
        });
    }

    async function showAll(background = false) {
        element.hidden = true;
        listAll.hidden = false;
        selectView('all');
        allRequest?.abort();
        const request = new AbortController();
        allRequest = request;
        if (!background) {
            listAll.setAttribute('aria-busy', 'true');
            listAll.textContent = 'Loading calendar dates…';
        }
        try {
            const events = await api(endpoint + '?all=1', {signal: request.signal});
            if (allRequest === request) {
                const signature = JSON.stringify(events);
                if (allSignature !== signature || !background) renderCalendarList(listAll, events);
                allSignature = signature;
            }
        } catch (error) {
            if (request.signal.aborted) return;
            if (background) throw error;
            listAll.textContent = 'Could not load calendar dates. Select List All to retry.';
            toast(error.message, 'danger');
        } finally {
            if (allRequest === request) listAll.setAttribute('aria-busy', 'false');
        }
    }

    const compact = window.matchMedia('(max-width: 699px)').matches;
    const calendar = new window.FullCalendar.Calendar(element, {
        initialView: compact ? 'listMonth' : 'dayGridMonth',
        headerToolbar: {left: 'title', center: '', right: compact ? 'prev,next' : 'today prev,next'},
        footerToolbar: compact ? {left: 'today', center: '', right: ''} : false,
        height: 'auto',
        navLinks: true,
        eventSources: [{events: async (info, success, failure) => {
            try { success(await api(`${endpoint}?start=${info.startStr.slice(0,10)}&end=${info.endStr.slice(0,10)}`)); }
            catch (error) { toast(error.message, 'danger'); failure(error); }
        }}],
        eventClick(info) {
            if (info.event.extendedProps.taskId) {
                info.jsEvent.preventDefault();
                openTask(info.event.extendedProps.taskId);
            }
        },
        eventContent(info) {
            const title = info.event.extendedProps.listTitle;
            if (info.view.type.startsWith('list') && title) {
                return {domNodes: [document.createTextNode(title)]};
            }
            return true;
        },
        datesSet(info) {
            if (listAll.hidden) selectView(info.view.type);
        },
    });
    calendar.render();
    document.addEventListener('cw:workspace-change', event => {
        if (listAll.hidden) calendar.refetchEvents();
        else event.detail.waitUntil(showAll(true));
    });
    for (const button of buttons) button.addEventListener('click', () => {
        const view = button.dataset.calendarView;
        if (view === 'all') {
            showAll();
            return;
        }
        allRequest?.abort();
        allRequest = null;
        listAll.hidden = true;
        listAll.setAttribute('aria-busy', 'false');
        element.hidden = false;
        calendar.changeView(view);
        calendar.updateSize();
        selectView(view);
    });
} else if (element) {
    element.textContent = 'The calendar could not load. Refresh the page to try again.';
}
