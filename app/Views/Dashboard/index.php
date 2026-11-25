<?php declare(strict_types=1); ?>
<?php $heading='Welcome back, '.explode(' ', (string) $currentUser?->record()['display_name'])[0];$description='Your work, deadlines, and organization activity at a glance.';$eyebrow='Dashboard';require __DIR__.'/../Components/page-header.php'; ?>
<?php $dates=$summary['dates'];require __DIR__.'/../Components/countdowns.php'; ?>
<div class="row g-4" data-live-section="dashboard">
    <section class="col-xl-8" aria-labelledby="my-tasks-title"><div class="card h-100"><div class="card-header bg-transparent d-flex justify-content-between"><h2 id="my-tasks-title" class="h5 mb-0">My active tasks</h2><span class="badge text-bg-primary"><?= count($summary['tasks']) ?></span></div><div class="list-group list-group-flush"><?php if ($summary['tasks']===[]): ?><div class="card-body text-secondary text-center py-5">No active assignments.</div><?php endif; ?><?php foreach($summary['tasks'] as $task): ?><button class="list-group-item list-group-item-action d-flex align-items-center gap-3" data-task-open="<?= (int)$task['id'] ?>"><span class="cw-priority cw-priority-<?= htmlspecialchars($task['priority'],ENT_QUOTES,'UTF-8') ?>"></span><span class="flex-grow-1"><span class="fw-semibold d-block"><?= htmlspecialchars($task['title'],ENT_QUOTES,'UTF-8') ?></span><span class="small text-secondary"><?= htmlspecialchars($task['project_title'],ENT_QUOTES,'UTF-8') ?></span></span><span class="badge cw-status"><?= htmlspecialchars($task['status'],ENT_QUOTES,'UTF-8') ?></span><span class="small text-secondary"><?= htmlspecialchars((string)($task['manual_due_date']?:$task['calculated_finish']),ENT_QUOTES,'UTF-8') ?></span></button><?php endforeach; ?></div></div></section>
    <section class="col-xl-4" aria-labelledby="projects-title"><div class="card h-100"><div class="card-header bg-transparent"><h2 id="projects-title" class="h5 mb-0">My projects</h2></div><div class="card-body"><?php foreach($summary['projects'] as $project): ?><a class="cw-project-mini" href="/projects/<?= (int)$project['id'] ?>"><span class="cw-project-dot" style="background:<?= htmlspecialchars($project['colour'],ENT_QUOTES,'UTF-8') ?>"></span><span class="flex-grow-1"><strong><?= htmlspecialchars($project['title'],ENT_QUOTES,'UTF-8') ?></strong><small><?= htmlspecialchars($project['status'],ENT_QUOTES,'UTF-8') ?></small></span><span><?= (int)$project['progress'] ?>%</span></a><?php endforeach; ?><?php if($summary['projects']===[]): ?><div class="text-secondary text-center py-5">No project assignments.</div><?php endif; ?></div></div></section>
    <section class="col-12" aria-labelledby="activity-title">
        <div class="card">
            <div class="card-header bg-transparent"><h2 id="activity-title" class="h5 mb-0">Recent organization activity</h2></div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th scope="col">When</th><th scope="col">Actor</th><th scope="col">Action</th><th scope="col">Item</th></tr></thead>
                    <tbody>
                        <?php foreach ($activity as $item): ?>
                            <tr>
                                <td class="text-nowrap"><?= htmlspecialchars($item['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($item['actor_name'] ?? 'System', ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <?= htmlspecialchars($item['action'], ENT_QUOTES, 'UTF-8') ?>
                                    <?php if ($item['detail'] !== null): ?>
                                        <div class="small text-body-secondary"><?= htmlspecialchars($item['detail'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div><?= htmlspecialchars($item['resource_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php if ($item['project_context'] !== null): ?>
                                        <div class="small text-body-secondary"><?= htmlspecialchars($item['project_context'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($activity === []): ?>
                            <tr><td colspan="4" class="text-center text-body-secondary py-3">No activity yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>
<?php require __DIR__.'/../Components/task-modal.php'; ?>
