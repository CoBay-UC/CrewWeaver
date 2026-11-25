<?php declare(strict_types=1); ?>
<dialog class="cw-dialog" id="projectEditor" aria-labelledby="project-editor-title">
    <form class="d-flex flex-column" data-project-form>
        <div class="cw-dialog-header">
            <div>
                <h2 class="h5 mb-0" id="project-editor-title">New project</h2>
                <div class="small text-secondary" data-project-editor-scope></div>
            </div>
            <button type="button" class="btn-close" data-dialog-cancel aria-label="Close"></button>
        </div>
        <div class="cw-dialog-body d-grid gap-3">
            <div class="alert alert-danger mb-0" data-dialog-error role="alert" tabindex="-1" hidden></div>
            <div>
                <label class="form-label" for="project-editor-name">Project title</label>
                <input class="form-control" name="title" id="project-editor-name" required maxlength="240" autofocus>
            </div>
            <div data-project-start-fields>
                <label class="form-label" for="project-editor-start">Planned start</label>
                <input class="form-control" name="planned_start" id="project-editor-start" type="date" required>
            </div>
            <div data-project-create-fields>
                <label class="form-label" for="project-editor-description">Description <span class="text-secondary">(optional)</span></label>
                <textarea class="form-control" name="description" id="project-editor-description" rows="3"></textarea>
            </div>
            <fieldset data-project-copy-fields hidden disabled>
                <legend class="form-label fs-6">Include in the copy</legend>
                <?php foreach (['copy_description' => 'Description', 'copy_tasks' => 'Tasks', 'copy_milestones' => 'Milestones', 'copy_dependencies' => 'Task dependencies'] as $field => $label): ?>
                    <div class="form-check">
                        <input class="form-check-input" name="<?= $field ?>" id="project-editor-<?= $field ?>" type="checkbox" checked>
                        <label class="form-check-label" for="project-editor-<?= $field ?>"><?= $label ?></label>
                    </div>
                <?php endforeach; ?>
            </fieldset>
        </div>
        <div class="cw-dialog-footer">
            <button type="button" class="btn btn-outline-secondary" data-dialog-cancel>Cancel</button>
            <button type="submit" class="btn btn-primary" data-project-editor-submit>Create project</button>
        </div>
    </form>
</dialog>
