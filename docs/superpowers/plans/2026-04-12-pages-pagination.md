# Pages Pagination Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add numbered pagination to the Pages list with 20 results per page, composing with existing search and date range features.

**Architecture:** Add offset to repository query + total count method. Controller reads `?page=N`, clamps to valid range, computes pagination metadata. Pagination controls render as plain `<a>` links inside the Turbo Frame — Turbo handles frame updates automatically. Search resets to page 1, date-range changes are clamped server-side.

**Tech Stack:** PHP 8.3, Symfony 7, Doctrine ORM, Hotwire (Turbo), PostgreSQL, PHPUnit

---

## File Structure

| File | Action | Responsibility |
|------|--------|----------------|
| `src/Repository/PageViewRepository.php` | Modify | Add `$offset` param to `findTopPages()`, add `countDistinctPages()` |
| `src/Controller/DashboardController.php` | Modify | Read `?page=`, compute pagination metadata, pass to templates |
| `templates/dashboard/pages/_pagination.html.twig` | Create | Reusable pagination controls partial |
| `templates/dashboard/pages/pages.html.twig` | Modify | Include pagination, fix rank numbering offset |
| `templates/dashboard/pages/_pages_list.html.twig` | Modify | Include pagination, fix rank numbering offset |
| `templates/dashboard/dashboard.css` | Modify | Pagination styles |
| `tests/Unit/Repository/PageViewRepositoryTest.php` | Modify | Tests for offset and count |
| `tests/Functional/Controller/DashboardControllerTest.php` | Modify | Tests for pagination, clamping, search+page |

---

### Task 1: Repository — add offset to `findTopPages()` and new `countDistinctPages()`

**Files:**
- Modify: `src/Repository/PageViewRepository.php:76-93`
- Test: `tests/Unit/Repository/PageViewRepositoryTest.php`

- [ ] **Step 1: Write the failing test for offset**

Add to `tests/Unit/Repository/PageViewRepositoryTest.php`:

```php
#[Test]
public function find_top_pages_with_offset_skips_results(): void
{
    $fp = str_repeat('a', 64);
    $fp2 = str_repeat('b', 64);

    // /home: 3 views, /about: 2 views, /contact: 1 view
    $this->createPageView('/home', $fp, '2026-04-05');
    $this->createPageView('/home', $fp2, '2026-04-05');
    $this->createPageView('/home', $fp, '2026-04-06');
    $this->createPageView('/about', $fp, '2026-04-05');
    $this->createPageView('/about', $fp2, '2026-04-06');
    $this->createPageView('/contact', $fp, '2026-04-05');

    $from = new \DateTimeImmutable('2026-04-05 00:00:00');
    $to = new \DateTimeImmutable('2026-04-07 23:59:59');

    $result = $this->repository->findTopPages($from, $to, 2, null, 1);

    self::assertCount(2, $result);
    self::assertSame('/about', $result[0]['pageUrl']);
    self::assertSame('/contact', $result[1]['pageUrl']);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Repository/PageViewRepositoryTest.php --filter find_top_pages_with_offset`

Expected: FAIL — `findTopPages()` does not accept a 5th parameter.

- [ ] **Step 3: Write the failing test for `countDistinctPages()`**

Add to `tests/Unit/Repository/PageViewRepositoryTest.php`:

```php
#[Test]
public function count_distinct_pages_returns_total(): void
{
    $fp = str_repeat('a', 64);

    $this->createPageView('/home', $fp, '2026-04-05');
    $this->createPageView('/home', $fp, '2026-04-06');
    $this->createPageView('/about', $fp, '2026-04-05');
    $this->createPageView('/contact', $fp, '2026-04-06');

    $from = new \DateTimeImmutable('2026-04-05 00:00:00');
    $to = new \DateTimeImmutable('2026-04-07 23:59:59');

    self::assertSame(3, $this->repository->countDistinctPages($from, $to));
}

#[Test]
public function count_distinct_pages_with_search_filters(): void
{
    $fp = str_repeat('a', 64);

    $this->createPageView('/en/blog/hello', $fp, '2026-04-05');
    $this->createPageView('/en/blog/world', $fp, '2026-04-05');
    $this->createPageView('/en/about', $fp, '2026-04-05');

    $from = new \DateTimeImmutable('2026-04-05 00:00:00');
    $to = new \DateTimeImmutable('2026-04-07 23:59:59');

    self::assertSame(2, $this->repository->countDistinctPages($from, $to, 'blog'));
}
```

- [ ] **Step 4: Implement offset in `findTopPages()` and new `countDistinctPages()`**

In `src/Repository/PageViewRepository.php`, replace the `findTopPages` method and add the new method:

```php
public function findTopPages(\DateTimeImmutable $from, \DateTimeImmutable $to, int $limit = 20, ?string $search = null, int $offset = 0): array
{
    $qb = $this->createQueryBuilder('p')
        ->select('p.pageUrl, COUNT(p.id) AS views, COUNT(DISTINCT p.fingerprint) AS uniqueVisitors')
        ->where('p.viewedAt >= :from')
        ->andWhere('p.viewedAt <= :to')
        ->setParameter('from', $from)
        ->setParameter('to', $to);

    if ($search !== null && $search !== '') {
        $qb->andWhere('p.pageUrl LIKE :search')
            ->setParameter('search', '%' . $search . '%');
    }

    return $qb->groupBy('p.pageUrl')
        ->orderBy('views', 'DESC')
        ->setFirstResult($offset)
        ->setMaxResults($limit)
        ->getQuery()
        ->getResult();
}

public function countDistinctPages(\DateTimeImmutable $from, \DateTimeImmutable $to, ?string $search = null): int
{
    $qb = $this->createQueryBuilder('p')
        ->select('COUNT(DISTINCT p.pageUrl)')
        ->where('p.viewedAt >= :from')
        ->andWhere('p.viewedAt <= :to')
        ->setParameter('from', $from)
        ->setParameter('to', $to);

    if ($search !== null && $search !== '') {
        $qb->andWhere('p.pageUrl LIKE :search')
            ->setParameter('search', '%' . $search . '%');
    }

    return (int) $qb->getQuery()->getSingleScalarResult();
}
```

- [ ] **Step 5: Run all three new tests plus existing find_top_pages tests**

Run: `vendor/bin/phpunit tests/Unit/Repository/PageViewRepositoryTest.php --filter "find_top_pages|count_distinct"`

Expected: all PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Repository/PageViewRepository.php tests/Unit/Repository/PageViewRepositoryTest.php
git commit -m "feat(repo): add offset to findTopPages and countDistinctPages (#3)"
```

---

### Task 2: Controller — read page param, compute pagination metadata

**Files:**
- Modify: `src/Controller/DashboardController.php:62-129`
- Test: `tests/Functional/Controller/DashboardControllerTest.php`

- [ ] **Step 1: Write the failing test for page 2**

Add to `tests/Functional/Controller/DashboardControllerTest.php`:

```php
#[Test]
public function pages_view_page_2_shows_second_page_of_results(): void
{
    $client = static::createClient();
    $em = self::getContainer()->get(EntityManagerInterface::class);

    // Create 25 distinct pages (more than 1 page of 20)
    for ($i = 1; $i <= 25; $i++) {
        $em->persist(PageView::create(
            fingerprint: str_repeat('a', 64),
            pageUrl: sprintf('/page-%03d', $i),
            referrer: null,
            viewedAt: new \DateTimeImmutable('today'),
        ));
    }
    $em->flush();

    $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
    $client->request('GET', '/analytics/pages?from=' . $today . '&to=' . $today . '&page=2');

    self::assertResponseStatusCodeSame(200);
    $content = $client->getResponse()->getContent();
    // Page 2 should have 5 results (25 total, 20 per page)
    self::assertSame(5, substr_count($content, '<tr>') - 1); // minus header row
}
```

- [ ] **Step 2: Write the failing test for out-of-bounds page clamping**

Add to `tests/Functional/Controller/DashboardControllerTest.php`:

```php
#[Test]
public function pages_view_out_of_bounds_page_clamps_to_last(): void
{
    $client = static::createClient();
    $em = self::getContainer()->get(EntityManagerInterface::class);

    $em->persist(PageView::create(
        fingerprint: str_repeat('a', 64),
        pageUrl: '/home',
        referrer: null,
        viewedAt: new \DateTimeImmutable('today'),
    ));
    $em->flush();

    $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
    $client->request('GET', '/analytics/pages?from=' . $today . '&to=' . $today . '&page=999');

    self::assertResponseStatusCodeSame(200);
    $content = $client->getResponse()->getContent();
    self::assertStringContainsString('/home', $content);
}
```

- [ ] **Step 3: Write the failing test for search + pagination**

Add to `tests/Functional/Controller/DashboardControllerTest.php`:

```php
#[Test]
public function pages_view_search_with_pagination(): void
{
    $client = static::createClient();
    $em = self::getContainer()->get(EntityManagerInterface::class);

    // Create 25 blog pages + 5 non-blog pages
    for ($i = 1; $i <= 25; $i++) {
        $em->persist(PageView::create(
            fingerprint: str_repeat('a', 64),
            pageUrl: sprintf('/blog/post-%03d', $i),
            referrer: null,
            viewedAt: new \DateTimeImmutable('today'),
        ));
    }
    for ($i = 1; $i <= 5; $i++) {
        $em->persist(PageView::create(
            fingerprint: str_repeat('a', 64),
            pageUrl: sprintf('/about/team-%03d', $i),
            referrer: null,
            viewedAt: new \DateTimeImmutable('today'),
        ));
    }
    $em->flush();

    $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
    $client->request('GET', '/analytics/pages?from=' . $today . '&to=' . $today . '&search=blog&page=2');

    self::assertResponseStatusCodeSame(200);
    $content = $client->getResponse()->getContent();
    // 25 blog pages, page 2 should have 5
    self::assertSame(5, substr_count($content, '<tr>') - 1);
    // Non-blog pages should not appear
    self::assertStringNotContainsString('/about/team', $content);
}
```

- [ ] **Step 4: Run tests to verify they fail**

Run: `vendor/bin/phpunit tests/Functional/Controller/DashboardControllerTest.php --filter "pages_view_page_2\|pages_view_out_of_bounds\|pages_view_search_with_pagination"`

Expected: FAIL — controller still uses limit 50 with no offset.

- [ ] **Step 5: Implement pagination in the controller**

In `src/Controller/DashboardController.php`, replace the `pagesView` method body (lines 65-129) with:

```php
        $this->denyAccessUnlessGranted();

        $from = $request->query->get('from');
        $to = $request->query->get('to');
        $search = $request->query->get('search');
        $dateRange = $this->dateRangeResolver->resolve(
            is_string($from) ? $from : null,
            is_string($to) ? $to : null,
        );

        if ($redirect = $this->redirectIfDatesNormalized($request, $dateRange)) {
            return $redirect;
        }

        $searchTerm = is_string($search) && $search !== '' ? $search : null;
        $perPage = 20;

        // Count total distinct pages (for pagination)
        $totalDistinct = $this->pageViewRepo->countDistinctPages($dateRange->from, $dateRange->to, $searchTerm);
        $totalPagesCount = max(1, (int) ceil($totalDistinct / $perPage));

        // Read and clamp page number
        $page = max(1, (int) $request->query->get('page', 1));
        $page = min($page, $totalPagesCount);
        $offset = ($page - 1) * $perPage;

        $pages = $this->pageViewRepo->findTopPages($dateRange->from, $dateRange->to, $perPage, $searchTerm, $offset);
        $totalPages = count($pages);

        // Turbo Frame request — return only the list frame
        if ($request->headers->get('Turbo-Frame') === 'ca-pages-list') {
            $html = $this->twig->render('@CookielessAnalytics/dashboard/pages/_pages_list.html.twig', [
                'pages' => $pages,
                'totalPages' => $totalPages,
                'currentPage' => $page,
                'totalPagesCount' => $totalPagesCount,
                'perPage' => $perPage,
                'from' => $dateRange->from->format('Y-m-d'),
                'to' => $dateRange->to->format('Y-m-d'),
                'search' => $searchTerm ?? '',
                'offset' => $offset,
            ]);

            return new Response($html);
        }

        // Pre-select the first page for the detail pane (only when not searching)
        $selectedPage = $searchTerm === null ? ($pages[0]['pageUrl'] ?? null) : null;
        $selectedDetail = null;
        if ($selectedPage !== null) {
            $selectedViews = $this->periodComparer->compare(
                $dateRange,
                fn (\DateTimeImmutable $f, \DateTimeImmutable $t) => $this->pageViewRepo->countByPeriodForPage($selectedPage, $f, $t),
            );
            $selectedVisitors = $this->periodComparer->compare(
                $dateRange,
                fn (\DateTimeImmutable $f, \DateTimeImmutable $t) => $this->pageViewRepo->countUniqueVisitorsByPeriodForPage($selectedPage, $f, $t),
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

        $html = $this->twig->render('@CookielessAnalytics/dashboard/pages/pages.html.twig', [
            'from' => $dateRange->from->format('Y-m-d'),
            'to' => $dateRange->to->format('Y-m-d'),
            'layout' => $this->dashboardLayout ?? '@CookielessAnalytics/dashboard/layout.html.twig',
            'active_nav' => 'pages',
            'pages' => $pages,
            'totalPages' => $totalPages,
            'selectedDetail' => $selectedDetail,
            'search' => $searchTerm ?? '',
            'currentPage' => $page,
            'totalPagesCount' => $totalPagesCount,
            'perPage' => $perPage,
            'offset' => $offset,
        ]);

        return new Response($html);
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `vendor/bin/phpunit tests/Functional/Controller/DashboardControllerTest.php --filter pages_view`

Expected: all `pages_view*` tests PASS.

- [ ] **Step 7: Commit**

```bash
git add src/Controller/DashboardController.php tests/Functional/Controller/DashboardControllerTest.php
git commit -m "feat(controller): add pagination to pagesView (#3)"
```

---

### Task 3: Pagination partial template and CSS

**Files:**
- Create: `templates/dashboard/pages/_pagination.html.twig`
- Modify: `templates/dashboard/dashboard.css`

- [ ] **Step 1: Create the pagination partial template**

Create `templates/dashboard/pages/_pagination.html.twig`:

```twig
{# Pagination controls — included inside the Turbo Frame #}
{% if totalPagesCount > 1 %}
<nav class="pagination">
  {# Previous #}
  {% if currentPage > 1 %}
    <a href="{{ path('cookieless_analytics_dashboard_pages_view', {from: from, to: to, search: search, page: currentPage - 1}) }}" class="pagination-link pagination-prev">&laquo; Prev</a>
  {% else %}
    <span class="pagination-link pagination-prev pagination-disabled">&laquo; Prev</span>
  {% endif %}

  {# Page numbers with window #}
  {% set windowSize = 2 %}
  {% set showFirst = currentPage > windowSize + 1 %}
  {% set showLastPage = currentPage < totalPagesCount - windowSize %}
  {% set rangeStart = max(1, currentPage - windowSize) %}
  {% set rangeEnd = min(totalPagesCount, currentPage + windowSize) %}

  {% if showFirst %}
    <a href="{{ path('cookieless_analytics_dashboard_pages_view', {from: from, to: to, search: search, page: 1}) }}" class="pagination-link">1</a>
    {% if rangeStart > 2 %}
      <span class="pagination-ellipsis">&hellip;</span>
    {% endif %}
  {% endif %}

  {% for p in rangeStart..rangeEnd %}
    {% if p == currentPage %}
      <span class="pagination-link pagination-current">{{ p }}</span>
    {% else %}
      <a href="{{ path('cookieless_analytics_dashboard_pages_view', {from: from, to: to, search: search, page: p}) }}" class="pagination-link">{{ p }}</a>
    {% endif %}
  {% endfor %}

  {% if showLastPage %}
    {% if rangeEnd < totalPagesCount - 1 %}
      <span class="pagination-ellipsis">&hellip;</span>
    {% endif %}
    <a href="{{ path('cookieless_analytics_dashboard_pages_view', {from: from, to: to, search: search, page: totalPagesCount}) }}" class="pagination-link">{{ totalPagesCount }}</a>
  {% endif %}

  {# Next #}
  {% if currentPage < totalPagesCount %}
    <a href="{{ path('cookieless_analytics_dashboard_pages_view', {from: from, to: to, search: search, page: currentPage + 1}) }}" class="pagination-link pagination-next">Next &raquo;</a>
  {% else %}
    <span class="pagination-link pagination-next pagination-disabled">Next &raquo;</span>
  {% endif %}
</nav>
{% endif %}
```

- [ ] **Step 2: Add pagination CSS**

Append to `templates/dashboard/dashboard.css`, before the `/* ─── Events Sub-Page ─── */` comment:

```css
/* ─── Pagination ─── */

.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  margin-top: 16px;
  padding: 8px 0;
}

.pagination-link {
  font-family: var(--data);
  font-size: 12px;
  padding: 4px 10px;
  border: 1px solid var(--rule);
  text-decoration: none;
  color: var(--ink);
  transition: background 0.12s ease;
}

.pagination-link:hover:not(.pagination-disabled):not(.pagination-current) {
  background: var(--paper-dark);
}

.pagination-current {
  background: var(--ink);
  color: var(--paper);
  border-color: var(--ink);
  font-weight: 600;
}

.pagination-disabled {
  color: var(--ink-faint);
  border-color: var(--rule);
  cursor: default;
}

.pagination-ellipsis {
  font-family: var(--data);
  font-size: 12px;
  color: var(--ink-muted);
  padding: 0 4px;
}
```

- [ ] **Step 3: Commit**

```bash
git add templates/dashboard/pages/_pagination.html.twig templates/dashboard/dashboard.css
git commit -m "feat(template): add pagination partial and styles (#3)"
```

---

### Task 4: Wire pagination into list templates

**Files:**
- Modify: `templates/dashboard/pages/pages.html.twig`
- Modify: `templates/dashboard/pages/_pages_list.html.twig`

- [ ] **Step 1: Update the full page template**

In `templates/dashboard/pages/pages.html.twig`, make two changes:

**Change 1:** Fix the rank numbering to account for offset. Replace line 45:

```twig
            <td class="rank-col">{{ '%02d'|format(loop.index) }}</td>
```

with:

```twig
            <td class="rank-col">{{ '%02d'|format(offset + loop.index) }}</td>
```

**Change 2:** Add pagination include after the table, before the closing `</div>` of `.list-pane` (before line 56). Insert after line 55 (`{% endif %}`):

```twig
      {% include '@CookielessAnalytics/dashboard/pages/_pagination.html.twig' %}
```

- [ ] **Step 2: Update the Turbo Frame partial template**

Replace `templates/dashboard/pages/_pages_list.html.twig` entirely:

```twig
<turbo-frame id="ca-pages-list">
<div class="list-pane">
  {% if pages|length > 0 %}
  <table class="pages-table">
    <thead>
      <tr>
        <th></th>
        <th>Page</th>
        <th class="num-head">Views</th>
        <th class="num-head">Visitors</th>
      </tr>
    </thead>
    <tbody>
      {% for page in pages %}
      <tr>
        <td class="rank-col">{{ '%02d'|format(offset + loop.index) }}</td>
        <td class="url-col">{{ page.pageUrl }}</td>
        <td class="num-col">{{ page.views|number_format(0, '.', ',') }}</td>
        <td class="num-col">{{ page.uniqueVisitors|number_format(0, '.', ',') }}</td>
      </tr>
      {% endfor %}
    </tbody>
  </table>
  {% else %}
  <div class="ca-empty">No pages match your search</div>
  {% endif %}
  {% include '@CookielessAnalytics/dashboard/pages/_pagination.html.twig' %}
</div>
</turbo-frame>
```

- [ ] **Step 3: Run existing functional tests to verify nothing breaks**

Run: `vendor/bin/phpunit tests/Functional/Controller/DashboardControllerTest.php --filter pages_view`

Expected: all PASS.

- [ ] **Step 4: Commit**

```bash
git add templates/dashboard/pages/pages.html.twig templates/dashboard/pages/_pages_list.html.twig
git commit -m "feat(template): wire pagination into pages list (#3)"
```

---

### Task 5: Update search controller to reset page on search

**Files:**
- Modify: `templates/dashboard/layout.html.twig` (search Stimulus controller)

- [ ] **Step 1: Update the search controller's `_perform()` method**

In `templates/dashboard/layout.html.twig`, find the search controller's `_perform()` method. The current implementation already builds a URL without a `page` param, which means it naturally resets to page 1. No change needed for the URL building.

However, the hint text should now show the total count across all pages, not just current page row count. Update `_perform()` to not count rows (which only shows the current page), and instead let the server-rendered hint handle it.

Actually, on reflection: the hint is outside the Turbo Frame and is updated client-side after frame load. With pagination, counting `tbody tr` gives the current page's count, not the total. The total is already available in the frame response as the server knows it. The simplest fix: move the results hint **inside** the Turbo Frame so it updates with the frame.

In `templates/dashboard/pages/pages.html.twig`, move the `search-hint` span from outside the frame (line 23) to inside the frame, and update its content:

Replace the search hint line (line 23):
```twig
    <span class="search-hint" data-search-target="hint">{{ totalPages }} results</span>
```

with a non-updating version (remove the target):
```twig
    <span class="search-hint">{{ totalPages }} results</span>
```

Then inside the Turbo Frame, right after the opening `<turbo-frame>` tag (line 30), add:
```twig
    <div class="pagination-info">{{ totalDistinct }} page{{ totalDistinct != 1 ? 's' : '' }} total</div>
```

Wait — this introduces a new variable `totalDistinct` that the controller doesn't pass yet. Let me reconsider.

The simplest approach: keep the hint outside the frame showing the current page count via the Stimulus target, and accept that it shows "5 results" when on page 2 of a 25-result set. This is actually fine for an MVP — the pagination controls already show which page you're on and how many total pages exist. The hint becomes redundant once pagination is visible.

So: **remove the hint update from the Stimulus controller** (the `turbo:frame-load` listener), and let the pagination controls communicate position. The static hint at the top still shows the page count on initial load.

Replace the search controller's `_perform()` method back to the simpler version:

```javascript
        _perform() {
            const term = this.inputTarget.value.trim();
            const params = new URLSearchParams({ from: this.fromValue, to: this.toValue });
            if (term) params.set("search", term);

            const frame = document.getElementById("ca-pages-list");
            if (frame) {
                frame.src = this.urlValue + "?" + params.toString();
            }
        }
```

And remove the `hint` target since we no longer update it:

Change `static targets = ["input", "hint"];` to `static targets = ["input"];`

In `templates/dashboard/pages/pages.html.twig`, remove `data-search-target="hint"` from the search hint span:

```twig
    <span class="search-hint">{{ totalPages }} results</span>
```

- [ ] **Step 2: Run tests**

Run: `vendor/bin/phpunit tests/Functional/Controller/DashboardControllerTest.php --filter pages_view`

Expected: all PASS.

- [ ] **Step 3: Commit**

```bash
git add templates/dashboard/layout.html.twig templates/dashboard/pages/pages.html.twig
git commit -m "refactor: simplify search hint, remove client-side hint update (#3)"
```

---

### Task 6: Final integration test and cleanup

**Files:**
- Test: `tests/Functional/Controller/DashboardControllerTest.php`

- [ ] **Step 1: Run the full test suite**

Run: `vendor/bin/phpunit`

Expected: all tests PASS.

- [ ] **Step 2: Manual browser test**

Full scenario:
1. Load Pages sub-page with enough data (use seed.php) — pagination controls visible
2. Click page 2 — list updates inside the frame, rank numbers continue from 21
3. Click page 1 — back to first page
4. Search for a term — resets to page 1, pagination shows filtered count
5. Paginate within search results — page 2 of search works
6. Change date range — if new range has fewer pages, page clamps to last valid
7. Prev disabled on page 1, Next disabled on last page
8. Out-of-bounds URL `?page=999` — shows last valid page

- [ ] **Step 3: Create GitHub issue for configurable page size**

```bash
gh issue create --repo jackfumanchu/cookieless-analytics-bundle \
  --title "Configurable page size for Pages list" \
  --label "enhancement" \
  --body "Allow users to choose how many results per page (e.g., 20/50/100 dropdown). Currently hardcoded at 20. Depends on pagination (#3)."
```

- [ ] **Step 4: Final commit if any fixes needed**

Only create this commit if fixes were needed during manual testing. Skip if everything passed.
