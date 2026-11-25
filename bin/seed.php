<?php

declare(strict_types=1);

use CrewWeaver\Configuration\Configuration;
use CrewWeaver\Database\DatabaseConnection;
$rootPath=dirname(__DIR__);
require $rootPath.'/vendor/autoload.php';
$configuration=Configuration::fromEnvironment($rootPath);
if($configuration->get('APP_ENV','production')!=='development'){throw new \RuntimeException('Development seeds can run only when APP_ENV=development.');}
$sql=file_get_contents($rootPath.'/database/seeds/DevelopmentSeeder.sql');
if($sql===false){throw new \RuntimeException('Development seed file is unavailable.');}
(new DatabaseConnection($configuration))->pdo()->exec($sql);
echo "Development data seeded.\n";
