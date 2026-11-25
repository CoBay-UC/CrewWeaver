<?php

declare(strict_types=1);

use CrewWeaver\Core\Request;
use CrewWeaver\Core\Response;

$router->add('GET', '/health', static function (Request $request) use ($database): Response {
    $database->pdo()->query('SELECT 1');
    return Response::json(['status' => 'ok', 'service' => 'CrewWeaver']);
}, ['auth' => false, 'csrf' => false, 'rate_limit'=>false]);
$router->add('GET', '/login', [$authController, 'login'], ['auth' => false, 'csrf' => false, 'rate_limit_max'=>20]);
$router->add('GET', '/auth/callback', [$authController, 'callback'], ['auth' => false, 'csrf' => false, 'rate_limit_max'=>20]);
$router->add('GET', '/pending', [$authController, 'pending'], ['approved' => false, 'csrf' => false]);
$router->add('POST', '/logout', [$authController, 'logout'], ['approved' => false]);
$router->add('GET', '/', [$pageController, 'dashboard']);
$router->add('GET', '/teams', [$pageController, 'teams']);
$router->add('GET', '/teams/{id}', [$pageController, 'team']);
$router->add('GET', '/sub-teams/{id}', [$pageController, 'subTeam']);
$router->add('GET', '/projects/{id}', [$pageController, 'project']);
$router->add('GET', '/boards/{scope}/{id}', [$pageController, 'board']);
$router->add('GET', '/gantt/{scope}/{id}', [$pageController, 'gantt']);
$router->add('GET', '/calendar/{scope}/{id}', [$pageController, 'calendar']);
$router->add('GET', '/important-dates', [$pageController, 'importantDates']);
$router->add('GET', '/archive', [$pageController, 'archive']);
$router->add('GET', '/admin/users', [$pageController, 'users']);
$router->add('GET', '/admin/teams', [$pageController, 'manageTeams']);
