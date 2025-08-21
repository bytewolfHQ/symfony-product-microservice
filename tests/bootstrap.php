<?php
// tests/bootstrap.php
declare(strict_types=1);

use App\Kernel;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

// .env laden, APP_ENV aus der Umgebung respektieren (bei uns: test)
(new Dotenv())->usePutenv()->bootEnv(dirname(__DIR__).'/.env');

// Kernel booten
$kernel = new Kernel('test', true);
$kernel->boot();

$container = $kernel->getContainer();
$em = $container->get('doctrine')->getManager();

// Für SQLite: Test-DB-Datei neu anlegen
$params = $em->getConnection()->getParams();
if (($params['driver'] ?? null) === 'pdo_sqlite' && isset($params['path'])) {
    $em->getConnection()->close();
    @unlink($params['path']);         // alte Datei weg (idempotent)
    $em->getConnection()->connect();  // neue Verbindung (Datei wird angelegt)
}

// Schema frisch erzeugen
$tool = new SchemaTool($em);
$tool->dropDatabase();
$tool->createSchema($em->getMetadataFactory()->getAllMetadata());

// optional: Minimal-Seeding …

$kernel->shutdown();
