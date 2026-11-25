<?php declare(strict_types=1); ?>
<?php
$heading = $team['name'];
$description = $team['description'];
$eyebrow = 'Team';
$scopeType = 'team';
$scopeId = (int) $team['id'];
$activeView = '';
ob_start();
if ($canManageScope): ?>
    <button type="button" class="btn btn-outline-primary" data-project-create data-team-id="<?= $scopeId ?>"
            data-scope-name="<?= htmlspecialchars($team['name'], ENT_QUOTES, 'UTF-8') ?>">New Project</button>
<?php endif;
require __DIR__.'/../Components/scope-views.php';
$actions = ob_get_clean();
require __DIR__.'/../Components/page-header.php';
if ($canManageScope) {
    require __DIR__.'/../Components/project-editor.php';
}
require __DIR__.'/../Components/countdowns.php';
?>
<div class="d-flex flex-wrap gap-3 mb-3" data-live-section="scope-links">
    <a href="/important-dates?scope=team:<?= $scopeId ?>">Important Dates</a>
    <a href="/archive?team_id=<?= $scopeId ?>"><i class="bi bi-archive me-1"></i><?= (int) $archivedCount ?> archived projects</a>
</div>
<div class="row g-4" data-live-section="scope-projects">
    <section class="col-xl-8" aria-labelledby="team-projects">
        <div class="card">
            <h2 class="card-header h5" id="team-projects">Active projects</h2>
            <div class="list-group list-group-flush">
                <?php foreach ($projects as $project): ?>
                    <a class="list-group-item list-group-item-action py-3" href="/projects/<?= (int) $project['id'] ?>">
                        <div class="d-flex gap-3 align-items-center">
                            <span class="cw-project-dot" style="background:<?= htmlspecialchars($project['colour'], ENT_QUOTES, 'UTF-8') ?>"></span>
                            <div class="flex-grow-1">
                                <strong><?= htmlspecialchars($project['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <div class="small text-secondary"><?= htmlspecialchars($project['sub_team_name'] ?? 'Direct team project', ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                            <span class="badge cw-status"><?= htmlspecialchars($project['status'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="small"><?= (int) $project['done_count'] ?>/<?= (int) $project['task_count'] ?> tasks</span>
                        </div>
                    </a>
                <?php endforeach; ?>
                <?php if (!$projects): ?><p class="p-3 mb-0 text-secondary">No active projects.</p><?php endif; ?>
            </div>
        </div>
    </section>
    <aside class="col-xl-4" aria-labelledby="team-sub-teams">
        <div class="card">
            <h2 class="card-header h5" id="team-sub-teams">Sub-teams</h2>
            <div class="list-group list-group-flush">
                <?php foreach ($team['sub_teams'] as $subTeam): ?>
                    <div class="list-group-item d-flex align-items-center gap-2">
                        <i class="bi bi-<?= htmlspecialchars($subTeam['icon'], ENT_QUOTES, 'UTF-8') ?> cw-scope-icon" aria-hidden="true"
                           style="--scope-color:<?= htmlspecialchars($subTeam['colour'], ENT_QUOTES, 'UTF-8') ?>"></i>
                        <a class="flex-grow-1" href="/sub-teams/<?= (int) $subTeam['id'] ?>"><?= htmlspecialchars($subTeam['name'], ENT_QUOTES, 'UTF-8') ?></a>
                        <a class="btn btn-sm btn-outline-secondary" href="/boards/sub-team/<?= (int) $subTeam['id'] ?>">Board</a>
                    </div>
                <?php endforeach; ?>
                <?php if (!$team['sub_teams']): ?><p class="p-3 mb-0 text-secondary">No sub-teams.</p><?php endif; ?>
            </div>
        </div>
    </aside>
</div>
