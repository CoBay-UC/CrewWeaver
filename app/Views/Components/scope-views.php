<?php declare(strict_types=1); ?>
<?php if ($scopeType === 'project'): ?>
    <span data-project-page="<?= $scope['sub_team_id'] ? '/sub-teams/' . (int) $scope['sub_team_id'] : '/teams/' . (int) $scope['team_id'] ?>" hidden></span>
<?php endif; ?>
<nav class="btn-group" aria-label="Scope views">
    <?php foreach (['boards' => 'Kanban', 'gantt' => 'Gantt', 'calendar' => 'Calendar'] as $path => $label): ?>
        <a class="btn <?= $activeView === $path ? 'btn-primary' : 'btn-outline-secondary' ?>"
           href="/<?= $path ?>/<?= htmlspecialchars($scopeType, ENT_QUOTES, 'UTF-8') ?>/<?= (int) $scopeId ?>"
           <?= $activeView === $path ? 'aria-current="page"' : '' ?>><?= $label ?></a>
    <?php endforeach; ?>
</nav>
