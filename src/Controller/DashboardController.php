<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Controller;

use Jackfumanchu\CookielessAnalyticsBundle\Repository\AnalyticsEventRepository;
use Jackfumanchu\CookielessAnalyticsBundle\Repository\PageViewRepository;
use Jackfumanchu\CookielessAnalyticsBundle\Service\DateRangeResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Environment;

class DashboardController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly DateRangeResolver $dateRangeResolver,
        private readonly PageViewRepository $pageViewRepo,
        private readonly AnalyticsEventRepository $eventRepo,
        private readonly ?AuthorizationCheckerInterface $authorizationChecker,
        private readonly string $dashboardRole,
        private readonly ?string $dashboardLayout,
    ) {
    }

    #[Route(path: '/', name: 'cookieless_analytics_dashboard', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted();

        $from = $request->query->get('from');
        $to = $request->query->get('to');
        $dateRange = $this->dateRangeResolver->resolve(
            is_string($from) ? $from : null,
            is_string($to) ? $to : null,
        );

        $html = $this->twig->render('@CookielessAnalytics/dashboard/index.html.twig', [
            'from' => $dateRange->from->format('Y-m-d'),
            'to' => $dateRange->to->format('Y-m-d'),
            'layout' => $this->dashboardLayout ?? '@CookielessAnalytics/dashboard/layout.html.twig',
        ]);

        return new Response($html);
    }

    #[Route(path: '/overview', name: 'cookieless_analytics_dashboard_overview', methods: ['GET'])]
    public function overview(Request $request): Response
    {
        return new Response('<turbo-frame id="ca-overview"><p>Coming soon</p></turbo-frame>');
    }

    #[Route(path: '/trends', name: 'cookieless_analytics_dashboard_trends', methods: ['GET'])]
    public function trends(Request $request): Response
    {
        return new Response('<turbo-frame id="ca-trends"><p>Coming soon</p></turbo-frame>');
    }

    #[Route(path: '/top-pages', name: 'cookieless_analytics_dashboard_top_pages', methods: ['GET'])]
    public function topPages(Request $request): Response
    {
        return new Response('<turbo-frame id="ca-top-pages"><p>Coming soon</p></turbo-frame>');
    }

    #[Route(path: '/events', name: 'cookieless_analytics_dashboard_events', methods: ['GET'])]
    public function events(Request $request): Response
    {
        return new Response('<turbo-frame id="ca-events"><p>Coming soon</p></turbo-frame>');
    }

    private function denyAccessUnlessGranted(): void
    {
        if ($this->authorizationChecker === null) {
            return;
        }

        if (!$this->authorizationChecker->isGranted($this->dashboardRole)) {
            throw new AccessDeniedException();
        }
    }
}
