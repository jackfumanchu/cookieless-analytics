<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Controller;

use Jackfumanchu\CookielessAnalyticsBundle\Repository\AnalyticsEventRepository;
use Jackfumanchu\CookielessAnalyticsBundle\Repository\PageViewRepository;
use Jackfumanchu\CookielessAnalyticsBundle\Service\DateRangeResolver;
use Jackfumanchu\CookielessAnalyticsBundle\Service\PeriodComparison;
use Jackfumanchu\CookielessAnalyticsBundle\Service\PeriodComparer;
use Jackfumanchu\CookielessAnalyticsBundle\Service\DateRange;
use Symfony\Component\HttpFoundation\RedirectResponse;
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
        private readonly PeriodComparer $periodComparer,
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

        if ($redirect = $this->redirectIfDatesNormalized($request, $dateRange)) {
            return $redirect;
        }

        $html = $this->twig->render('@CookielessAnalytics/dashboard/index.html.twig', [
            'from' => $dateRange->from->format('Y-m-d'),
            'to' => $dateRange->to->format('Y-m-d'),
            'layout' => $this->dashboardLayout ?? '@CookielessAnalytics/dashboard/layout.html.twig',
            'active_nav' => 'overview',
        ]);

        return new Response($html);
    }

    #[Route(path: '/frame/overview', name: 'cookieless_analytics_dashboard_overview', methods: ['GET'])]
    public function overview(Request $request): Response
    {
        $this->denyAccessUnlessGranted();

        $dateRange = $this->dateRangeResolver->resolve(
            $request->query->getString('from') ?: null,
            $request->query->getString('to') ?: null,
        );

        if ($redirect = $this->redirectIfDatesNormalized($request, $dateRange)) {
            return $redirect;
        }

        $pageViews = $this->periodComparer->compare($dateRange, $this->pageViewRepo->countByPeriod(...));
        $uniqueVisitors = $this->periodComparer->compare($dateRange, $this->pageViewRepo->countUniqueVisitorsByPeriod(...));
        $events = $this->periodComparer->compare($dateRange, $this->eventRepo->countByPeriod(...));

        $pagesPerVisitor = $uniqueVisitors->current > 0
            ? round($pageViews->current / $uniqueVisitors->current, 1)
            : 0.0;
        $prevPagesPerVisitor = $uniqueVisitors->previous > 0
            ? round($pageViews->previous / $uniqueVisitors->previous, 1)
            : 0.0;
        $pagesPerVisitorComparison = PeriodComparison::fromFloat($pagesPerVisitor, $prevPagesPerVisitor);

        $html = $this->twig->render('@CookielessAnalytics/dashboard/partials/_overview.html.twig', [
            'pageViews' => $pageViews,
            'uniqueVisitors' => $uniqueVisitors,
            'events' => $events,
            'pagesPerVisitor' => $pagesPerVisitorComparison,
        ]);

        return new Response($html);
    }

    #[Route(path: '/frame/trends', name: 'cookieless_analytics_dashboard_trends', methods: ['GET'])]
    public function trends(Request $request): Response
    {
        $this->denyAccessUnlessGranted();

        $dateRange = $this->dateRangeResolver->resolve(
            $request->query->getString('from') ?: null,
            $request->query->getString('to') ?: null,
        );

        if ($redirect = $this->redirectIfDatesNormalized($request, $dateRange)) {
            return $redirect;
        }

        $daily = $this->pageViewRepo->countByDay($dateRange->from, $dateRange->to);

        $dates = array_map(fn (array $row) => $row['date'], $daily);
        $views = array_map(fn (array $row) => (int) $row['count'], $daily);
        $visitors = array_map(fn (array $row) => (int) $row['unique'], $daily);

        $html = $this->twig->render('@CookielessAnalytics/dashboard/partials/_trends.html.twig', [
            'dates' => json_encode($dates),
            'views' => json_encode($views),
            'visitors' => json_encode($visitors),
        ]);

        return new Response($html);
    }

    #[Route(path: '/frame/top-pages', name: 'cookieless_analytics_dashboard_top_pages', methods: ['GET'])]
    public function topPages(Request $request): Response
    {
        $this->denyAccessUnlessGranted();

        $dateRange = $this->dateRangeResolver->resolve(
            $request->query->getString('from') ?: null,
            $request->query->getString('to') ?: null,
        );

        if ($redirect = $this->redirectIfDatesNormalized($request, $dateRange)) {
            return $redirect;
        }

        $pages = $this->pageViewRepo->findTopPages($dateRange->from, $dateRange->to, 10);

        $html = $this->twig->render('@CookielessAnalytics/dashboard/partials/_top_pages.html.twig', [
            'pages' => $pages,
        ]);

        return new Response($html);
    }

    #[Route(path: '/frame/referrers', name: 'cookieless_analytics_dashboard_referrers', methods: ['GET'])]
    public function referrers(Request $request): Response
    {
        $this->denyAccessUnlessGranted();

        $dateRange = $this->dateRangeResolver->resolve(
            $request->query->getString('from') ?: null,
            $request->query->getString('to') ?: null,
        );

        if ($redirect = $this->redirectIfDatesNormalized($request, $dateRange)) {
            return $redirect;
        }

        $referrers = $this->pageViewRepo->findTopReferrers($dateRange->from, $dateRange->to, 10);
        $totalVisits = array_sum(array_column($referrers, 'visits'));

        $html = $this->twig->render('@CookielessAnalytics/dashboard/partials/_referrers.html.twig', [
            'referrers' => $referrers,
            'totalVisits' => $totalVisits,
        ]);

        return new Response($html);
    }

    #[Route(path: '/frame/events', name: 'cookieless_analytics_dashboard_events', methods: ['GET'])]
    public function events(Request $request): Response
    {
        $this->denyAccessUnlessGranted();

        $dateRange = $this->dateRangeResolver->resolve(
            $request->query->getString('from') ?: null,
            $request->query->getString('to') ?: null,
        );

        if ($redirect = $this->redirectIfDatesNormalized($request, $dateRange)) {
            return $redirect;
        }

        $events = $this->eventRepo->findTopEvents($dateRange->from, $dateRange->to, 10);

        $html = $this->twig->render('@CookielessAnalytics/dashboard/partials/_events.html.twig', [
            'events' => $events,
        ]);

        return new Response($html);
    }

    private function redirectIfDatesNormalized(Request $request, DateRange $dateRange): ?RedirectResponse
    {
        $inputFrom = $request->query->getString('from');
        $inputTo = $request->query->getString('to');
        $resolvedFrom = $dateRange->from->format('Y-m-d');
        $resolvedTo = $dateRange->to->format('Y-m-d');

        if ($inputFrom !== '' && $inputTo !== '' && ($inputFrom !== $resolvedFrom || $inputTo !== $resolvedTo)) {
            $url = $request->getPathInfo() . '?' . http_build_query(['from' => $resolvedFrom, 'to' => $resolvedTo]);

            return new RedirectResponse($url, 302);
        }

        return null;
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
