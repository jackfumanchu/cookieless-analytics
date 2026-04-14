<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Command;

use Doctrine\DBAL\Connection;
use Jackfumanchu\CookielessAnalyticsBundle\Service\SqlDialect;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(
    name: 'cookieless:install',
    description: 'Create or update the CookielessAnalytics database tables',
)]
class InstallCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        private readonly SqlDialect $sqlDialect,
        private readonly KernelInterface $kernel,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $idColumn = $this->sqlDialect->autoIncrementId();
        $datetimeType = $this->sqlDialect->datetimeType();

        $this->connection->executeStatement(<<<SQL
            CREATE TABLE IF NOT EXISTS ca_page_view (
                {$idColumn},
                fingerprint VARCHAR(64) NOT NULL,
                page_url VARCHAR(2048) NOT NULL,
                referrer VARCHAR(2048) DEFAULT NULL,
                viewed_at {$datetimeType} NOT NULL
            )
            SQL);

        $this->connection->executeStatement(<<<SQL
            CREATE TABLE IF NOT EXISTS ca_analytics_event (
                {$idColumn},
                fingerprint VARCHAR(64) NOT NULL,
                name VARCHAR(255) NOT NULL,
                value VARCHAR(2048) DEFAULT NULL,
                page_url VARCHAR(2048) NOT NULL,
                recorded_at {$datetimeType} NOT NULL
            )
            SQL);

        $this->connection->executeStatement('CREATE INDEX IF NOT EXISTS idx_fingerprint ON ca_page_view (fingerprint)');
        $this->connection->executeStatement('CREATE INDEX IF NOT EXISTS idx_viewed_at ON ca_page_view (viewed_at)');
        $this->connection->executeStatement('CREATE INDEX IF NOT EXISTS idx_page_url ON ca_page_view (page_url)');

        $this->connection->executeStatement('CREATE INDEX IF NOT EXISTS idx_event_fingerprint ON ca_analytics_event (fingerprint)');
        $this->connection->executeStatement('CREATE INDEX IF NOT EXISTS idx_event_recorded_at ON ca_analytics_event (recorded_at)');
        $this->connection->executeStatement('CREATE INDEX IF NOT EXISTS idx_event_name ON ca_analytics_event (name)');

        $this->installRouteConfig($io);

        $io->success('CookielessAnalytics installed successfully.');

        return Command::SUCCESS;
    }

    private function installRouteConfig(SymfonyStyle $io): void
    {
        $routesDir = $this->kernel->getProjectDir() . '/config/routes';
        $routeFile = $routesDir . '/cookieless_analytics.yaml';

        if (file_exists($routeFile)) {
            return;
        }

        if (!is_dir($routesDir)) {
            return;
        }

        file_put_contents($routeFile, <<<'YAML'
            cookieless_analytics:
                resource: Jackfumanchu\CookielessAnalyticsBundle\Routing\RouteLoader
                type: service
            YAML. "\n");

        $io->note('Created config/routes/cookieless_analytics.yaml');
    }
}
