<?php declare(strict_types=1); ?>
<?php
$heading = 'Archived projects';
$eyebrow = 'Archive';
require __DIR__.'/../Components/page-header.php';
?>
<div class="card">
    <div class="card-header bg-transparent">
        <form class="d-flex gap-2" method="get">
            <?php foreach (['team_id', 'sub_team_id'] as $field): ?>
                <?php if ($filters[$field]): ?><input type="hidden" name="<?= $field ?>" value="<?= (int) $filters[$field] ?>"><?php endif; ?>
            <?php endforeach; ?>
            <input class="form-control" name="search" aria-label="Search archived projects" placeholder="Search archived projects" value="<?= htmlspecialchars($filters['search'], ENT_QUOTES, 'UTF-8') ?>">
            <button class="btn btn-outline-secondary">Search</button>
            <?php if ($filters['team_id'] || $filters['sub_team_id']): ?><a class="btn btn-outline-secondary text-nowrap" href="/archive">All scopes</a><?php endif; ?>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Project</th><th>Scope</th><th>Status</th><th>Archived</th><th>By</th><th></th></tr></thead>
            <tbody data-live-section="archive-results">
            <?php foreach ($projects as $project): ?>
                <tr>
                    <td><a href="/projects/<?= (int) $project['id'] ?>"><?= htmlspecialchars($project['title'], ENT_QUOTES, 'UTF-8') ?></a></td>
                    <td><?= htmlspecialchars($project['team_name'] . ($project['sub_team_name'] ? ' · ' . $project['sub_team_name'] : ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><span class="badge cw-status"><?= htmlspecialchars($project['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td><?= htmlspecialchars((string) $project['archived_at'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) $project['archived_by_name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="text-end"><?php if ($project['can_restore']): ?><button class="btn btn-sm btn-outline-primary" data-project-restore="<?= (int) $project['id'] ?>">Restore</button><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$projects): ?><tr><td colspan="6" class="text-center text-secondary py-5">No archived projects match.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
