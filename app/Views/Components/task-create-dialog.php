<?php declare(strict_types=1); ?>
<dialog class="cw-dialog" id="taskCreateDialog" aria-labelledby="task-create-title">
    <form class="d-flex flex-column" data-task-create-form>
        <div class="cw-dialog-header">
            <div>
                <h2 class="h5 mb-0" id="task-create-title">New task</h2>
                <div class="small text-secondary"><?= htmlspecialchars($project['title'], ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <button type="button" class="btn-close" data-dialog-cancel aria-label="Close"></button>
        </div>
        <div class="cw-dialog-body d-grid gap-3">
            <div class="alert alert-danger mb-0" data-dialog-error role="alert" tabindex="-1" hidden></div>
            <div>
                <label class="form-label" for="task-create-name">Task title</label>
                <input class="form-control" name="title" id="task-create-name" required maxlength="240" autofocus>
            </div>
            <div>
                <label class="form-label" for="task-create-duration">Estimated duration (days)</label>
                <input class="form-control" name="estimated_duration_days" id="task-create-duration" type="number" min="0" max="3650" step="1" value="1" required>
            </div>
            <div>
                <label class="form-label" for="task-create-description">Description <span class="text-secondary">(optional)</span></label>
                <textarea class="form-control" name="description" id="task-create-description" rows="3"></textarea>
            </div>
        </div>
        <div class="cw-dialog-footer">
            <button type="button" class="btn btn-outline-secondary" data-dialog-cancel>Cancel</button>
            <button type="submit" class="btn btn-primary">Create task</button>
        </div>
    </form>
</dialog>
