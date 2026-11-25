<?php declare(strict_types=1); ?>
<?php
$heading = $scope['name'];
$eyebrow = 'Sub-team';
$description = $scope['description'];
$activeView = '';
ob_start();
if ($canManageScope): ?>
    <button type="button" class="btn btn-outline-primary" data-project-create
            data-team-id="<?= (int) $scope['team_id'] ?>" data-sub-team-id="<?= $scopeId ?>"
            data-scope-name="<?= htmlspecialchars($scope['name'], ENT_QUOTES, 'UTF-8') ?>">New Project</button>
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
    <a href="/teams/<?= (int) $scope['team_id'] ?>">Parent team</a>
    <a href="/important-dates?scope=sub_team:<?= $scopeId ?>">Important Dates</a>
    <a href="/archive?sub_team_id=<?= $scopeId ?>"><i class="bi bi-archive me-1"></i><?= (int) $archivedCount ?> archived projects</a>
</div>
<section class="card" aria-labelledby="sub-team-projects" data-live-section="scope-projects">
    <h2 id="sub-team-projects" class="card-header h5">Active projects</h2>
    <div class="list-group list-group-flush">
        <?php foreach ($projects as $project): ?>
            <a class="list-group-item list-group-item-action" href="/projects/<?= (int) $project['id'] ?>">
                <?= htmlspecialchars($project['title'], ENT_QUOTES, 'UTF-8') ?>
                <span class="badge cw-status ms-2"><?= htmlspecialchars($project['status'], ENT_QUOTES, 'UTF-8') ?></span>
            </a>
        <?php endforeach; ?>
        <?php if ($projects === []): ?><p class="p-3 mb-0 text-secondary">No active projects.</p><?php endif; ?>
    </div>
</section>
