<?php declare(strict_types=1); ?>
<?php
$heading = ($scope['title'] ?? $scope['name']) . ' Gantt';
$eyebrow = ucfirst(str_replace('-', ' ', $scopeType));
$activeView = 'gantt';
ob_start();
require __DIR__.'/../Components/scope-views.php';
$actions = ob_get_clean();
require __DIR__.'/../Components/page-header.php';
$singleProject = count($rows) === 1;
?>
<div class="card">
    <div class="card-header bg-transparent d-flex flex-wrap gap-2 align-items-center">
        <button class="btn btn-sm btn-outline-secondary" data-gantt-today>Today</button>
        <button class="btn btn-sm btn-outline-secondary" data-gantt-start>Project start</button>
        <button class="btn btn-sm btn-outline-secondary" data-gantt-end>Project end</button>
        <div class="btn-group btn-group-sm ms-md-auto" role="group" aria-label="Gantt zoom">
            <?php foreach (['day', 'week', 'month', 'quarter'] as $zoom): ?>
                <button class="btn btn-outline-secondary" data-gantt-zoom="<?= $zoom ?>"><?= ucfirst($zoom) ?></button>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="cw-gantt" data-gantt data-today="<?= date('Y-m-d') ?>" tabindex="0" aria-label="Scrollable project timeline">
        <div class="cw-gantt-rows">
            <div class="cw-gantt-head">
                <div class="cw-gantt-row"><div class="cw-gantt-label fw-semibold">Date</div><div class="cw-gantt-axis" data-gantt-dates></div></div>
                <?php if ($singleProject): ?>
                    <div class="cw-gantt-row"><div class="cw-gantt-label">Project day</div><div class="cw-gantt-axis" data-gantt-days="<?= htmlspecialchars((string) $rows[0]['planned_start'], ENT_QUOTES, 'UTF-8') ?>"></div></div>
                <?php endif; ?>
            </div>
            <?php foreach ($rows as $project): ?>
                <?php $end = $project['calculated_completion'] ?: ($project['planned_start'] ?: ''); ?>
                <section class="cw-gantt-group" data-gantt-group="<?= (int) $project['id'] ?>">
                    <?php if (!$singleProject): ?>
                        <div class="cw-gantt-row"><div class="cw-gantt-label">Project day</div><div class="cw-gantt-axis" data-gantt-days="<?= htmlspecialchars((string) $project['planned_start'], ENT_QUOTES, 'UTF-8') ?>"></div></div>
                    <?php endif; ?>
                    <div class="cw-gantt-row">
                        <button class="cw-gantt-label fw-bold" data-gantt-toggle aria-expanded="true"><i class="bi bi-chevron-down me-1"></i><?= htmlspecialchars($project['title'], ENT_QUOTES, 'UTF-8') ?></button>
                        <div class="cw-gantt-track" data-project-track data-start="<?= htmlspecialchars((string) $project['planned_start'], ENT_QUOTES, 'UTF-8') ?>" data-end="<?= htmlspecialchars($end, ENT_QUOTES, 'UTF-8') ?>" data-progress="<?= (int) $project['progress'] ?>" data-color="<?= htmlspecialchars($project['colour'], ENT_QUOTES, 'UTF-8') ?>"></div>
                    </div>
                    <div data-gantt-tasks>
                        <?php foreach ($project['tasks'] as $task): ?>
                            <div class="cw-gantt-row">
                                <button class="cw-gantt-label ps-4" data-task-open="<?= (int) $task['id'] ?>" title="<?= htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8') ?></button>
                                <div class="cw-gantt-track" data-start="<?= htmlspecialchars((string) $task['planned_start'], ENT_QUOTES, 'UTF-8') ?>" data-end="<?= htmlspecialchars((string) $task['calculated_finish'], ENT_QUOTES, 'UTF-8') ?>" data-progress="<?= (int) $task['progress'] ?>" data-color="<?= htmlspecialchars($project['colour'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?php foreach ($task['milestones'] as $milestone): ?>
                                        <?php if ($milestone['calculated_date']): ?>
                                            <span class="cw-gantt-milestone" data-date="<?= htmlspecialchars($milestone['calculated_date'], ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($milestone['title'], ENT_QUOTES, 'UTF-8') ?>"></span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
            <?php if (!$rows): ?><p class="p-3 mb-0 text-secondary">No active projects in this scope.</p><?php endif; ?>
            <div class="cw-gantt-today" aria-hidden="true"></div>
        </div>
    </div>
</div>
<?php require __DIR__.'/../Components/task-modal.php'; ?>
<script type="module" src="/assets/js/gantt.js"></script>
