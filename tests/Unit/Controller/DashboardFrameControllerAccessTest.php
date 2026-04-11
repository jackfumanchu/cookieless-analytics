<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\Unit\Controller;

use Jackfumanchu\CookielessAnalyticsBundle\Controller\DashboardFrameController;
use Jackfumanchu\CookielessAnalyticsBundle\Repository\AnalyticsEventRepository;
use Jackfumanchu\CookielessAnalyticsBundle\Repository\PageViewRepository;
use Jackfumanchu\CookielessAnalyticsBundle\Service\DateRangeResolver;
use Jackfumanchu\CookielessAnalyticsBundle\Service\PeriodComparer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Environment;

class DashboardFrameControllerAccessTest extends TestCase
{
    private DashboardFrameController $controller;

    protected function setUp(): void
    {
        $authChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturn(false);

        $this->controller = new DashboardFrameController(
            $this->createStub(Environment::class),
            $this->createStub(DateRangeResolver::class),
            $this->createStub(PageViewRepository::class),
            $this->createStub(AnalyticsEventRepository::class),
            $this->createStub(PeriodComparer::class),
            $authChecker,
            'ROLE_ANALYTICS',
        );
    }

    #[Test]
    public function overview_throws_access_denied_when_not_granted(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->controller->overview(Request::create('/frame/overview'));
    }

    #[Test]
    public function trends_throws_access_denied_when_not_granted(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->controller->trends(Request::create('/frame/trends'));
    }

    #[Test]
    public function top_pages_throws_access_denied_when_not_granted(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->controller->topPages(Request::create('/frame/top-pages'));
    }

    #[Test]
    public function referrers_throws_access_denied_when_not_granted(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->controller->referrers(Request::create('/frame/referrers'));
    }

    #[Test]
    public function events_throws_access_denied_when_not_granted(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->controller->events(Request::create('/frame/events'));
    }
}
