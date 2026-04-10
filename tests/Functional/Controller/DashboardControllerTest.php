<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\Functional\Controller;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DashboardControllerTest extends WebTestCase
{
    #[Test]
    public function index_returns_200_with_dashboard_content(): void
    {
        $client = static::createClient();

        $client->request('GET', '/analytics/');

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorExists('turbo-frame#ca-overview');
        self::assertSelectorExists('turbo-frame#ca-top-pages');
        self::assertSelectorExists('turbo-frame#ca-events');
        self::assertSelectorExists('turbo-frame#ca-trends');
    }

    #[Test]
    public function index_contains_date_range_selector(): void
    {
        $client = static::createClient();

        $client->request('GET', '/analytics/');

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorExists('[data-controller="date-range"]');
        self::assertSelectorExists('input[name="from"]');
        self::assertSelectorExists('input[name="to"]');
    }
}
