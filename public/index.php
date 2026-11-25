<?php

declare(strict_types=1);

use CrewWeaver\Authentication\AuthenticationService;
use CrewWeaver\Authentication\CsrfToken;
use CrewWeaver\Authentication\OidcClient;
use CrewWeaver\Authorization\AuthorizationService;
use CrewWeaver\Authorization\VisibilityPolicy;
use CrewWeaver\Configuration\Configuration;
use CrewWeaver\Controllers\AdminApiController;
use CrewWeaver\Controllers\AuthController;
use CrewWeaver\Controllers\FileController;
use CrewWeaver\Controllers\ImportantDateApiController;
use CrewWeaver\Controllers\MembershipApiController;
use CrewWeaver\Controllers\NotificationApiController;
use CrewWeaver\Controllers\PageController;
use CrewWeaver\Controllers\ProjectApiController;
use CrewWeaver\Controllers\TaskApiController;
use CrewWeaver\Controllers\TeamApiController;
use CrewWeaver\Controllers\VisualizationApiController;
use CrewWeaver\Core\Request;
use CrewWeaver\Core\Router;
use CrewWeaver\Core\Session;
use CrewWeaver\Core\View;
use CrewWeaver\Database\DatabaseConnection;
use CrewWeaver\Middleware\AuthenticationMiddleware;
use CrewWeaver\Middleware\CsrfMiddleware;
use CrewWeaver\Middleware\RateLimitMiddleware;
use CrewWeaver\Middleware\SecurityHeadersMiddleware;
use CrewWeaver\Middleware\WorkspaceRevisionMiddleware;
use CrewWeaver\Models\ActivityRepository;
use CrewWeaver\Models\AttachmentRepository;
use CrewWeaver\Models\DashboardRepository;
use CrewWeaver\Models\ImportantDateRepository;
use CrewWeaver\Models\MembershipRepository;
use CrewWeaver\Models\NotificationRepository;
use CrewWeaver\Models\OrganizationSettingsRepository;
use CrewWeaver\Models\PermissionRepository;
use CrewWeaver\Models\ProjectCopyRepository;
use CrewWeaver\Models\ProjectRepository;
use CrewWeaver\Models\RateLimitRepository;
use CrewWeaver\Models\ReplacementRepository;
use CrewWeaver\Models\ScheduleRepository;
use CrewWeaver\Models\ScopeDeletionRepository;
use CrewWeaver\Models\TaskRepository;
use CrewWeaver\Models\TeamRepository;
use CrewWeaver\Models\UserRepository;
use CrewWeaver\Models\VisualizationRepository;
use CrewWeaver\Models\WorkspaceRevisionRepository;
use CrewWeaver\Scheduling\SchedulingService;
use CrewWeaver\Storage\UploadStorage;
use CrewWeaver\Storage\ScopeFileCleanup;

$rootPath = dirname(__DIR__);
if (!is_file($rootPath . '/vendor/autoload.php')) {
    http_response_code(503);
    echo 'CrewWeaver dependencies are not installed. Run Composer or use the Docker image.';
    exit;
}
require $rootPath . '/vendor/autoload.php';

$configuration = Configuration::fromEnvironment($rootPath);
date_default_timezone_set($configuration->get('APP_TIMEZONE', 'UTC'));
$session = new Session($configuration);
$session->start();
$database = new DatabaseConnection($configuration);
$pdo = $database->pdo();
$view = new View($rootPath . '/app/Views');
$csrf = new CsrfToken($session);

$users = new UserRepository($pdo);
$teams = new TeamRepository($pdo);
$projects = new ProjectRepository($pdo);
$tasks = new TaskRepository($pdo);
$permissions = new PermissionRepository($pdo);
$schedules = new ScheduleRepository($pdo);
$dates = new ImportantDateRepository($pdo);
$activity = new ActivityRepository($pdo);
$notifications = new NotificationRepository($pdo);
$visualization = new VisualizationRepository($pdo);
$dashboard = new DashboardRepository($pdo);
$copies = new ProjectCopyRepository($pdo);
$replacement = new ReplacementRepository($pdo);
$attachments = new AttachmentRepository($pdo);
$memberships = new MembershipRepository($pdo);
$rateLimits = new RateLimitRepository($pdo);
$organizationSettings = new OrganizationSettingsRepository($pdo);
$scopeDeletions = new ScopeDeletionRepository($pdo, $activity);
$workspaceRevisions = new WorkspaceRevisionRepository($pdo);

$authentication = new AuthenticationService($configuration, $session, $users);
$authorization = new AuthorizationService($permissions, new VisibilityPolicy());
$oidc = new OidcClient($configuration, $session);
$scheduling = new SchedulingService($schedules);
$storage = new UploadStorage($rootPath . '/storage/uploads', $configuration->int('UPLOAD_MAX_BYTES', 10485760));
$scopeFileCleanup = new ScopeFileCleanup($scopeDeletions, $storage);

$authController = new AuthController($configuration, $authentication, $oidc, $users, $view, $csrf);
$pageController = new PageController($view, $authentication, $authorization, $csrf, $teams, $projects, $tasks, $dashboard, $visualization, $dates, $notifications, $activity, $users, $organizationSettings);
$teamApiController = new TeamApiController(
    $teams, $authentication, $authorization, $activity, $organizationSettings,
    $scopeDeletions, $scopeFileCleanup
);
$projectApiController = new ProjectApiController($projects, $copies, $scheduling, $authentication, $authorization, $activity, $scopeDeletions, $scopeFileCleanup);
$taskApiController = new TaskApiController($tasks, $scheduling, $authentication, $authorization, $activity, $notifications, $attachments);
$visualizationApiController = new VisualizationApiController($visualization, $dates);
$dateApiController = new ImportantDateApiController($dates, $authentication, $authorization, $teams, $activity);
$adminApiController = new AdminApiController($users, $replacement, $activity, $authentication, $authorization);
$notificationApiController = new NotificationApiController($notifications, $authentication);
$fileController = new FileController($attachments, $storage, $authentication, $authorization);
$membershipApiController = new MembershipApiController($memberships, $authentication, $authorization, $users, $activity);

$router = new Router([
    new SecurityHeadersMiddleware(),
    new RateLimitMiddleware($rateLimits),
    new AuthenticationMiddleware($authentication),
    new CsrfMiddleware($csrf),
    new WorkspaceRevisionMiddleware($workspaceRevisions),
], $configuration->bool('APP_DEBUG'));
require $rootPath . '/app/Routes/web.php';
require $rootPath . '/app/Routes/api.php';
$router->dispatch(Request::fromGlobals())->emit();
