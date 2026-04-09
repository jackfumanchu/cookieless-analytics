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

    $schemaTool->dropSchema($metadata);
    $schemaTool->createSchema($metadata);

    $kernel->shutdown();
})();
