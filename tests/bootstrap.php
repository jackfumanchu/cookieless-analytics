<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

// Create the database schema for functional tests
(static function (): void {
    $kernel = new \Jackfumanchu\CookielessAnalyticsBundle\Tests\App\Kernel('test', true);
    $kernel->boot();

    /** @var \Doctrine\ORM\EntityManagerInterface $em */
    $em = $kernel->getContainer()->get('doctrine.orm.entity_manager');
    $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($em);
    $metadata = $em->getMetadataFactory()->getAllMetadata();

    // Drop existing tables directly via DBAL, then recreate schema
    $connection = $em->getConnection();
    $schemaManager = $connection->createSchemaManager();
    $existingTables = $schemaManager->listTableNames();
    foreach ($existingTables as $table) {
        $schemaManager->dropTable($table);
    }

    $schemaTool->createSchema($metadata);

    $kernel->shutdown();
})();
