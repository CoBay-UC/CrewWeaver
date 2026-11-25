import {openTask} from './task-dialog.js';

const monthFormat = new Intl.DateTimeFormat('en-US', {month: 'long', year: 'numeric', timeZone: 'UTC'});
const dayFormat = new Intl.DateTimeFormat('en-US', {
    weekday: 'long', month: 'long', day: 'numeric', year: 'numeric', timeZone: 'UTC',
});

function calendarDate(date) {
    return new Date(date + 'T00:00:00Z');
}

function fullDateLabel(date) {
    const parts = {};
    for (const part of dayFormat.formatToParts(date)) {
        if (part.type !== 'literal') parts[part.type] = part.value;
    }
    return parts.weekday + ' ' + parts.month + ' ' + parts.day + ', ' + parts.year;
}

function eventItem(event) {
    const item = document.createElement('li');
    item.className = 'list-group-item px-0 d-flex align-items-center gap-2';
    const marker = document.createElement('span');
    marker.className = 'cw-project-dot';
    marker.style.backgroundColor = event.color;
    marker.setAttribute('aria-hidden', 'true');
    const taskId = event.extendedProps?.taskId;
    let title;
    if (taskId) {
        title = document.createElement('button');
        title.type = 'button';
        title.className = 'btn btn-link p-0 text-start';
        title.addEventListener('click', () => openTask(taskId));
    } else if (event.url) {
        title = document.createElement('a');
        title.href = event.url;
    } else {
        title = document.createElement('span');
    }
    title.textContent = event.extendedProps?.listTitle ?? event.title;
    item.append(marker, title);
    if (!event.allDay && event.start.length > 10) {
        const time = document.createElement('time');
        time.className = 'text-secondary small';
        time.dateTime = event.start;
        time.textContent = event.start.slice(11, 16);
        item.append(time);
    }
    if (event.end) {
        const lastDay = calendarDate(event.end.slice(0, 10));
        lastDay.setUTCDate(lastDay.getUTCDate() - 1);
        if (lastDay > calendarDate(event.start.slice(0, 10))) {
            const end = document.createElement('span');
            end.className = 'text-secondary small';
            end.textContent = 'Through ' + fullDateLabel(lastDay);
            item.append(end);
        }
    }
    return item;
}

export function renderCalendarList(container, events) {
    if (!events.length) {
        container.textContent = 'No calendar dates to show.';
        return;
    }
    const fragment = document.createDocumentFragment();
    let monthSection, dayList, previousMonth, previousDay;
    const label = event => event.extendedProps?.listTitle ?? event.title;
    const sorted = [...events].sort((a, b) => a.start.localeCompare(b.start) || label(a).localeCompare(label(b)));
    for (const event of sorted) {
        const day = event.start.slice(0, 10);
        const month = day.slice(0, 7);
        if (month !== previousMonth) {
            monthSection = document.createElement('section');
            monthSection.className = 'mb-4';
            const heading = document.createElement('h2');
            heading.className = 'h5 border-bottom pb-2';
            heading.textContent = monthFormat.format(calendarDate(day));
            monthSection.append(heading);
            fragment.append(monthSection);
            previousMonth = month;
        }
        if (day !== previousDay) {
            const heading = document.createElement('h3');
            heading.className = 'h6 mt-3 mb-0';
            const time = document.createElement('time');
            time.dateTime = day;
            time.textContent = fullDateLabel(calendarDate(day));
            heading.append(time);
            dayList = document.createElement('ul');
            dayList.className = 'list-group list-group-flush';
            monthSection.append(heading, dayList);
            previousDay = day;
        }
        dayList.append(eventItem(event));
    }
    container.replaceChildren(fragment);
}
