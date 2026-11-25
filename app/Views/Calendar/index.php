<?php declare(strict_types=1); ?>
<?php
$heading = ($scope['title'] ?? $scope['name']) . ' Calendar';
$eyebrow = ucfirst(str_replace('-', ' ', $scopeType));
$activeView = 'calendar';
ob_start();
?>
<a class="btn btn-outline-secondary" href="/important-dates?scope=<?= htmlspecialchars(str_replace('-', '_', $scopeType), ENT_QUOTES, 'UTF-8') ?>:<?= (int) $scopeId ?>">Important Dates</a>
<?php
require __DIR__.'/../Components/scope-views.php';
$actions = ob_get_clean();
require __DIR__.'/../Components/page-header.php';
?>
<div class="card" data-calendar-panel>
    <div class="card-header bg-transparent">
        <div class="btn-group btn-group-sm" role="group" aria-label="Calendar display">
            <button type="button" class="btn btn-outline-secondary" data-calendar-view="dayGridMonth" aria-pressed="false">Month</button>
            <button type="button" class="btn btn-outline-secondary d-none d-md-inline-block" data-calendar-view="timeGridWeek" aria-pressed="false">Week</button>
            <button type="button" class="btn btn-outline-secondary d-none d-md-inline-block" data-calendar-view="timeGridDay" aria-pressed="false">Day</button>
            <button type="button" class="btn btn-outline-secondary" data-calendar-view="listMonth" aria-pressed="false">List</button>
            <button type="button" class="btn btn-outline-secondary" data-calendar-view="all" aria-pressed="false" aria-controls="calendar-list-all">List All</button>
        </div>
    </div>
    <div class="card-body">
        <div id="calendar" data-calendar data-scope="<?= htmlspecialchars($scopeType, ENT_QUOTES, 'UTF-8') ?>" data-scope-id="<?= (int) $scopeId ?>"></div>
        <section id="calendar-list-all" data-calendar-list-all aria-label="All calendar dates" aria-busy="false" hidden></section>
    </div>
</div>
<?php require __DIR__.'/../Components/task-modal.php'; ?><script src="/assets/vendor/fullcalendar/core.min.js"></script><script src="/assets/vendor/fullcalendar/daygrid.min.js"></script><script src="/assets/vendor/fullcalendar/timegrid.min.js"></script><script src="/assets/vendor/fullcalendar/list.min.js"></script><script type="module" src="/assets/js/calendar.js"></script>
