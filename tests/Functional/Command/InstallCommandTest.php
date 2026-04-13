<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\Functional\Command;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class InstallCommandTest extends KernelTestCase
{
    #[Test]
    public function install_creates_tables_on_fresh_database(): void
    {
        $kernel = static::bootKernel();
        $application = new Application($kernel);

        // Drop both tables to simulate a fresh database
        $conn = $kernel->getContainer()->get('test.service_container')
            ->get(EntityManagerInterface::class)->getConnection();
        $conn->executeStatement('DROP TABLE IF EXISTS ca_page_view');
        $conn->executeStatement('DROP TABLE IF EXISTS ca_analytics_event');

        $command = $application->find('cookieless:install');
        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        self::assertStringContainsString('installed successfully', $output);
        self::assertStringNotContainsString('Nothing to do', $output);

        // Verify both tables were created by inserting into them
        $conn->executeStatement("INSERT INTO ca_page_view (fingerprint, page_url, viewed_at) VALUES ('abc', '/test', '2026-01-01 00:00:00')");
        $conn->executeStatement("INSERT INTO ca_analytics_event (fingerprint, name, page_url, recorded_at) VALUES ('abc', 'test', '/test', '2026-01-01 00:00:00')");
        $count = $conn->fetchOne('SELECT COUNT(*) FROM ca_page_view');
        self::assertSame(1, (int) $count);
    }

    #[Test]
    public function install_is_idempotent(): void
    {
        $kernel = static::bootKernel();
        $application = new Application($kernel);

        $command = $application->find('cookieless:install');

        // First run — tables already exist from bootstrap
        $tester = new CommandTester($command);
        $tester->execute([]);
        self::assertSame(0, $tester->getStatusCode());

        // Second run — should report nothing to do, not "installed successfully"
        $tester->execute([]);
        self::assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        self::assertStringContainsString('Nothing to do', $output);
        self::assertStringNotContainsString('installed successfully', $output);
    }
}
