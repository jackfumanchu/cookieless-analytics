# Dashboard Sub-Pages Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the three dashboard sub-pages (Pages, Events, Trends) matching the v3 editorial mockups, using existing repository methods and the shared layout.

**Architecture:** Each sub-page is a full-page controller action extending the shared layout. CSS for any missing components is added incrementally alongside the template. The Pages and Events pages are server-rendered with the first item pre-selected in the detail pane (interactive row selection is a follow-up). Metrics we don't track (avg time, bounce rate, avg duration) show "—" placeholders.

**Tech Stack:** PHP 8.2+, Symfony 7.4, Twig, CSS, uPlot

---

## File Structure

| File | Action | Responsibility |
|------|--------|----------------|
| `templates/dashboard/layout.html.twig` | Modify | Update section nav links to real routes |
| `templates/dashboard/dashboard.css` | Modify | Add any missing CSS alongside each page |
| `src/Controller/DashboardController.php` | Modify | Add `pagesView()`, `eventsView()`, `trendsView()` |
| `templates/dashboard/pages.html.twig` | Create | Pages sub-page |
| `templates/dashboard/events.html.twig` | Create | Events sub-page |
| `templates/dashboard/trends.html.twig` | Create | Trends sub-page |
| `tests/Functional/Controller/DashboardControllerTest.php` | Modify | Tests for the 3 new routes |

---

### Task 1: Update section nav + add Pages sub-page

**Files:**
- Modify: `templates/dashboard/layout.html.twig`
- Modify: `src/Controller/DashboardController.php`
- Create: `templates/dashboard/pages.html.twig`
- Modify: `templates/dashboard/dashboard.css` (if needed)
- Modify: `tests/Functional/Controller/DashboardControllerTest.php`

- [ ] **Step 1: Write the failing functional test**

Add to `tests/Functional/Controller/DashboardControllerTest.php`:

```php
#[Test]
public function pages_view_returns_200_with_page_list(): void
{
    $client = static::createClient();
    $em = self::getContainer()->get(EntityManagerInterface::class);

    $em->persist(PageView::create(
        fingerprint: str_repeat('a', 64),
        pageUrl: '/home',
        referrer: 'https://google.com/search',
        viewedAt: new \DateTimeImmutable('today'),
    ));
    $em->persist(PageView::create(
        fingerprint: str_repeat('b', 64),
        pageUrl: '/home',
        referrer: null,
        viewedAt: new \DateTimeImmutable('today'),
    ));
    $em->persist(PageView::create(
        fingerprint: str_repeat('a', 64),
        pageUrl: '/about',
        referrer: null,
        viewedAt: new \DateTimeImmutable('today'),
    ));
    $em->flush();

    $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
    $client->request('GET', '/analytics/pages?from=' . $today . '&to=' . $today);

    self::assertResponseStatusCodeSame(200);
    $content = $client->getResponse()->getContent();
    self::assertStringContainsString('/home', $content);
    self::assertStringContainsString('/about', $content);
    self::assertStringContainsString('page-layout', $content);
    self::assertStringContainsString('google.com', $content);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit tests/Functional/Controller/DashboardControllerTest.php --filter=pages_view_returns`
Expected: FAIL — route not found

- [ ] **Step 3: Update section nav in layout.html.twig**

Replace the section nav links for Pages, Events, Trends to use real route names:

```twig
<nav class="section-nav">
  <a href="{{ path('cookieless_analytics_dashboard', {from: from, to: to}) }}" {{ active_nav == 'overview' ? 'class="active"' }}>Overview</a>
  <a href="{{ path('cookieless_analytics_dashboard_pages_view', {from: from, to: to}) }}" {{ active_nav == 'pages' ? 'class="active"' }}>Pages</a>
  <a href="{{ path('cookieless_analytics_dashboard_events_view', {from: from, to: to}) }}" {{ active_nav == 'events' ? 'class="active"' }}>Events</a>
  <a href="{{ path('cookieless_analytics_dashboard_trends_view', {from: from, to: to}) }}" {{ active_nav == 'trends' ? 'class="active"' }}>Trends</a>
</nav>
```

- [ ] **Step 4: Add pagesView() controller action**

Add to `DashboardController.php` after `index()`:

```php
#[Route(path: '/pages', name: 'cookieless_analytics_dashboard_pages_view', methods: ['GET'])]
public function pagesView(Request $request): Response
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

    $pages = $this->pageViewRepo->findTopPages($dateRange->from, $dateRange->to, 50);
    $totalPages = count($pages);

    // Pre-select the first page for the detail pane
    $selectedPage = $pages[0]['pageUrl'] ?? null;
    $selectedDetail = null;
    if ($selectedPage !== null) {
        $selectedViews = $this->periodComparer->compare(
            $dateRange,
            fn (\DateTimeImmutable $f, \DateTimeImmutable $t) => (int) $this->pageViewRepo->createQueryBuilder('p')
                ->select('COUNT(p.id)')
                ->where('p.viewedAt >= :from')->andWhere('p.viewedAt <= :to')->andWhere('p.pageUrl = :url')
                ->setParameter('from', $f)->setParameter('to', $t)->setParameter('url', $selectedPage)
                ->getQuery()->getSingleScalarResult()
        );
        $selectedVisitors = $this->periodComparer->compare(
            $dateRange,
            fn (\DateTimeImmutable $f, \DateTimeImmutable $t) => (int) $this->pageViewRepo->createQueryBuilder('p')
                ->select('COUNT(DISTINCT p.fingerprint)')
                ->where('p.viewedAt >= :from')->andWhere('p.viewedAt <= :to')->andWhere('p.pageUrl = :url')
                ->setParameter('from', $f)->setParameter('to', $t)->setParameter('url', $selectedPage)
                ->getQuery()->getSingleScalarResult()
        );
        $selectedDaily = $this->pageViewRepo->countByDayForPage($selectedPage, $dateRange->from, $dateRange->to);
        $selectedReferrers = $this->pageViewRepo->findTopReferrersForPage($selectedPage, $dateRange->from, $dateRange->to, 5);

        $selectedDetail = [
            'pageUrl' => $selectedPage,
            'views' => $selectedViews,
            'visitors' => $selectedVisitors,
            'daily' => $selectedDaily,
            'referrers' => $selectedReferrers,
        ];
    }

    $html = $this->twig->render('@CookielessAnalytics/dashboard/pages.html.twig', [
        'from' => $dateRange->from->format('Y-m-d'),
        'to' => $dateRange->to->format('Y-m-d'),
        'layout' => $this->dashboardLayout ?? '@CookielessAnalytics/dashboard/layout.html.twig',
        'active_nav' => 'pages',
        'pages' => $pages,
        'totalPages' => $totalPages,
        'selectedDetail' => $selectedDetail,
    ]);

    return new Response($html);
}
```

- [ ] **Step 5: Create pages.html.twig**

Create `templates/dashboard/pages.html.twig` matching the v3 mockup structure — two-pane layout with ranked page list and detail pane showing views, visitors, "Avg. Time" (—), "Bounce" (—), period trend chart, and top referrers. Use the mockup at `docs/mockup/v3/pages_v3.html` as reference for the HTML structure and CSS classes. Reuse existing CSS classes (`.ed-table`, `.section-head`, `.ref-item`, etc.) where possible. Add any missing CSS rules to `dashboard.css`.

- [ ] **Step 6: Run test to verify it passes**

Run: `php vendor/bin/phpunit tests/Functional/Controller/DashboardControllerTest.php --filter=pages_view_returns`
Expected: PASS

- [ ] **Step 7: Run full suite**

Run: `php vendor/bin/phpunit`
Expected: All tests pass

- [ ] **Step 8: Commit**

```bash
git add templates/dashboard/layout.html.twig src/Controller/DashboardController.php templates/dashboard/pages.html.twig templates/dashboard/dashboard.css tests/Functional/Controller/DashboardControllerTest.php
git commit -m "feat: add Pages sub-page with two-pane layout and detail panel"
```

---

### Task 2: Events sub-page

**Files:**
- Modify: `src/Controller/DashboardController.php`
- Create: `templates/dashboard/events.html.twig`
- Modify: `templates/dashboard/dashboard.css` (if needed)
- Modify: `tests/Functional/Controller/DashboardControllerTest.php`

- [ ] **Step 1: Write the failing functional test**

Add to `tests/Functional/Controller/DashboardControllerTest.php`:

```php
#[Test]
public function events_view_returns_200_with_event_list(): void
{
    $client = static::createClient();
    $em = self::getContainer()->get(EntityManagerInterface::class);

    $em->persist(AnalyticsEvent::create(
        fingerprint: str_repeat('a', 64),
        name: 'click-cta',
        value: 'hero-button',
        pageUrl: '/home',
        recordedAt: new \DateTimeImmutable('today'),
    ));
    $em->persist(AnalyticsEvent::create(
        fingerprint: str_repeat('b', 64),
        name: 'click-cta',
        value: 'footer-button',
        pageUrl: '/pricing',
        recordedAt: new \DateTimeImmutable('today'),
    ));
    $em->persist(AnalyticsEvent::create(
        fingerprint: str_repeat('a', 64),
        name: 'signup',
        value: null,
        pageUrl: '/home',
        recordedAt: new \DateTimeImmutable('today'),
    ));
    $em->flush();

    $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
    $client->request('GET', '/analytics/events?from=' . $today . '&to=' . $today);

    self::assertResponseStatusCodeSame(200);
    $content = $client->getResponse()->getContent();
    self::assertStringContainsString('click-cta', $content);
    self::assertStringContainsString('signup', $content);
    self::assertStringContainsString('event-layout', $content);
    self::assertStringContainsString('summary-strip', $content);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit tests/Functional/Controller/DashboardControllerTest.php --filter=events_view_returns`
Expected: FAIL — route not found

- [ ] **Step 3: Add eventsView() controller action**

Add to `DashboardController.php`:

```php
#[Route(path: '/events', name: 'cookieless_analytics_dashboard_events_view', methods: ['GET'])]
public function eventsView(Request $request): Response
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

    $events = $this->eventRepo->findTopEvents($dateRange->from, $dateRange->to, 50);
    $totalEvents = $this->eventRepo->countByPeriod($dateRange->from, $dateRange->to);
    $distinctTypes = $this->eventRepo->countDistinctTypes($dateRange->from, $dateRange->to);
    $uniqueActors = $this->eventRepo->countUniqueActors($dateRange->from, $dateRange->to);
    $topEventName = $events[0]['name'] ?? null;

    $selectedDetail = null;
    if ($topEventName !== null) {
        $selectedDaily = $this->eventRepo->countByDayForEvent($topEventName, $dateRange->from, $dateRange->to);
        $selectedValues = $this->eventRepo->findValueBreakdown($topEventName, $dateRange->from, $dateRange->to, 10);
        $selectedPages = $this->eventRepo->findTopPagesForEvent($topEventName, $dateRange->from, $dateRange->to, 5);

        $selectedDetail = [
            'name' => $topEventName,
            'occurrences' => (int) $events[0]['occurrences'],
            'distinctValues' => (int) $events[0]['distinctValues'],
            'daily' => $selectedDaily,
            'values' => $selectedValues,
            'pages' => $selectedPages,
        ];
    }

    $html = $this->twig->render('@CookielessAnalytics/dashboard/events.html.twig', [
        'from' => $dateRange->from->format('Y-m-d'),
        'to' => $dateRange->to->format('Y-m-d'),
        'layout' => $this->dashboardLayout ?? '@CookielessAnalytics/dashboard/layout.html.twig',
        'active_nav' => 'events',
        'events' => $events,
        'totalEvents' => $totalEvents,
        'distinctTypes' => $distinctTypes,
        'uniqueActors' => $uniqueActors,
        'topEventName' => $topEventName,
        'selectedDetail' => $selectedDetail,
    ]);

    return new Response($html);
}
```

- [ ] **Step 4: Create events.html.twig**

Create `templates/dashboard/events.html.twig` matching the v3 mockup — summary strip (total events, distinct types, most frequent, unique actors), two-pane layout with event table (name, bar, count, unique) and detail pane showing count, distinct values, period trend chart, value breakdown with bars, and top pages list. Use `docs/mockup/v3/events_v3.html` as reference. Add any missing CSS to `dashboard.css`.

- [ ] **Step 5: Run test to verify it passes**

Run: `php vendor/bin/phpunit tests/Functional/Controller/DashboardControllerTest.php --filter=events_view_returns`
Expected: PASS

- [ ] **Step 6: Run full suite**

Run: `php vendor/bin/phpunit`
Expected: All tests pass

- [ ] **Step 7: Commit**

```bash
git add src/Controller/DashboardController.php templates/dashboard/events.html.twig templates/dashboard/dashboard.css tests/Functional/Controller/DashboardControllerTest.php
git commit -m "feat: add Events sub-page with summary strip and detail panel"
```

---

### Task 3: Trends sub-page

**Files:**
- Modify: `src/Controller/DashboardController.php`
- Create: `templates/dashboard/trends.html.twig`
- Modify: `templates/dashboard/dashboard.css` (if needed)
- Modify: `tests/Functional/Controller/DashboardControllerTest.php`

- [ ] **Step 1: Write the failing functional test**

Add to `tests/Functional/Controller/DashboardControllerTest.php`:

```php
#[Test]
public function trends_view_returns_200_with_charts(): void
{
    $client = static::createClient();
    $em = self::getContainer()->get(EntityManagerInterface::class);

    for ($i = 0; $i < 3; $i++) {
        $date = (new \DateTimeImmutable('today'))->modify("-{$i} days");
        $em->persist(PageView::create(
            fingerprint: str_repeat('a', 64),
            pageUrl: '/home',
            referrer: null,
            viewedAt: $date,
        ));
    }
    $em->persist(AnalyticsEvent::create(
        fingerprint: str_repeat('a', 64),
        name: 'click-cta',
        value: null,
        pageUrl: '/home',
        recordedAt: new \DateTimeImmutable('today'),
    ));
    $em->flush();

    $from = (new \DateTimeImmutable('today'))->modify('-6 days')->format('Y-m-d');
    $to = (new \DateTimeImmutable('today'))->format('Y-m-d');
    $client->request('GET', '/analytics/trends?from=' . $from . '&to=' . $to);

    self::assertResponseStatusCodeSame(200);
    $content = $client->getResponse()->getContent();
    self::assertStringContainsString('hero-chart', $content);
    self::assertStringContainsString('numbers-strip', $content);
    self::assertStringContainsString('data-chart-dates-value', $content);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit tests/Functional/Controller/DashboardControllerTest.php --filter=trends_view_returns`
Expected: FAIL — route not found

- [ ] **Step 3: Add trendsView() controller action**

Add to `DashboardController.php`:

```php
#[Route(path: '/trends', name: 'cookieless_analytics_dashboard_trends_view', methods: ['GET'])]
public function trendsView(Request $request): Response
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

    $daily = $this->pageViewRepo->countByDay($dateRange->from, $dateRange->to);
    $dates = array_map(fn (array $row) => $row['date'], $daily);
    $views = array_map(fn (array $row) => (int) $row['count'], $daily);
    $visitors = array_map(fn (array $row) => (int) $row['unique'], $daily);

    $prevDaily = $this->pageViewRepo->countByDay($dateRange->comparisonFrom, $dateRange->comparisonTo);
    $prevViews = array_map(fn (array $row) => (int) $row['count'], $prevDaily);

    $totalViews = array_sum($views);
    $totalVisitors = array_sum($visitors);
    $numDays = count($views);
    $dailyAvgViews = $numDays > 0 ? (int) round($totalViews / $numDays) : 0;
    $dailyAvgVisitors = $numDays > 0 ? (int) round($totalVisitors / $numDays) : 0;

    $peakDay = null;
    $lowDay = null;
    if ($numDays > 0) {
        $maxIdx = array_search(max($views), $views, true);
        $minIdx = array_search(min($views), $views, true);
        $peakDay = ['date' => $dates[$maxIdx], 'views' => $views[$maxIdx]];
        $lowDay = ['date' => $dates[$minIdx], 'views' => $views[$minIdx]];
    }

    $weekdayViews = [];
    $weekendViews = [];
    foreach ($daily as $row) {
        $dow = (int) (new \DateTimeImmutable($row['date']))->format('N');
        if ($dow <= 5) {
            $weekdayViews[] = (int) $row['count'];
        } else {
            $weekendViews[] = (int) $row['count'];
        }
    }
    $weekdayAvg = count($weekdayViews) > 0 ? (int) round(array_sum($weekdayViews) / count($weekdayViews)) : 0;
    $weekendAvg = count($weekendViews) > 0 ? (int) round(array_sum($weekendViews) / count($weekendViews)) : 0;

    $pageViewsComparison = $this->periodComparer->compare($dateRange, $this->pageViewRepo->countByPeriod(...));
    $visitorsComparison = $this->periodComparer->compare($dateRange, $this->pageViewRepo->countUniqueVisitorsByPeriod(...));
    $eventsComparison = $this->periodComparer->compare($dateRange, $this->eventRepo->countByPeriod(...));
    $pagesPerVisitor = $visitorsComparison->current > 0 ? round($pageViewsComparison->current / $visitorsComparison->current, 1) : 0.0;
    $prevPagesPerVisitor = $visitorsComparison->previous > 0 ? round($pageViewsComparison->previous / $visitorsComparison->previous, 1) : 0.0;
    $pagesPerVisitorComparison = PeriodComparison::fromFloat($pagesPerVisitor, $prevPagesPerVisitor);

    $html = $this->twig->render('@CookielessAnalytics/dashboard/trends.html.twig', [
        'from' => $dateRange->from->format('Y-m-d'),
        'to' => $dateRange->to->format('Y-m-d'),
        'layout' => $this->dashboardLayout ?? '@CookielessAnalytics/dashboard/layout.html.twig',
        'active_nav' => 'trends',
        'dates' => json_encode($dates),
        'views' => json_encode($views),
        'visitors' => json_encode($visitors),
        'prevViews' => json_encode($prevViews),
        'peakDay' => $peakDay,
        'lowDay' => $lowDay,
        'dailyAvgViews' => $dailyAvgViews,
        'dailyAvgVisitors' => $dailyAvgVisitors,
        'weekdayAvg' => $weekdayAvg,
        'weekendAvg' => $weekendAvg,
        'pageViewsComparison' => $pageViewsComparison,
        'visitorsComparison' => $visitorsComparison,
        'eventsComparison' => $eventsComparison,
        'pagesPerVisitorComparison' => $pagesPerVisitorComparison,
    ]);

    return new Response($html);
}
```

- [ ] **Step 4: Create trends.html.twig**

Create `templates/dashboard/trends.html.twig` matching the v3 mockup — hero chart area (uPlot with dates/views/visitors), numbers strip (peak day, low day, daily avg, avg visitors, weekday avg, weekend avg), small multiples grid (6 cards: page views, visitors, events, pages/visitor, bounce rate as "—", avg duration as "—"), each with value and change percentage. Use `docs/mockup/v3/trends_v3.html` as reference. Add any missing CSS to `dashboard.css`.

- [ ] **Step 5: Run test to verify it passes**

Run: `php vendor/bin/phpunit tests/Functional/Controller/DashboardControllerTest.php --filter=trends_view_returns`
Expected: PASS

- [ ] **Step 6: Run full suite**

Run: `php vendor/bin/phpunit`
Expected: All tests pass

- [ ] **Step 7: Commit**

```bash
git add src/Controller/DashboardController.php templates/dashboard/trends.html.twig templates/dashboard/dashboard.css tests/Functional/Controller/DashboardControllerTest.php
git commit -m "feat: add Trends sub-page with hero chart, numbers strip, and small multiples"
```

---

### Task 4: Final verification

- [ ] **Step 1: Run full test suite**

Run: `php vendor/bin/phpunit`
Expected: All tests pass

- [ ] **Step 2: Verify all pages in browser**

- `/analytics/` — Overview, nav highlights "Overview"
- `/analytics/pages` — Pages, nav highlights "Pages", detail pane shows first page
- `/analytics/events` — Events, nav highlights "Events", summary strip + detail pane
- `/analytics/trends` — Trends, nav highlights "Trends", hero chart + numbers + multiples
- Section nav links work between all pages, preserving date range

- [ ] **Step 3: Commit any remaining fixes**
