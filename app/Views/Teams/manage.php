<?php declare(strict_types=1); ?>
<?php
$heading = 'Teams & sub-teams';
$eyebrow = 'Administration';
$actions = $isGlobalAdmin ? '<button type="button" class="btn btn-primary" data-scope-new="teams">New team</button>' : '';
require __DIR__.'/../Components/page-header.php';
?>
<?php if ($isGlobalAdmin): ?>
    <section class="card mb-4" aria-labelledby="organization-navigation-heading">
        <div class="card-body">
            <h2 class="h5" id="organization-navigation-heading">Organization navigation</h2>
            <form class="d-flex flex-wrap align-items-center gap-3" data-navigation-settings>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="sidebar-sub-teams" name="show_sub_teams" <?= $showSidebarSubTeams ? 'checked' : '' ?>>
                    <label class="form-check-label" for="sidebar-sub-teams">Show sub-teams in the sidebar</label>
                </div>
                <button class="btn btn-sm btn-outline-primary" type="submit">Save navigation</button>
            </form>
        </div>
    </section>
<?php endif; ?>
<div data-managed-teams data-live-section="teams-admin">
    <?php foreach ($managedTeams as $managedTeam): ?>
        <section class="card mb-3">
            <div class="card-header d-flex flex-wrap gap-2 align-items-center">
                <h2 class="h5 mb-0 me-auto"><a href="/teams/<?= (int) $managedTeam['id'] ?>"><?= htmlspecialchars($managedTeam['name'], ENT_QUOTES, 'UTF-8') ?></a></h2>
                <?php if (!$managedTeam['is_active']): ?><span class="badge text-bg-secondary">Inactive</span><?php endif; ?>
                <?php if ($managedTeam['can_manage']): ?>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-scope-new="sub-teams" data-parent-id="<?= (int) $managedTeam['id'] ?>">New sub-team</button>
                    <?php $type = 'teams'; $record = $managedTeam; require __DIR__.'/../Components/scope-admin-actions.php'; ?>
                <?php endif; ?>
            </div>
            <ul class="list-group list-group-flush">
                <?php foreach ($managedTeam['sub_teams'] as $child): ?>
                    <li class="list-group-item d-flex flex-wrap gap-2 align-items-center ps-4">
                        <a class="me-auto" href="/sub-teams/<?= (int) $child['id'] ?>"><?= htmlspecialchars($child['name'], ENT_QUOTES, 'UTF-8') ?></a>
                        <?php if (!$child['is_active']): ?><span class="badge text-bg-secondary">Inactive</span><?php endif; ?>
                        <?php $type = 'sub-teams'; $record = $child; require __DIR__.'/../Components/scope-admin-actions.php'; ?>
                    </li>
                <?php endforeach; ?>
                <?php if (!$managedTeam['sub_teams']): ?><li class="list-group-item text-secondary">No sub-teams.</li><?php endif; ?>
            </ul>
        </section>
    <?php endforeach; ?>
    <?php if (!$managedTeams): ?><p class="text-secondary">No teams to manage.</p><?php endif; ?>
</div>

<div class="modal fade" id="scopeEditor" tabindex="-1" aria-labelledby="scope-editor-heading" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-fullscreen-sm-down">
        <form class="modal-content" data-scope-form>
            <div class="modal-header"><h2 class="modal-title h5" id="scope-editor-heading" data-scope-heading></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <div class="alert alert-danger" role="alert" data-scope-error hidden></div>
                <div class="row g-3" data-scope-fields>
                    <div class="col-md-6"><label class="form-label" for="scope-name">Name</label><input class="form-control" id="scope-name" name="name" required maxlength="160"></div>
                    <div class="col-md-6"><label class="form-label" for="scope-slug">Slug</label><input class="form-control" id="scope-slug" name="slug" maxlength="180" placeholder="Generated for new entries"></div>
                    <div class="col-12"><label class="form-label" for="scope-description">Description</label><textarea class="form-control" id="scope-description" name="description" rows="3" maxlength="16000"></textarea></div>
                    <div class="col-3"><label class="form-label" for="scope-colour">Colour</label><input class="form-control form-control-color" id="scope-colour" name="colour" type="color"></div>
                    <div class="col-5" data-icon-picker>
                        <label class="form-label" for="scope-icon">Bootstrap icon</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi" data-icon-preview aria-hidden="true"></i></span>
                            <input class="form-control" id="scope-icon" name="icon" required maxlength="80" pattern="[a-z0-9\-]+">
                        </div>
                        <button type="button" class="btn btn-sm btn-link px-0" data-icon-open
                                aria-expanded="false" aria-controls="scope-icon-options">Browse icons</button>
                        <div id="scope-icon-options" class="cw-icon-options border p-2" hidden>
                            <label class="visually-hidden" for="scope-icon-search">Search Bootstrap Icons</label>
                            <input type="search" class="form-control form-control-sm mb-2" id="scope-icon-search"
                                   placeholder="Search icons" autocomplete="off">
                            <p class="small text-body-secondary mb-1" role="status" data-icon-status></p>
                            <div class="list-group cw-icon-results" data-icon-results></div>
                            <button type="button" class="btn btn-sm btn-link mt-1" data-icon-more hidden>Show more</button>
                        </div>
                    </div>
                    <div class="col-4"><label class="form-label" for="scope-order">Display order</label><input class="form-control" id="scope-order" name="display_order" type="number" required></div>
                    <div class="col-12"><div class="form-check"><input class="form-check-input" id="scope-active" name="is_active" type="checkbox"><label class="form-check-label" for="scope-active">Active</label></div></div>
                </div>
                <fieldset data-scope-delete-fields hidden disabled>
                    <p class="fw-semibold text-danger" data-scope-delete-message></p>
                    <p>This permanently removes active and archived projects in this scope, including tasks, files, comments, Important Dates, and its memberships and roles. Deleting a team also removes its sub-teams.</p>
                    <label for="scope-delete-confirmation" class="form-label">Type this sentence to confirm</label>
                    <p class="mb-2"><strong id="scope-delete-phrase"><?= htmlspecialchars($deleteConfirmation, ENT_QUOTES, 'UTF-8') ?></strong></p>
                    <input class="form-control" name="confirmation" id="scope-delete-confirmation" required maxlength="256"
                           autocomplete="off" spellcheck="false" aria-describedby="scope-delete-phrase">
                </fieldset>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary" data-scope-submit>Save</button></div>
        </form>
    </div>
</div>
<script type="module" src="/assets/js/administration.js"></script>
