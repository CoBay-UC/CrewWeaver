<?php declare(strict_types=1); ?>
<?php
$heading = 'Important Dates';
$editableScopes = array_filter($options, static fn (array $option): bool => $option['can_edit']);
$actions = $editableScopes ? '<button type="button" class="btn btn-primary" data-date-new>New Important Date</button>' : '';
require __DIR__.'/../Components/page-header.php';
?>
<form method="get" class="row g-2 mb-4">
    <div class="col-md-4">
        <label for="dates-search" class="form-label">Search</label>
        <input id="dates-search" class="form-control" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
    </div>
    <div class="col-md">
        <label for="dates-scope" class="form-label">Scope</label>
        <select id="dates-scope" class="form-select" name="scope">
            <option value="">All scopes</option>
            <?php foreach ($options as $key => $option): ?>
                <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" <?= $selected === $key ? 'selected' : '' ?>><?= htmlspecialchars($option['name'], ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-6 col-md-auto">
        <label for="dates-period" class="form-label">Period</label>
        <select id="dates-period" class="form-select" name="period">
            <?php foreach (['' => 'All dates', 'upcoming' => 'Upcoming', 'past' => 'Past'] as $key => $label): ?>
                <option value="<?= $key ?>" <?= $period === $key ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-6 col-md-auto">
        <label for="dates-sort" class="form-label">Sort by</label>
        <select id="dates-sort" class="form-select" name="sort">
            <option value="soonest">Date ascending</option>
            <option value="latest" <?= $sort === 'latest' ? 'selected' : '' ?>>Date descending</option>
        </select>
    </div>
    <div class="col-auto align-self-end"><button class="btn btn-outline-secondary">Apply</button></div>
</form>
<?php if ($focusedId): ?><p><a href="/important-dates">Show all Important Dates</a></p><?php endif; ?>
<div class="list-group" data-date-results data-live-section="important-date-results">
    <?php foreach ($dateItems as $item): ?>
        <article class="list-group-item py-3" data-date-id="<?= (int) $item['id'] ?>">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                <div>
                    <h2 class="h6 mb-1"><?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                    <div class="small text-secondary">
                        <time datetime="<?= htmlspecialchars($item['event_date'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($item['event_date'], ENT_QUOTES, 'UTF-8') ?></time>
                        <?php if ($item['event_time'] && !$item['is_all_day']): ?> at <?= htmlspecialchars(substr($item['event_time'], 0, 5), ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                        <?php if ($item['end_date'] && $item['end_date'] !== $item['event_date']): ?> to <?= htmlspecialchars($item['end_date'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                        · <?= htmlspecialchars($options[$item['scope_key']]['name'] ?? $item['scope_name'], ENT_QUOTES, 'UTF-8') ?>
                        <?php if (!$item['is_active']): ?> · Inactive<?php endif; ?>
                    </div>
                    <?php if ($item['description']): ?><p class="mb-0 mt-2 cw-comment"><?= htmlspecialchars($item['description'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                </div>
                <?php if ($item['can_edit']): ?>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                data-date-edit="<?= htmlspecialchars(json_encode($item, JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR), ENT_QUOTES, 'UTF-8') ?>">Edit</button>
                        <button type="button" class="btn btn-sm btn-outline-danger" data-date-delete="<?= (int) $item['id'] ?>"
                                data-date-title="<?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>">Delete</button>
                    </div>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
    <?php if (!$dateItems): ?><p class="text-secondary">No matching Important Dates.</p><?php endif; ?>
</div>
<?php if ($editableScopes): ?>
<dialog class="cw-dialog" id="importantDateEditor" aria-labelledby="date-editor-title">
    <form class="d-flex flex-column">
        <div class="cw-dialog-header">
            <h2 class="h5 mb-0" id="date-editor-title">New Important Date</h2>
            <button type="button" class="btn-close" data-date-cancel aria-label="Close"></button>
        </div>
        <div class="cw-dialog-body">
            <div class="alert alert-danger" role="alert" tabindex="-1" data-date-error hidden></div>
            <div class="row g-3">
                <div class="col-12"><label class="form-label" for="date-title">Title</label><input class="form-control" id="date-title" name="title" required maxlength="240" autofocus></div>
                <div class="col-12">
                    <label class="form-label" for="date-scope">Calendar scope</label>
                    <select class="form-select" id="date-scope" name="scope" required>
                        <option value="">Select a scope</option>
                        <?php foreach ($editableScopes as $key => $option): ?>
                            <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($option['name'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Team dates appear on every calendar. Sub-team dates appear only on that sub-team’s calendar.</div>
                </div>
                <div class="col-sm-6"><label class="form-label" for="date-start">Start date</label><input class="form-control" id="date-start" name="event_date" type="date" required></div>
                <div class="col-sm-6"><label class="form-label" for="date-end">End date (optional)</label><input class="form-control" id="date-end" name="end_date" type="date"></div>
                <div class="col-sm-6"><label class="form-label" for="date-time">Start time (optional)</label><input class="form-control" id="date-time" name="event_time" type="time"></div>
                <div class="col-6 col-sm-3"><label class="form-label" for="date-color">Colour</label><input class="form-control form-control-color" id="date-color" name="colour" type="color" value="#b45309"></div>
                <div class="col-6 col-sm-3"><label class="form-label" for="date-priority">Priority</label><select class="form-select" id="date-priority" name="priority"><option value="normal">Normal</option><option value="low">Low</option><option value="high">High</option><option value="critical">Critical</option></select></div>
                <div class="col-12"><label class="form-label" for="date-description">Description (optional)</label><textarea class="form-control" id="date-description" name="description" rows="3" maxlength="16000"></textarea></div>
                <div class="col-12">
                    <div class="form-check"><input class="form-check-input" id="date-countdown" name="show_countdown" type="checkbox" checked><label class="form-check-label" for="date-countdown">Show countdown</label></div>
                    <div class="form-check"><input class="form-check-input" id="date-active" name="is_active" type="checkbox" checked><label class="form-check-label" for="date-active">Active</label></div>
                </div>
            </div>
        </div>
        <div class="cw-dialog-footer">
            <button type="button" class="btn btn-outline-secondary" data-date-cancel>Cancel</button>
            <button type="submit" class="btn btn-primary">Save date</button>
        </div>
    </form>
</dialog>
<script type="module" src="/assets/js/important-dates.js"></script>
<?php endif; ?>
