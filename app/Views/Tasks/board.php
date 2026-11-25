<?php declare(strict_types=1); ?>
<?php
$scopeName=$scope['title']??$scope['name'];
$heading=$scopeName.' Kanban';
$description='Drag authorized tasks between statuses or use each card’s status menu.';
$eyebrow=ucfirst(str_replace('-',' ',$scopeType));
$activeView = 'boards';
ob_start();
require __DIR__.'/../Components/scope-views.php';
$actions = ob_get_clean();
require __DIR__.'/../Components/page-header.php';
require __DIR__.'/../Components/countdowns.php';
$statuses=['Not Started','Researching','In-Progress','Needs Outside Input','Needs Review','Done'];
$swimlanes=$lanes;
?>
<div class="cw-board-toolbar card mb-3"><div class="card-body py-2"><form class="row g-2 align-items-center" data-board-filters><div class="col-md"><label class="visually-hidden" for="board-search">Search tasks</label><div class="input-group input-group-sm"><span class="input-group-text"><i class="bi bi-search"></i></span><input id="board-search" class="form-control" name="search" placeholder="Search tasks and projects"></div></div><div class="col-auto"><select class="form-select form-select-sm" name="priority" aria-label="Filter by priority"><option value="">All priorities</option><option>critical</option><option>high</option><option>normal</option><option>low</option></select></div><div class="col-auto"><div class="form-check"><input class="form-check-input" type="checkbox" name="overdue" value="1" id="overdue"><label class="form-check-label small" for="overdue">Overdue</label></div></div></form></div></div>
<div class="cw-kanban" data-board data-scope="<?= htmlspecialchars($scopeType,ENT_QUOTES,'UTF-8') ?>" data-scope-id="<?= (int)$scopeId ?>">
    <div class="cw-kanban-header" aria-hidden="true"><div class="cw-swimlane-label"></div><?php foreach($statuses as $status): ?><div><span><?= htmlspecialchars($status,ENT_QUOTES,'UTF-8') ?></span><span class="badge text-bg-light" data-column-count="<?= htmlspecialchars($status,ENT_QUOTES,'UTF-8') ?>"><?= count(array_filter($tasks,static fn(array $task):bool=>$task['status']===$status)) ?></span></div><?php endforeach; ?></div>
    <?php foreach($swimlanes as $lane): ?>
        <section class="cw-swimlane">
            <button class="cw-swimlane-label" type="button" data-swimlane-toggle aria-expanded="true"><i class="bi bi-chevron-down"></i><span><?= htmlspecialchars($lane,ENT_QUOTES,'UTF-8') ?></span></button>
            <div class="cw-swimlane-columns">
                <?php foreach($statuses as $status): ?>
                    <div class="cw-kanban-column" data-status="<?= htmlspecialchars($status,ENT_QUOTES,'UTF-8') ?>" data-lane="<?= htmlspecialchars($lane,ENT_QUOTES,'UTF-8') ?>">
                        <h2 class="visually-hidden"><?= htmlspecialchars($lane.' '.$status,ENT_QUOTES,'UTF-8') ?></h2>
                        <?php foreach($tasks as $task): $taskLane=$scopeType==='team'?($task['sub_team_name']??'Direct team projects'):$scopeName;if($taskLane!==$lane||$task['status']!==$status)continue; ?>
                            <article <?= $task['can_edit'] ? 'data-task-editable' : '' ?> class="card cw-task-card" tabindex="0" data-task-id="<?= (int)$task['id'] ?>" data-order="<?= htmlspecialchars((string) $task['kanban_order'], ENT_QUOTES, 'UTF-8') ?>" data-version="<?= (int)$task['version'] ?>"><div class="card-body p-3"><div class="d-flex justify-content-between gap-2"><span class="small text-secondary text-truncate"><?= htmlspecialchars($task['project_title'],ENT_QUOTES,'UTF-8') ?></span><span class="cw-priority cw-priority-<?= htmlspecialchars($task['priority'],ENT_QUOTES,'UTF-8') ?>" title="<?= htmlspecialchars($task['priority'],ENT_QUOTES,'UTF-8') ?> priority"></span></div><button class="btn btn-link text-start text-body text-decoration-none fw-semibold p-0 my-2 stretched-link" data-task-open="<?= (int)$task['id'] ?>"><?= htmlspecialchars($task['title'],ENT_QUOTES,'UTF-8') ?></button><div class="d-flex align-items-center gap-2 small text-secondary position-relative"><?php if($task['is_blocked']): ?><i class="bi bi-lock-fill text-danger" title="Blocked"></i><?php endif; ?><span title="Comments"><i class="bi bi-chat"></i> <?= (int)$task['comment_count'] ?></span><span title="Research"><i class="bi bi-journal-text"></i> <?= (int)$task['research_count'] ?></span><span class="ms-auto"><?= htmlspecialchars((string)($task['manual_due_date']?:$task['calculated_finish']),ENT_QUOTES,'UTF-8') ?></span></div><?php if ($task['can_edit']): ?><select class="form-select form-select-sm mt-2 position-relative d-md-none" data-status-select aria-label="Change status"><?php foreach($statuses as $option): ?><option <?= $option===$status?'selected':'' ?>><?= htmlspecialchars($option,ENT_QUOTES,'UTF-8') ?></option><?php endforeach; ?></select><?php endif; ?></div></article>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
</div>
<?php require __DIR__.'/../Components/task-modal.php'; ?>
<script src="/assets/vendor/sortable/Sortable.min.js"></script><script type="module" src="/assets/js/kanban.js"></script>
