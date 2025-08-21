<?php

declare(strict_types=1);

use App\Kernel;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (class_exists(Dotenv::class) && file_exists(dirname(__DIR__).'/.env')) {
    (new Dotenv())->usePutenv()->bootEnv(dirname(__DIR__) . '/.env');
}

// Boot kernel
$kernel = new Kernel('test', true);
$kernel->boot();

$container = $kernel->getContainer();
$em = $container->get('doctrine')->getManager();

// Create SQLite test db
$params = $em->getConnection()->getParams();
if (($params['driver'] ?? null) === 'pdo_sqlite' && isset($params['path'])) {
    $em->getConnection()->close();
    @unlink($params['path']);         // delete old file
    $em->getConnection()->connect();  // connection to new file
}

// Create fresh schema
$tool = new SchemaTool($em);
$tool->dropDatabase();
$tool->createSchema($em->getMetadataFactory()->getAllMetadata());

$kernel->shutdown();
