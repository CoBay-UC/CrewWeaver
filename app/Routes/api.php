<?php

declare(strict_types=1);

use CrewWeaver\Core\Response;

$router->add('GET', '/api/workspace/revision', static fn (): Response =>
    Response::success('Workspace revision retrieved.', $workspaceRevisions->current())
        ->withHeader('Cache-Control', 'no-store'),
    ['rate_limit_max' => 600]);

$router->add('GET', '/api/teams', [$teamApiController, 'index']);
$router->add('POST', '/api/teams', [$teamApiController, 'create']);
$router->add('PATCH', '/api/teams/{id}', [$teamApiController, 'update']);
$router->add('DELETE', '/api/teams/{id}', [$teamApiController, 'delete']);
$router->add('POST', '/api/sub-teams', [$teamApiController, 'createSubTeam']);
$router->add('PATCH', '/api/sub-teams/{id}', [$teamApiController, 'updateSubTeam']);
$router->add('DELETE', '/api/sub-teams/{id}', [$teamApiController, 'deleteSubTeam']);
$router->add('PATCH', '/api/organization/navigation', [$teamApiController, 'updateSidebar']);

$router->add('GET', '/api/projects', [$projectApiController, 'index']);
$router->add('GET', '/api/projects/{id}', [$projectApiController, 'show']);
$router->add('POST', '/api/projects', [$projectApiController, 'create']);
$router->add('PATCH', '/api/projects/{id}', [$projectApiController, 'update']);
$router->add('DELETE', '/api/projects/{id}', [$projectApiController, 'delete']);
$router->add('POST', '/api/projects/{id}/archive', [$projectApiController, 'archive']);
$router->add('POST', '/api/projects/{id}/restore', [$projectApiController, 'restore']);
$router->add('POST', '/api/projects/{id}/copy', [$projectApiController, 'copy']);
$router->add('POST', '/api/projects/{id}/schedule/preview', [$projectApiController, 'schedulePreview']);
$router->add('POST', '/api/projects/{id}/schedule/apply', [$projectApiController, 'scheduleApply']);

$router->add('GET', '/api/tasks/{id}', [$taskApiController, 'show']);
$router->add('POST', '/api/tasks', [$taskApiController, 'create']);
$router->add('PATCH', '/api/tasks/{id}', [$taskApiController, 'update']);
$router->add('DELETE', '/api/tasks/{id}', [$taskApiController, 'delete']);
$router->add('PATCH', '/api/tasks/{id}/status', [$taskApiController, 'boardPosition']);
$router->add('PATCH', '/api/tasks/{id}/owner', [$taskApiController, 'owner']);
$router->add('PUT', '/api/tasks/{id}/milestones', [$taskApiController, 'milestones']);
$router->add('PUT', '/api/tasks/{id}/dependencies', [$taskApiController, 'dependencies']);
$router->add('POST', '/api/tasks/{id}/comments', [$taskApiController, 'addComment']);
$router->add('PATCH', '/api/comments/{id}', [$taskApiController, 'editComment']);
$router->add('DELETE', '/api/comments/{id}', [$taskApiController, 'deleteComment']);
$router->add('POST', '/api/tasks/{id}/research', [$taskApiController, 'addResearch']);
$router->add('PATCH', '/api/research/{id}', [$taskApiController, 'editResearch']);
$router->add('DELETE', '/api/research/{id}', [$taskApiController, 'deleteResearch']);
$router->add('POST', '/api/tasks/{id}/attachments', [$fileController, 'upload']);
$router->add('GET', '/files/{id}/download', [$fileController, 'download'], ['csrf' => false]);
$router->add('DELETE', '/api/attachments/{id}', [$fileController, 'delete']);

$router->add('GET', '/api/boards/{scope}/{id}', [$visualizationApiController, 'board']);
$router->add('GET', '/api/gantt/{scope}/{id}', [$visualizationApiController, 'gantt']);
$router->add('GET', '/api/calendar/{scope}/{id}', [$visualizationApiController, 'calendar']);

$router->add('GET', '/api/important-dates', [$dateApiController, 'index']);
$router->add('POST', '/api/important-dates', [$dateApiController, 'create']);
$router->add('PATCH', '/api/important-dates/{id}', [$dateApiController, 'update']);
$router->add('DELETE', '/api/important-dates/{id}', [$dateApiController, 'delete']);

$router->add('GET', '/api/users', [$adminApiController, 'users']);
$router->add('PATCH', '/api/users/{id}/status', [$adminApiController, 'userStatus']);
$router->add('POST', '/api/memberships', [$membershipApiController, 'create']);
$router->add('GET', '/api/users/{id}/memberships', [$membershipApiController, 'index']);
$router->add('DELETE', '/api/memberships', [$membershipApiController, 'delete']);
$router->add('POST', '/api/scoped-roles', [$membershipApiController, 'grantRole']);
$router->add('DELETE', '/api/scoped-roles/{id}', [$membershipApiController, 'revokeRole']);
$router->add('POST', '/api/users/replacement/preview', [$adminApiController, 'replacementPreview']);
$router->add('POST', '/api/users/replacement/commit', [$adminApiController, 'replacementCommit']);
$router->add('GET', '/api/audit', [$adminApiController, 'audit']);
$router->add('GET', '/api/notifications', [$notificationApiController, 'index']);
$router->add('PATCH', '/api/notifications/{id}/read', [$notificationApiController, 'read']);
$router->add('POST', '/api/notifications/read-all', [$notificationApiController, 'readAll']);
