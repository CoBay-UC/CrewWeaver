const millisecondsPerDay = 86400000;
const zoomLevels = {
    day: {width: 66, interval: 1},
    week: {width: 44, interval: 1},
    month: {width: 10, interval: 7},
    quarter: {width: 4, interval: 14},
};
const labelDate = new Intl.DateTimeFormat(undefined, {month: 'short', day: 'numeric', timeZone: 'UTC'});
const toDay = value => value ? Math.floor(Date.parse(value + 'T00:00:00Z') / millisecondsPerDay) : NaN;

function bindGantt(gantt, signal) {
    const today = toDay(gantt.dataset.today);
    const tracks = [...gantt.querySelectorAll('.cw-gantt-track')];
    const axes = [...gantt.querySelectorAll('[data-gantt-days]')];
    const scheduledDays = tracks.flatMap(track => [toDay(track.dataset.start), toDay(track.dataset.end)]).filter(Number.isFinite);
    const firstProjectDay = scheduledDays.length ? Math.min(...scheduledDays) : today;
    const lastProjectDay = scheduledDays.length ? Math.max(...scheduledDays) : today;
    const first = Math.min(today, firstProjectDay) - 2;
    const last = Math.max(today, lastProjectDay) + 2;
    let width;

    function jumpTo(day, centered = false) {
        const labelWidth = gantt.querySelector('.cw-gantt-label').offsetWidth;
        const visibleWidth = gantt.clientWidth - labelWidth;
        gantt.scrollLeft = (day - first) * width - (centered ? visibleWidth / 2 : 0);
    }

    function drawAxis(axis, anchor, interval, textForDay, isVisible = () => true) {
        const fragment = document.createDocumentFragment();
        for (let day = anchor; day <= last; day += interval) {
            if (!isVisible(day)) continue;
            const label = document.createElement('span');
            label.className = 'cw-gantt-tick';
            label.style.left = ((day - first) * width) + 'px';
            label.textContent = textForDay(day);
            fragment.append(label);
        }
        axis.replaceChildren(fragment);
    }

    function render(zoom) {
        const previousDay = width ? first + gantt.scrollLeft / width : today;
        const level = zoomLevels[zoom] ?? zoomLevels.week;
        width = level.width;
        gantt.style.setProperty('--gantt-day-width', width + 'px');
        gantt.style.setProperty('--gantt-width', ((last - first + 1) * width) + 'px');
        drawAxis(gantt.querySelector('[data-gantt-dates]'), first, level.interval,
            day => labelDate.format(new Date(day * millisecondsPerDay)));

        for (const axis of axes) {
            const start = toDay(axis.dataset.ganttDays);
            const groupTrack = axis.closest('[data-gantt-group]')?.querySelector('[data-project-track]');
            const groupTasks = groupTrack?.closest('[data-gantt-group]').querySelectorAll('[data-gantt-tasks] [data-end]') ?? [];
            const end = groupTrack ? Math.max(...[groupTrack, ...groupTasks].map(track => toDay(track.dataset.end)).filter(Number.isFinite)) : lastProjectDay;
            if (Number.isFinite(start)) {
                drawAxis(axis, start, level.interval, day => day - start + 1, day => day < end || day === start);
            }
        }

        for (const track of tracks) {
            const start = toDay(track.dataset.start);
            const end = toDay(track.dataset.end);
            if (Number.isFinite(start) && Number.isFinite(end)) {
                track.classList.add('cw-gantt-scheduled');
                track.style.setProperty('--bar-left', ((start - first) * width) + 'px');
                track.style.setProperty('--bar-width', Math.max(4, (end - start) * width) + 'px');
                track.style.setProperty('--bar-color', track.dataset.color || '#4f46e5');
                track.style.setProperty('--bar-progress', Math.max(0, Math.min(100, Number(track.dataset.progress))) + '%');
            }
            for (const marker of track.querySelectorAll('[data-date]')) {
                marker.style.left = ((toDay(marker.dataset.date) - first) * width) + 'px';
            }
        }
        gantt.style.setProperty('--gantt-today', ((today - first) * width) + 'px');
        document.querySelectorAll('[data-gantt-zoom]').forEach(button => {
            const selected = button.dataset.ganttZoom === zoom;
            button.classList.toggle('active', selected);
            button.setAttribute('aria-pressed', String(selected));
        });
        jumpTo(previousDay);
    }

    for (const button of document.querySelectorAll('[data-gantt-zoom]')) {
        button.addEventListener('click', () => {
            localStorage.setItem('crewweaver-gantt-zoom', button.dataset.ganttZoom);
            render(button.dataset.ganttZoom);
        }, {signal});
    }
    document.querySelector('[data-gantt-today]').addEventListener('click', () => jumpTo(today, true), {signal});
    document.querySelector('[data-gantt-start]').addEventListener('click', () => jumpTo(firstProjectDay), {signal});
    document.querySelector('[data-gantt-end]').addEventListener('click', () => jumpTo(lastProjectDay, true), {signal});
    gantt.querySelectorAll('[data-gantt-toggle]').forEach(button => button.addEventListener('click', () => {
        const tasks = button.closest('[data-gantt-group]').querySelector('[data-gantt-tasks]');
        tasks.hidden = !tasks.hidden;
        button.setAttribute('aria-expanded', String(!tasks.hidden));
        button.querySelector('i').className = 'bi me-1 bi-chevron-' + (tasks.hidden ? 'right' : 'down');
    }));
    const preferred = localStorage.getItem('crewweaver-gantt-zoom');
    render(Object.hasOwn(zoomLevels, preferred) ? preferred : 'week');
    jumpTo(today, true);
}

let gantt = document.querySelector('[data-gantt]');
let lastSnapshot = gantt.outerHTML;
let controls = new AbortController();
bindGantt(gantt, controls.signal);
document.addEventListener('cw:page-snapshot', event => {
    const replacement = event.detail.querySelector('[data-gantt]');
    if (!replacement || replacement.outerHTML === lastSnapshot) return;
    lastSnapshot = replacement.outerHTML;
    const collapsed = new Set([...gantt.querySelectorAll('[data-gantt-toggle][aria-expanded="false"]')]
        .map(button => button.closest('[data-gantt-group]').dataset.ganttGroup));
    const {scrollLeft, scrollTop} = gantt;
    controls.abort();
    controls = new AbortController();
    gantt.replaceWith(replacement);
    gantt = replacement;
    bindGantt(gantt, controls.signal);
    for (const button of gantt.querySelectorAll('[data-gantt-toggle]')) {
        if (collapsed.has(button.closest('[data-gantt-group]').dataset.ganttGroup)) button.click();
    }
    gantt.scrollLeft = scrollLeft;
    gantt.scrollTop = scrollTop;
});
