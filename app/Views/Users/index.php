<?php declare(strict_types=1); ?>
<?php
$heading = 'Users & access';
$eyebrow = 'Administration';
require __DIR__.'/../Components/page-header.php';
?>
<div class="card">
    <div class="card-header bg-transparent">
        <form class="row g-2" method="get">
            <div class="col-md"><label class="visually-hidden" for="user-search">Search name or email</label><input class="form-control" id="user-search" name="search" placeholder="Search name or email" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"></div>
            <?php if ($isGlobalAdmin): ?>
                <div class="col-auto"><select class="form-select" name="status" aria-label="Account status"><option value="">All statuses</option><?php foreach (['pending', 'active', 'inactive'] as $option): ?><option value="<?= $option ?>" <?= $status === $option ? 'selected' : '' ?>><?= ucfirst($option) ?></option><?php endforeach; ?></select></div>
            <?php endif; ?>
            <div class="col-auto"><button class="btn btn-outline-secondary">Filter</button></div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>User</th><?php if ($isGlobalAdmin): ?><th>Status</th><th>Last login</th><th>Created</th><?php endif; ?><th class="text-end">Actions</th></tr></thead>
            <tbody data-live-section="users-list">
            <?php foreach ($users['items'] as $user): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($user['display_name'], ENT_QUOTES, 'UTF-8') ?></strong><div class="small text-secondary"><?= htmlspecialchars((string) $user['email'], ENT_QUOTES, 'UTF-8') ?></div></td>
                    <?php if ($isGlobalAdmin): ?>
                        <td><select class="form-select form-select-sm w-auto" data-user-status="<?= (int) $user['id'] ?>" aria-label="Change status for <?= htmlspecialchars($user['display_name'], ENT_QUOTES, 'UTF-8') ?>">
                            <?php foreach (['pending', 'active', 'inactive'] as $option): ?><option value="<?= $option ?>" <?= $user['status'] === $option ? 'selected' : '' ?>><?= ucfirst($option) ?></option><?php endforeach; ?>
                        </select></td>
                        <td><?= htmlspecialchars((string) $user['last_login_at'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($user['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                    <?php endif; ?>
                    <td class="text-end"><button class="btn btn-sm btn-outline-primary" type="button" data-user-memberships="<?= (int) $user['id'] ?>" data-user-name="<?= htmlspecialchars($user['display_name'], ENT_QUOTES, 'UTF-8') ?>">Memberships</button></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$users['items']): ?><tr><td colspan="<?= $isGlobalAdmin ? 5 : 2 ?>" class="text-secondary p-3">No matching users.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php $lastPage = (int) ceil($users['total'] / $users['per_page']); ?>
    <div class="card-footer d-flex justify-content-between align-items-center" data-live-section="users-pagination">
        <span class="small text-secondary"><?= (int) $users['total'] ?> users</span>
        <nav class="d-flex gap-2" aria-label="Users pages">
            <?php foreach (['Previous' => $users['page'] - 1, 'Next' => $users['page'] + 1] as $label => $page): ?>
                <?php if ($page >= 1 && $page <= $lastPage): ?>
                    <a class="btn btn-sm btn-outline-secondary" href="?<?= htmlspecialchars(http_build_query(['search' => $search, 'status' => $status, 'page' => $page]), ENT_QUOTES, 'UTF-8') ?>"><?= $label ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
    </div>
</div>
<div class="modal fade" id="membershipsEditor" tabindex="-1" aria-labelledby="membership-heading" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header"><h2 class="modal-title h5" id="membership-heading">Memberships</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <div class="alert alert-danger" role="alert" data-membership-error hidden></div>
                <ul class="list-group mb-3" aria-live="polite" data-membership-list></ul>
                <form class="d-flex flex-column gap-2" data-membership-form>
                    <label for="membership-scope" class="form-label mb-0">Team or sub-team</label>
                    <div class="d-flex gap-2">
                        <select class="form-select" id="membership-scope" name="scope" required>
                            <option value="">Select a scope</option>
                            <?php foreach ($managedTeams as $managedTeam): ?>
                                <optgroup label="<?= htmlspecialchars($managedTeam['name'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?php if ($managedTeam['can_manage']): ?>
                                        <option value="<?= (int) $managedTeam['id'] ?>:0"><?= htmlspecialchars($managedTeam['name'], ENT_QUOTES, 'UTF-8') ?> (team)</option>
                                    <?php endif; ?>
                                    <?php foreach ($managedTeam['sub_teams'] as $child): ?>
                                        <option value="<?= (int) $managedTeam['id'] ?>:<?= (int) $child['id'] ?>"><?= htmlspecialchars($child['name'], ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-primary text-nowrap" type="submit" <?= !$managedTeams ? 'disabled' : '' ?>>Add membership</button>
                    </div>
                </form>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>
<script type="module" src="/assets/js/administration.js"></script>
