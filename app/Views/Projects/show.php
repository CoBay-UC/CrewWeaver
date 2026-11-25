<?php declare(strict_types=1);
use CrewWeaver\Models\ScopeDeletionRepository;
?>
<?php
$heading=$project['title'];
$description=$project['description'];
$eyebrow=trim($project['team_name'].' · '.($project['sub_team_name']??''),' ·');
$scopeUrl = $project['sub_team_id'] ? '/sub-teams/' . (int) $project['sub_team_id'] : '/teams/' . (int) $project['team_id'];
$management = '';
if ($canManageScope) {
    ob_start();
    ?>
    <button type="button" class="btn btn-outline-secondary" data-project-copy="<?= (int) $project['id'] ?>"
            data-team-id="<?= (int) $project['team_id'] ?>" data-sub-team-id="<?= (int) $project['sub_team_id'] ?>"
            data-scope-name="<?= htmlspecialchars($eyebrow, ENT_QUOTES, 'UTF-8') ?>"
            data-project-title="<?= htmlspecialchars($project['title'], ENT_QUOTES, 'UTF-8') ?>">Copy</button>
    <?php if (!$project['is_archived']): ?>
        <button type="button" class="btn btn-outline-secondary" data-project-edit="<?= (int) $project['id'] ?>"
                data-project-version="<?= (int) $project['version'] ?>"
                data-project-title="<?= htmlspecialchars($project['title'], ENT_QUOTES, 'UTF-8') ?>"
                data-project-description="<?= htmlspecialchars((string) $project['description'], ENT_QUOTES, 'UTF-8') ?>"
                data-scope-name="<?= htmlspecialchars($eyebrow, ENT_QUOTES, 'UTF-8') ?>">Edit project</button>
        <button type="button" class="btn btn-outline-danger" data-project-archive="<?= (int) $project['id'] ?>">Archive</button>
    <?php else: ?>
        <button type="button" class="btn btn-outline-primary" data-project-restore="<?= (int) $project['id'] ?>">Restore</button>
    <?php endif; ?>
    <button type="button" class="btn btn-outline-danger" data-project-delete="<?= (int) $project['id'] ?>"
            data-project-title="<?= htmlspecialchars($project['title'], ENT_QUOTES, 'UTF-8') ?>"
            data-delete-phrase="<?= htmlspecialchars(ScopeDeletionRepository::CONFIRMATION, ENT_QUOTES, 'UTF-8') ?>"
            data-scope-url="<?= htmlspecialchars($scopeUrl, ENT_QUOTES, 'UTF-8') ?>">Delete project</button>
    <?php
    $management = ob_get_clean();
}
$actions=$management.'<a class="btn btn-outline-secondary" href="/gantt/project/'.(int)$project['id'].'"><i class="bi bi-bar-chart-steps me-2"></i>Gantt</a><a class="btn btn-primary" href="/boards/project/'.(int)$project['id'].'"><i class="bi bi-kanban me-2"></i>Kanban</a>';
require __DIR__.'/../Components/page-header.php';
?>
<span data-project-page="<?= htmlspecialchars($scopeUrl, ENT_QUOTES, 'UTF-8') ?>" hidden></span>
<nav class="nav nav-underline mb-4" aria-label="Project views">
    <a class="nav-link active" href="/projects/<?= (int)$project['id'] ?>">Overview</a>
    <a class="nav-link" href="/boards/project/<?= (int)$project['id'] ?>">Kanban</a>
    <a class="nav-link" href="/gantt/project/<?= (int)$project['id'] ?>">Gantt</a>
    <a class="nav-link" href="/calendar/project/<?= (int)$project['id'] ?>">Calendar</a>
</nav>
<?php require __DIR__.'/../Components/countdowns.php'; ?>
<div class="row g-4" data-live-section="project-overview">
    <div class="col-lg-8"><div class="card">
        <div class="card-header bg-transparent d-flex justify-content-between"><h2 class="h5 mb-0">Tasks</h2><?php if($canManageScope && !$project['is_archived']): ?><button type="button" class="btn btn-sm btn-primary" data-task-create data-project-id="<?= (int)$project['id'] ?>"><i class="bi bi-plus-lg"></i> New task</button><?php endif; ?></div>
        <div class="list-group list-group-flush" data-project-tasks><?php foreach($tasks as $task): ?><button class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3" data-task-open="<?= (int)$task['id'] ?>"><span class="cw-priority cw-priority-<?= htmlspecialchars($task['priority'],ENT_QUOTES,'UTF-8') ?>"></span><span class="flex-grow-1 text-start"><strong><?= htmlspecialchars($task['title'],ENT_QUOTES,'UTF-8') ?></strong><span class="small text-secondary d-block"><?= htmlspecialchars((string)$task['owner_name'],ENT_QUOTES,'UTF-8') ?></span></span><span class="badge cw-status"><?= htmlspecialchars($task['status'],ENT_QUOTES,'UTF-8') ?></span><span class="small text-secondary"><?= (int)$task['progress'] ?>%</span></button><?php endforeach; ?></div>
    </div></div>
    <aside class="col-lg-4">
        <div class="card mb-4"><div class="card-body"><h2 class="h5">Schedule</h2><dl class="row small mb-0"><dt class="col-6">Start</dt><dd class="col-6"><?= htmlspecialchars((string)$project['planned_start'],ENT_QUOTES,'UTF-8') ?></dd><dt class="col-6">Calculated finish</dt><dd class="col-6"><?= htmlspecialchars((string)$project['calculated_completion'],ENT_QUOTES,'UTF-8') ?></dd><dt class="col-6">Target</dt><dd class="col-6"><?= htmlspecialchars((string)$project['target_completion'],ENT_QUOTES,'UTF-8') ?></dd><dt class="col-6">Progress</dt><dd class="col-6"><?= (int)$project['progress'] ?>%</dd></dl></div></div>
        <div class="card"><div class="card-body"><h2 class="h5">Members</h2><div class="d-flex flex-wrap gap-2"><?php foreach($project['members'] as $member): ?><span class="badge rounded-pill text-bg-light border"><span class="cw-avatar cw-avatar-sm me-1"><?= htmlspecialchars(strtoupper(substr($member['display_name'],0,1)),ENT_QUOTES,'UTF-8') ?></span><?= htmlspecialchars($member['display_name'],ENT_QUOTES,'UTF-8') ?></span><?php endforeach; ?></div></div></div>
    </aside>
</div>
<?php require __DIR__.'/../Components/task-modal.php'; ?>
<?php if ($canManageScope): ?>
    <?php require __DIR__.'/../Components/project-editor.php'; ?>
    <?php require __DIR__.'/../Components/task-create-dialog.php'; ?>
<?php endif; ?>
