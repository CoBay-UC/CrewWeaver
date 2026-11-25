<?php

declare(strict_types=1);

use CrewWeaver\Configuration\Configuration;
use CrewWeaver\Database\DatabaseConnection;
use CrewWeaver\Models\ActivityRepository;
use CrewWeaver\Models\ScopeDeletionRepository;
use CrewWeaver\Storage\ScopeFileCleanup;
use CrewWeaver\Storage\UploadStorage;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
$configuration = Configuration::fromEnvironment($root);
$pdo = (new DatabaseConnection($configuration))->pdo();
$deletions = new ScopeDeletionRepository($pdo, new ActivityRepository($pdo));
$cleanup = new ScopeFileCleanup($deletions, new UploadStorage($root . '/storage/uploads', $configuration->int('UPLOAD_MAX_BYTES', 10485760)));
$remaining = $cleanup->purge($deletions->queuedFiles());
echo $remaining === 0 ? "Attachment cleanup complete.\n" : "{$remaining} attachment deletions still pending.\n";
exit($remaining === 0 ? 0 : 1);
