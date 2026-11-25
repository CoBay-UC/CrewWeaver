<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="en" data-bs-theme="auto">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> · CrewWeaver</title>
    <link rel="stylesheet" href="/assets/vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/vendor/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/app.css">
    <script src="/assets/js/theme.js"></script>
</head>
<body data-current-user-id="<?= (int) $currentUser?->id() ?>">
<a class="visually-hidden-focusable skip-link" href="#main-content">Skip to content</a>
<header class="navbar navbar-expand-lg sticky-top cw-topbar border-bottom">
    <div class="container-fluid">
        <button class="btn btn-sm d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-label="Open navigation"><i class="bi bi-list fs-4"></i></button>
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="/"><span class="cw-logo"><i class="bi bi-bezier2"></i></span> CrewWeaver</a>
        <div class="ms-auto d-flex align-items-center gap-2">
            <button class="btn btn-sm btn-outline-secondary" type="button" data-theme-toggle aria-label="Change theme"><i class="bi bi-circle-half" aria-hidden="true"></i></button>
            <button class="btn btn-sm btn-outline-secondary position-relative" type="button" data-notifications-toggle aria-label="Notifications">
                <i class="bi bi-bell"></i><?php if ($unreadNotifications > 0): ?><span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= (int) $unreadNotifications ?></span><?php endif; ?>
            </button>
            <div class="dropdown">
                <button class="btn btn-sm d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="cw-avatar"><?= htmlspecialchars(strtoupper(substr((string) $currentUser?->record()['display_name'], 0, 1)), ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="d-none d-md-inline"><?= htmlspecialchars((string) $currentUser?->record()['display_name'], ENT_QUOTES, 'UTF-8') ?></span>
                </button>
                <div class="dropdown-menu dropdown-menu-end p-2">
                    <button class="dropdown-item" type="button" data-theme-value="system"><i class="bi bi-circle-half me-2" aria-hidden="true"></i>System theme</button>
                    <button class="dropdown-item" type="button" data-theme-value="light"><i class="bi bi-sun me-2" aria-hidden="true"></i>Light theme</button>
                    <button class="dropdown-item" type="button" data-theme-value="dark"><i class="bi bi-moon me-2" aria-hidden="true"></i>Dark theme</button>
                    <hr class="dropdown-divider">
                    <form method="post" action="/logout"><input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"><button class="dropdown-item text-danger" type="submit"><i class="bi bi-box-arrow-right me-2"></i>Sign out</button></form>
                </div>
            </div>
        </div>
    </div>
</header>
<div class="container-fluid">
    <div class="row">
        <aside class="offcanvas-lg offcanvas-start col-lg-2 cw-sidebar border-end p-0" tabindex="-1" id="sidebar" aria-label="Primary navigation">
            <div class="offcanvas-header"><h2 class="offcanvas-title h5">Navigation</h2><button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Close"></button></div>
            <nav class="offcanvas-body cw-sidebar-nav p-0">
                <div class="cw-sidebar-links p-3">
                <a class="cw-nav-link" href="/"><i class="bi bi-grid-1x2"></i> Dashboard</a>
                <a class="cw-nav-link" href="/important-dates"><i class="bi bi-calendar-event"></i> Important Dates</a>
                <a class="cw-nav-link" href="/teams"><i class="bi bi-people"></i> Organization</a>
                <div data-organization-nav data-live-section="organization-nav">
                <?php foreach ($navigationTeams as $navTeam): ?>
                    <a class="cw-nav-link" href="/teams/<?= (int) $navTeam['id'] ?>"><i class="bi bi-<?= htmlspecialchars($navTeam['icon'], ENT_QUOTES, 'UTF-8') ?> cw-scope-icon" aria-hidden="true" style="--scope-color:<?= htmlspecialchars($navTeam['colour'], ENT_QUOTES, 'UTF-8') ?>"></i><?= htmlspecialchars($navTeam['name'], ENT_QUOTES, 'UTF-8') ?></a>
                    <?php if (!empty($navTeam['sub_teams'])): ?>
                        <ul class="cw-nav-children">
                            <?php foreach ($navTeam['sub_teams'] as $navChild): ?>
                                <li><a class="cw-nav-link" href="/sub-teams/<?= (int) $navChild['id'] ?>"><i class="bi bi-<?= htmlspecialchars($navChild['icon'], ENT_QUOTES, 'UTF-8') ?> cw-scope-icon" aria-hidden="true" style="--scope-color:<?= htmlspecialchars($navChild['colour'], ENT_QUOTES, 'UTF-8') ?>"></i><?= htmlspecialchars($navChild['name'], ENT_QUOTES, 'UTF-8') ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                <?php endforeach; ?>
                </div>
                <?php if ($canManageScopes): ?>
                    <div class="cw-nav-label">Administration</div>
                    <a class="cw-nav-link" href="/admin/teams"><i class="bi bi-diagram-3"></i> Teams &amp; sub-teams</a>
                    <a class="cw-nav-link" href="/admin/users"><i class="bi bi-shield-lock"></i> Users &amp; access</a>
                <?php endif; ?>
                </div>
                <div class="cw-sidebar-archive px-3 py-2">
                    <a class="cw-nav-link" href="/archive"><i class="bi bi-archive"></i> Archive</a>
                </div>
            </nav>
        </aside>
        <main id="main-content" class="col-lg-10 ms-sm-auto px-3 px-lg-4 py-4" tabindex="-1"><?= $content ?></main>
    </div>
</div>
<div class="toast-container position-fixed bottom-0 end-0 p-3" data-toast-container></div>
<?php require __DIR__.'/../Components/confirmation-dialog.php'; ?>
<div class="offcanvas offcanvas-end" tabindex="-1" id="notificationsPanel"><div class="offcanvas-header"><h2 class="offcanvas-title h5">Notifications</h2><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button></div><div class="offcanvas-body" data-notifications-list><div class="text-center text-secondary py-5"><span class="spinner-border spinner-border-sm"></span> Loading</div></div></div>
<script src="/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script type="module" src="/assets/js/app.js"></script>
</body>
</html>
