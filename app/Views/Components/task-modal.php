<?php declare(strict_types=1); ?>
<div class="modal fade" id="taskModal" tabindex="-1" aria-labelledby="taskModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-fullscreen-sm-down modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div><div class="small text-secondary" data-task-project></div><h2 class="modal-title h4" id="taskModalTitle" data-task-title>Task</h2></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="d-flex flex-column flex-md-row">
                    <nav class="nav nav-pills flex-md-column flex-row flex-nowrap overflow-auto p-3 border-end cw-task-tabs" role="tablist" aria-label="Task sections">
                        <?php foreach (['overview', 'timeline', 'research', 'comments', 'activity'] as $tab): ?>
                            <button id="task-tab-<?= $tab ?>" class="nav-link <?= $tab === 'overview' ? 'active' : '' ?>"
                                    data-bs-toggle="pill" data-bs-target="#task-<?= $tab ?>" type="button"
                                    role="tab" aria-controls="task-<?= $tab ?>"><?= ucfirst($tab) ?></button>
                        <?php endforeach; ?>
                    </nav>
                    <div class="tab-content flex-grow-1 p-3 p-lg-4">
                        <?php foreach (['overview', 'timeline', 'research', 'comments', 'activity'] as $tab): ?>
                            <div class="tab-pane fade <?= $tab === 'overview' ? 'show active' : '' ?>" id="task-<?= $tab ?>"
                                 role="tabpanel" aria-labelledby="task-tab-<?= $tab ?>" data-task-<?= $tab ?>></div>
                        <?php endforeach; ?>
                        <div class="alert alert-warning mt-3 mb-0" role="alert" data-task-save-error hidden></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <span class="me-auto small text-secondary" data-task-version></span>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" data-task-save hidden>Save changes</button>
            </div>
        </div>
    </div>
</div>
