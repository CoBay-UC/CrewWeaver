import assert from 'node:assert/strict';
import {createRequire} from 'node:module';
import {setTimeout as delay} from 'node:timers/promises';

const require = createRequire(import.meta.url);
const {chromium} = require(process.env.CREWWEAVER_PLAYWRIGHT_PATH || 'playwright-core');
const base = process.env.CREWWEAVER_TEST_URL;
assert.ok(base && new URL(base).hostname === '127.0.0.1', 'Use an isolated loopback deployment with development fixtures.');
const browser = await chromium.launch({
    executablePath: process.env.CREWWEAVER_BROWSER_PATH || undefined,
    headless: true,
    args: ['--disable-gpu'],
});
const page = await browser.newPage({viewport: {width: 1150, height: 850}});
page.setDefaultTimeout(8000);
const browserErrors = [];
const browserPrompts = [];
const confirmationPhrase = 'I understand this permanently deletes the data and cannot be undone.';
page.on('pageerror', error => browserErrors.push(error.message));
page.on('dialog', dialog => { browserPrompts.push(dialog.type()); return dialog.dismiss(); });
const marker = 'Browser check ' + Date.now();
let teamId, subTeamId;

async function until(test) {
    for (let attempt = 0; attempt < 60; attempt++) {
        if (await test()) return;
        await delay(100);
    }
    assert.fail('Timed out waiting for the expected browser state');
}

async function open(path) {
    const response = await page.goto(base + path);
    assert.equal(response.status(), 200);
}

async function mutate(path, method, body) {
    const token = await page.locator('meta[name="csrf-token"]').getAttribute('content');
    return page.request.fetch(base + path, {
        method,
        headers: {'X-CSRF-Token': token},
        data: body,
    });
}

async function saveScope(button, name, method = 'POST', icon = null) {
    await button.click();
    const editor = page.locator('#scopeEditor');
    await editor.locator('[name=name]').fill(name);
    if (icon) {
        await editor.getByRole('button', {name: 'Browse icons'}).click();
        await editor.getByRole('searchbox', {name: 'Search Bootstrap Icons'}).fill(icon.replaceAll('-', ' '));
        await editor.getByRole('button', {name: icon, exact: true}).click();
        assert.equal(await editor.locator('[name=icon]').inputValue(), icon);
        assert.ok((await editor.locator('[data-icon-preview]').getAttribute('class')).includes('bi-' + icon));
    }
    const response = page.waitForResponse(reply =>
        /\/api\/(?:sub-)?teams/.test(reply.url()) && reply.request().method() === method);
    await editor.getByRole('button', {name: 'Save', exact: true}).click();
    const reply = await response;
    assert.ok(reply.ok(), await reply.text());
    await editor.waitFor({state: 'hidden'});
    return (await reply.json()).data;
}

try {
    await page.emulateMedia({colorScheme: 'light'});
    await open('/boards/project/1');
    const theme = page.locator('[data-theme-toggle]');
    const panel = page.locator('.cw-board-toolbar');
    const shape = await panel.evaluate(element => ({
        radius: getComputedStyle(element).borderTopLeftRadius,
        clip: getComputedStyle(element).clipPath,
    }));
    assert.equal(shape.radius, '6.8px');
    assert.match(shape.clip, /100% 10px/);
    const buttonShape = await theme.evaluate(element => ({
        radius: getComputedStyle(element).borderTopLeftRadius,
        clip: getComputedStyle(element).clipPath,
    }));
    assert.deepEqual(buttonShape, {radius: '4px', clip: 'none'});
    await page.locator('body').evaluate(body => { body.dataset.themeTest = 'present'; });
    await theme.click();
    assert.equal(await page.locator('html').getAttribute('data-bs-theme'), 'dark');
    const contrast = await page.locator('.cw-task-card .text-secondary').first().evaluate(element => {
        const luminance = colour => {
            const scaled = colour.match(/[\d.]+/g).slice(0, 3).map(channel => Number(channel) / 255)
                .map(channel => channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4);
            return scaled[0] * 0.2126 + scaled[1] * 0.7152 + scaled[2] * 0.0722;
        };
        const pair = [getComputedStyle(element).color, getComputedStyle(element.closest('.card')).backgroundColor]
            .map(luminance).sort((a, b) => b - a);
        return (pair[0] + 0.05) / (pair[1] + 0.05);
    });
    assert.ok(contrast >= 4.5, 'Dark muted text needs at least 4.5:1 contrast on cards');
    assert.equal(await theme.getAttribute('aria-label'), 'Switch to light theme');
    assert.equal(await page.locator('body').getAttribute('data-theme-test'), 'present');
    await page.reload();
    assert.equal(await page.locator('html').getAttribute('data-bs-theme'), 'dark');
    await theme.click();
    assert.equal(await page.locator('html').getAttribute('data-bs-theme'), 'light');
    const userMenu = page.locator('[data-bs-toggle="dropdown"]');
    await userMenu.click();
    assert.equal(await page.getByRole('button', {name: 'Light theme', exact: true}).getAttribute('aria-pressed'), 'true');
    await page.getByRole('button', {name: 'System theme'}).click();
    await page.emulateMedia({colorScheme: 'dark'});
    await until(async () => (await page.locator('html').getAttribute('data-bs-theme')) === 'dark');
    await page.emulateMedia({colorScheme: 'light'});
    await until(async () => (await page.locator('html').getAttribute('data-bs-theme')) === 'light');
    assert.deepEqual(await page.locator('nav[aria-label="Scope views"] a').allTextContents(), ['Kanban', 'Gantt', 'Calendar']);
    const header = await page.locator('.cw-kanban-header').boundingBox();
    const lane = await page.locator('.cw-swimlane').first().boundingBox();
    assert.ok(header.y + header.height <= lane.y + 1, 'Kanban headers must precede the first swimlane');
    await page.locator('[data-task-open="1"]').click();
    await until(async () => (await page.locator('[data-task-form] [name="title"]').inputValue({timeout: 1000}).catch(() => '')) === 'Product research');
    const progressSlider = page.getByRole('slider', {name: 'Progress'});
    await progressSlider.press('End');
    assert.equal(await page.locator('[data-task-progress]').textContent(), '100%');
    await progressSlider.press('ArrowLeft');
    assert.equal(await page.locator('[data-task-progress]').textContent(), '99%');
    const progressSaved = page.waitForResponse(reply => reply.url().endsWith('/api/tasks/1') && reply.request().method() === 'PATCH');
    await page.getByRole('button', {name: 'Save changes'}).click();
    assert.ok((await progressSaved).ok());
    await until(async () => (await page.locator('[data-task-progress]').textContent({timeout: 500}).catch(() => '')) === '99%');
    await page.getByRole('tab', {name: 'Research', exact: true}).click();
    await page.getByRole('link', {name: 'https://example.com/reference'}).waitFor();
    const boardSearch = await page.request.get(base + '/api/boards/project/1?search=Mechanical');
    assert.ok(boardSearch.ok(), await boardSearch.text());
    assert.equal((await boardSearch.json()).data.tasks.length, 1);

    await open('/gantt/project/1');
    await page.getByRole('button', {name: 'Project start', exact: true}).click();
    assert.equal(await page.locator('[data-gantt-days] .cw-gantt-tick').first().textContent(), '1');
    assert.ok(await page.locator('[data-gantt-dates] .cw-gantt-tick').count() > 10);
    const taskLabel = page.getByRole('button', {name: 'Product research'});
    const taskLabelBefore = await taskLabel.boundingBox();
    await page.getByRole('button', {name: 'Project end', exact: true}).click();
    const taskLabelAfter = await taskLabel.boundingBox();
    assert.ok(Math.abs(taskLabelBefore.x - taskLabelAfter.x) < 2, 'Task labels must remain pinned');
    assert.ok(await page.locator('[data-gantt]').evaluate(element => element.scrollLeft > 0));

    await open('/calendar/team/1');
    await page.locator('.fc-daygrid').waitFor();
    const feed = await page.request.get(base + '/api/calendar/team/1?start=2027-01-01&end=2027-02-01');
    assert.ok(feed.ok(), await feed.text());
    const events = (await feed.json()).data;
    assert.ok(events.some(event => event.id === 'project-start-1'));
    assert.ok(events.some(event => event.id === 'task-start-1'));
    assert.ok(events.some(event => event.id === 'milestone-1'));
    assert.ok(events.some(event => event.title === 'Prototype review'));
    assert.ok(!events.some(event => event.id === 'project-start-3'), 'Archived work must stay out of the calendar');
    const visibleMonth = await page.locator('.fc-daygrid-day[data-date]').nth(15).getAttribute('data-date');
    const [year, month] = visibleMonth.split('-').map(Number);
    const monthOffset = (2027 - year) * 12 + 1 - month;
    if (Math.abs(monthOffset) <= 24) {
        for (let index = 0; index < Math.abs(monthOffset); index++) {
            await page.locator(monthOffset > 0 ? '.fc-next-button' : '.fc-prev-button').click();
        }
        const researchEvent = page.getByText('Task start: Product research', {exact: true});
        await researchEvent.click();
        await page.getByRole('heading', {name: 'Product research', exact: true}).waitFor();
        await page.locator('#taskModal .modal-footer').getByRole('button', {name: 'Close'}).click();
        await page.locator('#taskModal').waitFor({state: 'hidden'});
    }
    await page.getByRole('button', {name: 'List All', exact: true}).click();
    const allDates = page.getByRole('region', {name: 'All calendar dates'});
    await allDates.getByRole('heading', {name: 'Friday January 1, 2027', exact: true}).waitFor();
    await page.locator('[data-calendar]').waitFor({state: 'hidden'});
    assert.deepEqual(await allDates.getByRole('heading', {level: 2}).allTextContents(), ['January 2027', 'February 2027']);
    await allDates.getByRole('button', {name: '[Atlas Prototype] Task start: Product research', exact: true}).click();
    await page.getByRole('heading', {name: 'Product research', exact: true}).waitFor();
    await page.locator('#taskModal .modal-footer').getByRole('button', {name: 'Close'}).click();
    await page.locator('#taskModal').waitFor({state: 'hidden'});
    await page.getByRole('button', {name: 'List', exact: true}).click();
    await page.locator('.fc-list').waitFor();
    await allDates.waitFor({state: 'hidden'});
    if (Math.abs(monthOffset) <= 24) {
        await page.locator('.fc-list').getByText('[Atlas Prototype] Milestone: Find three similar products', {exact: true}).waitFor();
    }
    for (const [scope, label] of [
        ['team/2', '[Launch::Launch Readiness] Task start: Publish runbook'],
        ['sub-team/3', '[Launch Readiness] Task start: Publish runbook'],
    ]) {
        await open('/calendar/' + scope);
        await page.getByRole('button', {name: 'List All', exact: true}).click();
        await allDates.getByRole('button', {name: label, exact: true}).waitFor();
    }

    await open('/admin/teams');
    const navChildren = page.locator('[data-organization-nav] .cw-nav-children a');
    assert.ok(await navChildren.count() >= 3);
    const archive = await page.locator('.cw-sidebar-archive').boundingBox();
    assert.ok(archive.y + archive.height > 830, 'Archive should sit at the bottom of the sidebar');
    const team = await saveScope(page.getByRole('button', {name: 'New team', exact: true}), marker, 'POST', 'gear-fill');
    assert.equal(team.icon, 'gear-fill');
    teamId = team.id;
    const teamSection = page.locator('[data-managed-teams] section').filter({has: page.getByRole('heading', {name: marker, exact: true})});
    const subTeam = await saveScope(teamSection.getByRole('button', {name: 'New sub-team'}), marker + ' child', 'POST', 'lightning-charge');
    assert.equal(subTeam.icon, 'lightning-charge');
    subTeamId = subTeam.id;
    const subTeamRow = teamSection.getByRole('listitem').filter({hasText: marker + ' child'});
    await saveScope(subTeamRow.getByRole('button', {name: 'Edit'}), marker + ' renamed', 'PATCH');
    await navChildren.getByText(marker + ' renamed', {exact: true}).waitFor();

    await page.getByLabel('Show sub-teams in the sidebar').uncheck();
    await page.getByRole('button', {name: 'Save navigation'}).click();
    await until(async () => await navChildren.count() === 0);
    await page.reload();
    assert.equal(await navChildren.count(), 0);
    await page.getByLabel('Show sub-teams in the sidebar').check();
    await page.getByRole('button', {name: 'Save navigation'}).click();
    await until(async () => await navChildren.count() > 0);

    await open('/admin/users?search=Morgan');
    await page.getByRole('button', {name: 'Memberships', exact: true}).click();
    const membership = page.locator('#membershipsEditor');
    await membership.getByText('Product Engineering / Mechanical').waitFor();
    for (const [scope, label] of [[teamId + ':0', marker + ' (team)'], [teamId + ':' + subTeamId, marker + ' / ' + marker + ' renamed']]) {
        await membership.getByLabel('Team or sub-team').selectOption(scope);
        await membership.getByRole('button', {name: 'Add membership'}).click();
        await membership.locator('[data-membership-list]').getByText(label, {exact: true}).waitFor();
    }
    const stored = await page.request.get(base + '/api/users/2/memberships');
    assert.equal((await stored.json()).data.filter(record => String(record.team_id) === String(teamId)).length, 2);
    for (const label of ['Remove ' + marker + ' (team)', 'Remove ' + marker + ' / ' + marker + ' renamed']) {
        const button = membership.getByRole('button', {name: label, exact: true});
        await button.click();
        const confirmation = page.getByRole('dialog', {name: 'Remove membership'});
        await confirmation.getByRole('button', {name: 'Cancel'}).click();
        assert.equal(await button.count(), 1);
        await button.click();
        await confirmation.getByRole('button', {name: 'Remove', exact: true}).click();
        await button.waitFor({state: 'detached'});
    }

    await page.setViewportSize({width: 390, height: 844});
    await open('/teams/' + teamId);
    await page.getByRole('button', {name: 'New Project', exact: true}).click();
    const editor = page.getByRole('dialog', {name: 'New project'});
    assert.equal(await editor.getByLabel('Project title').evaluate(element => document.activeElement === element), true);
    await page.keyboard.press('Escape');
    await editor.waitFor({state: 'hidden'});
    await page.getByRole('button', {name: 'New Project', exact: true}).click();
    await editor.getByLabel('Project title').fill(marker + ' direct project');
    await editor.getByLabel('Planned start').fill('2027-01-03');
    await editor.getByRole('textbox', {name: 'Description (optional)'}).fill('Created from a mobile dialog');
    await page.route('**/api/projects', route => route.fulfill({
        status: 422,
        json: {success: false, message: 'Validation failed.', errors: {title: ['Test validation message.']}},
    }), {times: 1});
    await editor.getByRole('button', {name: 'Create project'}).click();
    await editor.getByRole('alert').getByText('Test validation message.').waitFor();
    assert.equal(await editor.getByLabel('Project title').inputValue(), marker + ' direct project');
    await editor.getByRole('button', {name: 'Create project'}).click();
    await page.getByRole('heading', {name: marker + ' direct project'}).waitFor();
    const directProjectId = Number(page.url().split('/').at(-1));
    await page.getByRole('button', {name: 'New task'}).click();
    const taskEditor = page.getByRole('dialog', {name: 'New task'});
    await taskEditor.getByLabel('Task title').fill(marker + ' direct task');
    await taskEditor.getByLabel('Estimated duration (days)').fill('2');
    await taskEditor.getByRole('button', {name: 'Create task'}).click();
    await page.getByRole('heading', {name: marker + ' direct task'}).waitFor();
    const taskModal = page.locator('#taskModal');
    const directTaskId = Number(await page.locator('[data-project-tasks]').getByRole('button', {name: new RegExp(marker + ' direct task')}).getAttribute('data-task-open'));
    await taskModal.locator('.modal-footer').getByRole('button', {name: 'Close'}).click();
    await taskModal.waitFor({state: 'hidden'});
    await page.getByRole('button', {name: 'Copy', exact: true}).click();
    const copyEditor = page.getByRole('dialog', {name: 'Copy project'});
    await copyEditor.getByLabel('Project title').fill(marker + ' copy');
    await copyEditor.getByRole('button', {name: 'Copy project'}).click();
    await page.getByRole('heading', {name: marker + ' copy'}).waitFor();
    const copiedProjectId = Number(page.url().split('/').at(-1));
    await page.getByRole('button', {name: 'Edit project', exact: true}).click();
    const editEditor = page.getByRole('dialog', {name: 'Edit project'});
    await editEditor.getByLabel('Project title').fill(marker + ' renamed copy');
    await editEditor.getByRole('button', {name: 'Save changes'}).click();
    await page.getByRole('heading', {name: marker + ' renamed copy', exact: true}).waitFor();
    await page.getByRole('button', {name: 'Archive', exact: true}).click();
    const archiveDialog = page.getByRole('dialog', {name: 'Archive project'});
    await archiveDialog.getByRole('button', {name: 'Cancel'}).click();
    assert.ok(page.url().endsWith('/projects/' + copiedProjectId));
    await page.getByRole('button', {name: 'Archive', exact: true}).click();
    await archiveDialog.getByRole('button', {name: 'Archive', exact: true}).click();
    await page.waitForURL('**/archive');
    await page.locator('[data-project-restore="' + copiedProjectId + '"]').click();
    const restoreDialog = page.getByRole('dialog', {name: 'Restore project'});
    await restoreDialog.getByRole('button', {name: 'Restore', exact: true}).click();
    await page.locator('[data-project-restore="' + copiedProjectId + '"]').waitFor({state: 'detached'});
    assert.ok((await mutate('/api/projects/' + copiedProjectId + '/archive', 'POST')).ok());

    await open('/sub-teams/' + subTeamId);
    assert.ok(await page.locator('main').getByRole('link', {name: '0 archived projects'}).count());
    await page.getByRole('button', {name: 'New Project', exact: true}).click();
    await editor.getByLabel('Project title').fill(marker + ' child project');
    await editor.getByLabel('Planned start').fill('2027-01-03');
    await editor.getByRole('button', {name: 'Create project'}).click();
    await page.getByRole('heading', {name: marker + ' child project'}).waitFor();
    const childProjectId = Number(page.url().split('/').at(-1));
    const childProject = (await (await page.request.get(base + '/api/projects/' + childProjectId)).json()).data;
    assert.equal(Number(childProject.team_id), Number(teamId));
    assert.equal(Number(childProject.sub_team_id), Number(subTeamId));
    const childProjectContext = '[' + marker + ' renamed]::[' + childProject.title + ']';
    await open('/');
    await page.getByRole('region', {name: 'Recent organization activity'}).getByRole('row').filter({hasText: 'Project created'})
        .getByRole('cell', {name: childProjectContext, exact: true}).waitFor();
    for (const path of ['/projects/' + childProjectId, '/boards/project/' + childProjectId]) {
        await open(path);
        assert.equal(await page.locator('main').getByText(/\d+ archived projects/).count(), 0);
    }

    async function createDate(scope, title, date) {
        await open('/important-dates?scope=' + scope);
        await page.getByRole('button', {name: 'New Important Date', exact: true}).click();
        const dateEditor = page.getByRole('dialog', {name: 'New Important Date'});
        assert.equal(await dateEditor.getByLabel('Calendar scope').inputValue(), scope);
        await dateEditor.getByLabel('Title').fill(title);
        await dateEditor.getByLabel('Start date').fill(date);
        await dateEditor.getByRole('button', {name: 'Save date'}).click();
        await page.getByRole('heading', {name: title}).waitFor();
    }

    const teamDateTitle = marker + ' team date';
    const subTeamDateTitle = marker + ' sub-team date';
    await createDate('team:' + teamId, teamDateTitle, '2027-01-14');
    await createDate('sub_team:' + subTeamId, subTeamDateTitle, '2026-11-25');
    await open('/calendar/sub-team/' + subTeamId);
    await page.getByRole('button', {name: 'List All', exact: true}).click();
    await allDates.getByRole('heading', {name: 'Wednesday November 25, 2026', exact: true}).waitFor();
    await allDates.getByRole('link', {name: subTeamDateTitle, exact: true}).click();
    await page.getByRole('heading', {name: subTeamDateTitle, exact: true}).waitFor();
    await page.getByRole('button', {name: 'Edit', exact: true}).click();
    const dateEditor = page.getByRole('dialog', {name: 'Edit Important Date'});
    await dateEditor.getByLabel('Start date').fill('2027-01-16');
    await dateEditor.getByRole('button', {name: 'Save date'}).click();
    await page.locator('[data-date-results]').getByText('2027-01-16').waitFor();

    for (const [path, showSubTeam] of [
        ['team/' + teamId, false], ['team/1', false], ['sub-team/' + subTeamId, true],
        ['sub-team/1', false], ['project/' + childProjectId, false], ['project/1', false],
    ]) {
        const response = await page.request.get(base + '/api/calendar/' + path + '?start=2027-01-01&end=2027-02-01');
        assert.ok(response.ok(), await response.text());
        const calendarEvents = (await response.json()).data;
        assert.equal(calendarEvents.filter(event => event.title === teamDateTitle).length, 1, path);
        assert.equal(calendarEvents.some(event => event.title === subTeamDateTitle && event.start === '2027-01-16'), showSubTeam, path);
    }
    await page.getByRole('button', {name: 'Delete', exact: true}).click();
    await page.getByRole('dialog', {name: 'Delete Important Date'}).getByRole('button', {name: 'Delete', exact: true}).click();
    await page.getByRole('heading', {name: subTeamDateTitle}).waitFor({state: 'detached'});
    assert.equal(await page.getByText('No matching Important Dates.').count(), 1);

    await open('/');
    const activity = page.getByRole('region', {name: 'Recent organization activity'});
    await activity.getByRole('row').filter({hasText: 'Important date deleted'})
        .getByRole('cell', {name: subTeamDateTitle, exact: true}).waitFor();

    const childTaskReply = await mutate('/api/tasks', 'POST', {
        project_id: childProjectId, title: marker + ' child task', estimated_duration_days: 3,
    });
    assert.ok(childTaskReply.ok(), await childTaskReply.text());
    const childTaskId = (await childTaskReply.json()).data.task.id;
    const csrf = await page.locator('meta[name="csrf-token"]').getAttribute('content');
    const uploaded = await page.request.post(base + '/api/tasks/' + childTaskId + '/attachments', {
        headers: {'X-CSRF-Token': csrf},
        multipart: {file: {name: 'test.txt', mimeType: 'text/plain', buffer: Buffer.from('Disposable browser test attachment.')}},
    });
    assert.ok(uploaded.ok(), await uploaded.text());
    const attachmentId = (await uploaded.json()).data.id;
    assert.equal((await page.request.get(base + '/files/' + attachmentId + '/download')).status(), 200);

    const viewer = await browser.newPage({viewport: {width: 390, height: 844}});
    viewer.setDefaultTimeout(8000);
    viewer.on('pageerror', error => browserErrors.push(error.message));
    await viewer.goto(base + '/boards/project/' + childProjectId);
    await viewer.locator('body').evaluate(body => { body.dataset.syncCheck = 'same-document'; });
    const card = viewer.locator('[data-task-id="' + childTaskId + '"]');
    await card.getByRole('button', {name: marker + ' child task', exact: true}).waitFor();
    await card.getByRole('combobox', {name: 'Change status'}).selectOption('Researching');
    await until(async () => (await (await page.request.get(base + '/api/tasks/' + childTaskId)).json()).data.status === 'Researching');
    await open('/projects/' + childProjectId);
    await page.locator('[data-task-open="' + childTaskId + '"]').click();
    const taskForm = page.locator('[data-task-form]');
    await taskForm.locator('[name="description"]').fill('Draft from the other session');
    await card.getByRole('combobox', {name: 'Change status'}).selectOption('Done');
    await until(async () => (await (await page.request.get(base + '/api/tasks/' + childTaskId)).json()).data.status === 'Done');
    assert.equal(await taskForm.locator('[name="description"]').inputValue(), 'Draft from the other session');
    await page.getByRole('button', {name: 'Save changes', exact: true}).click();
    await until(async () => {
        const task = (await (await page.request.get(base + '/api/tasks/' + childTaskId)).json()).data;
        return task.description === 'Draft from the other session' && task.status === 'Done';
    });
    const latestTask = (await (await page.request.get(base + '/api/tasks/' + childTaskId)).json()).data;
    const renameTask = await mutate('/api/tasks/' + childTaskId, 'PATCH', {title: marker + ' synchronized task', version: latestTask.version});
    assert.ok(renameTask.ok(), await renameTask.text());
    await viewer.bringToFront();
    await card.getByRole('button', {name: marker + ' synchronized task', exact: true}).waitFor();
    assert.equal(await viewer.locator('body').getAttribute('data-sync-check'), 'same-document');
    await viewer.close();

    await taskForm.locator('[name="title"]').fill(marker + ' local title');
    const otherTask = (await (await page.request.get(base + '/api/tasks/' + childTaskId)).json()).data;
    assert.ok((await mutate('/api/tasks/' + childTaskId, 'PATCH', {title: marker + ' remote title', version: otherTask.version})).ok());
    await page.getByRole('button', {name: 'Save changes', exact: true}).click();
    await page.locator('[data-task-save-error]').getByText(/Another edit changed title/).waitFor();
    assert.equal(await taskForm.locator('[name="title"]').inputValue(), marker + ' local title');
    assert.equal((await (await page.request.get(base + '/api/tasks/' + childTaskId)).json()).data.title, marker + ' remote title');
    await page.getByRole('button', {name: 'Save changes', exact: true}).click();
    await until(async () => (await (await page.request.get(base + '/api/tasks/' + childTaskId)).json()).data.title === marker + ' local title');

    const doomedTitle = marker + ' <draft> to delete';
    const doomed = await mutate('/api/projects', 'POST', {team_id: teamId, title: doomedTitle, status: 'Planned'});
    assert.ok(doomed.ok(), await doomed.text());
    const doomedId = (await doomed.json()).data.id;
    const doomedTask = await mutate('/api/tasks', 'POST', {project_id: doomedId, title: marker + ' disposable task'});
    assert.ok(doomedTask.ok(), await doomedTask.text());
    const doomedTaskId = (await doomedTask.json()).data.task.id;
    assert.ok((await mutate('/api/projects/' + doomedId + '/archive', 'POST')).ok());
    const archivedTask = (await (await page.request.get(base + '/api/tasks/' + doomedTaskId)).json()).data;
    assert.equal(archivedTask.permissions.edit, false);
    assert.equal((await mutate('/api/projects/' + doomedId, 'DELETE', {confirmation: 'incorrect'})).status(), 422);
    await open('/projects/' + doomedId);
    await page.getByRole('button', {name: 'Delete project', exact: true}).click();
    const projectDelete = page.getByRole('dialog', {name: 'Delete project'});
    const deleteProject = projectDelete.getByRole('button', {name: 'Permanently delete'});
    assert.ok(await deleteProject.isDisabled());
    await projectDelete.getByLabel('Type this sentence to confirm').fill(confirmationPhrase);
    await deleteProject.click();
    await page.waitForURL('**/teams/' + teamId);
    assert.equal((await page.request.get(base + '/api/projects/' + doomedId)).status(), 404);
    assert.equal((await page.request.get(base + '/api/tasks/' + doomedTaskId)).status(), 404);
    await open('/');
    for (const action of ['Project archived', 'Project deleted']) {
        await activity.getByRole('row').filter({hasText: action})
            .getByRole('cell', {name: '[' + doomedTitle + ']', exact: true}).waitFor();
    }
    const statusRow = activity.getByRole('row').filter({hasText: 'Researching → Done'});
    await statusRow.getByText(marker + ' child task', {exact: true}).waitFor();
    await statusRow.getByText(childProjectContext, {exact: true}).waitFor();
    assert.ok((await statusRow.textContent()).includes('Task status changed'));

    await page.setViewportSize({width: 1150, height: 850});
    await open('/admin/teams');
    const scopeEditor = page.locator('#scopeEditor');
    const unconfirmed = await mutate('/api/sub-teams/' + subTeamId, 'DELETE', {confirmation: 'incorrect'});
    assert.equal(unconfirmed.status(), 422);
    assert.equal((await page.request.get(base + '/api/projects/' + childProjectId)).status(), 200);
    for (const [container, type] of [[subTeamRow, 'sub-teams'], [teamSection, 'teams']]) {
        const target = type === 'sub-teams'
            ? teamSection.getByRole('listitem').filter({hasText: marker + ' renamed'})
            : container;
        await target.locator('[data-scope-delete="' + type + '"]').click();
        const destroyButton = scopeEditor.getByRole('button', {name: 'Permanently delete', exact: true});
        assert.ok(await destroyButton.isDisabled());
        await scopeEditor.getByLabel('Type this sentence to confirm').fill(confirmationPhrase);
        const response = page.waitForResponse(reply => reply.request().method() === 'DELETE' && reply.url().includes('/api/' + type + '/'));
        await destroyButton.click();
        const reply = await response;
        assert.ok(reply.ok(), await reply.text());
        const result = (await reply.json()).data;
        assert.equal(result.pendingFiles, 0);
        await target.waitFor({state: 'detached'});
        if (type === 'sub-teams') {
            subTeamId = null;
            assert.deepEqual(result.deleted, {projects: 1, tasks: 1, attachments: 1});
            assert.equal((await page.request.get(base + '/api/projects/' + childProjectId)).status(), 404);
            assert.equal((await page.request.get(base + '/files/' + attachmentId + '/download')).status(), 404);
            assert.equal((await page.request.get(base + '/api/projects/' + directProjectId)).status(), 200);
            const descendant = await mutate('/api/sub-teams', 'POST', {team_id: teamId, name: marker + ' descendant'});
            assert.ok(descendant.ok(), await descendant.text());
        } else {
            teamId = null;
            assert.deepEqual(result.deleted, {projects: 2, tasks: 2, attachments: 0});
            assert.equal((await page.request.get(base + '/api/projects/' + copiedProjectId)).status(), 404);
            assert.equal((await page.request.get(base + '/api/tasks/' + directTaskId)).status(), 404);
        }
    }

    await page.setViewportSize({width: 390, height: 844});
    await open('/calendar/sub-team/3');
    await theme.click();
    assert.equal(await page.locator('html').getAttribute('data-bs-theme'), 'dark');
    await theme.click();
    await page.locator('.fc-list').waitFor();
    await page.getByRole('button', {name: 'List All', exact: true}).click();
    await allDates.getByRole('heading', {name: 'February 2027', exact: true}).waitFor();
    await page.locator('[data-calendar]').waitFor({state: 'hidden'});
    await page.getByRole('button', {name: 'Open navigation'}).click();
    await page.getByRole('link', {name: 'Launch', exact: true}).waitFor();
    assert.deepEqual(browserErrors, []);
    assert.deepEqual(browserPrompts, []);
    console.log('Desktop and mobile browser checks passed for calendar labels, two-session synchronization, conflict recovery, project and scope CRUD, memberships, icons, and theme contrast.');
} finally {
    if (subTeamId || teamId) {
        if (subTeamId) await mutate('/api/memberships', 'DELETE', {user_id: 2, team_id: teamId, sub_team_id: subTeamId}).catch(() => {});
        if (teamId) await mutate('/api/memberships', 'DELETE', {user_id: 2, team_id: teamId, sub_team_id: null}).catch(() => {});
        if (subTeamId) await mutate('/api/sub-teams/' + subTeamId, 'DELETE', {confirmation: confirmationPhrase}).catch(() => {});
        if (teamId) await mutate('/api/teams/' + teamId, 'DELETE', {confirmation: confirmationPhrase}).catch(() => {});
    }
    await browser.close();
}
