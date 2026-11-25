<?php declare(strict_types=1); ?>
<?php $countdownDates = array_filter($dates ?? [], static fn (array $date): bool => (bool) $date['show_countdown']); ?>
<div data-live-section="countdowns">
    <?php if ($countdownDates !== []): ?>
        <section class="mb-4" aria-labelledby="countdowns-title">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 id="countdowns-title" class="h6 mb-0">Important dates</h2>
                <button class="btn btn-sm btn-link" type="button" data-bs-toggle="collapse" data-bs-target="#countdownCards"
                        aria-expanded="true" aria-controls="countdownCards">Show / hide</button>
            </div>
            <div class="collapse show" id="countdownCards">
                <div class="cw-countdowns">
                    <?php foreach ($countdownDates as $date):
                        $remaining = (new DateTimeImmutable('today'))->diff(new DateTimeImmutable($date['event_date']));
                        $days = (int) $remaining->format('%r%a');
                    ?>
                        <article class="card cw-countdown" style="--scope-color:<?= htmlspecialchars($date['colour'], ENT_QUOTES, 'UTF-8') ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <i class="bi bi-<?= htmlspecialchars($date['icon'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
                                    <span class="badge text-bg-light"><?= htmlspecialchars(str_replace('_', ' ', $date['scope_type']), ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <h3 class="h6 mt-3 mb-1"><?= htmlspecialchars($date['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                                <div class="small text-secondary"><?= htmlspecialchars($date['event_date'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="fs-5 fw-bold mt-2"><?= $days === 0 ? 'Today' : ($days > 0 ? $days . ' days' : 'Past') ?></div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>
</div>
