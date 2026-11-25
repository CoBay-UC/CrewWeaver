<?php

declare(strict_types=1);

use CrewWeaver\Configuration\Configuration;
use CrewWeaver\Database\DatabaseConnection;
use CrewWeaver\Database\Migrator;

$rootPath=dirname(__DIR__);
require $rootPath.'/vendor/autoload.php';
$configuration=Configuration::fromEnvironment($rootPath);
$migrator=new Migrator((new DatabaseConnection($configuration))->pdo(),$rootPath.'/database/migrations');
$completed=$migrator->migrate();
echo $completed===[]?"Database is current.\n":"Applied: ".implode(', ',$completed)."\n";
