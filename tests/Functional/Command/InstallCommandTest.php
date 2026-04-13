<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\Functional\Command;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class InstallCommandTest extends KernelTestCase
{
    #[Test]
    public function install_returns_success(): void
    {
        $kernel = static::bootKernel();
        $application = new Application($kernel);

        $command = $application->find('cookieless:install');
        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        self::assertStringContainsString('CookielessAnalytics', $output);
    }

    #[Test]
    public function install_is_idempotent(): void
    {
        $kernel = static::bootKernel();
        $application = new Application($kernel);

        $command = $application->find('cookieless:install');

        // First run
        $tester = new CommandTester($command);
        $tester->execute([]);
        self::assertSame(0, $tester->getStatusCode());

        // Second run — should succeed with "nothing to do"
        $tester->execute([]);
        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Nothing to do', $tester->getDisplay());
    }
}
