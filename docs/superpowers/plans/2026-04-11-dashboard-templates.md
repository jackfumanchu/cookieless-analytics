# Dashboard Templates (v3 Editorial) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restyle the dashboard overview page from the generic SaaS look to the v3 newspaper/editorial design, add a referrers section, and keep Turbo Frame lazy-loading working.

**Architecture:** Replace the existing CSS and templates with the v3 editorial style (Libre Franklin headings, Source Serif 4 body, IBM Plex Mono data). The layout template provides the masthead, section nav, and folio. The index page arranges turbo frames in the v3 three-column layout. Each partial (_overview, _trends, _top_pages, _events) is restyled. A new _referrers partial and controller action are added. Existing functional tests are updated to match the new DOM structure.

**Tech Stack:** Twig, CSS (no preprocessor), Stimulus JS, uPlot, Turbo Frames

---

## File Structure

| File | Action | Responsibility |
|------|--------|----------------|
| `templates/dashboard/dashboard.css` | Rewrite | All editorial CSS — variables, typography, layout, components, responsive |
| `templates/dashboard/layout.html.twig` | Rewrite | HTML shell: fonts, masthead, section nav, folio, JS (Stimulus + uPlot) |
| `templates/dashboard/index.html.twig` | Rewrite | Overview page: controls bar, turbo frame layout (KPIs + chart + three-column) |
| `templates/dashboard/_overview.html.twig` | Rewrite | Headline numbers (4 KPIs with sparklines) |
| `templates/dashboard/_trends.html.twig` | Rewrite | Editorial chart area with legend |
| `templates/dashboard/_top_pages.html.twig` | Rewrite | Editorial table with rank numbers |
| `templates/dashboard/_events.html.twig` | Rewrite | Event dispatch list with inline bars |
| `templates/dashboard/_referrers.html.twig` | Create | Referrer sources list with counts and percentages |
| `src/Controller/DashboardController.php` | Modify | Add `referrers()` action |
| `tests/Functional/Controller/DashboardControllerTest.php` | Modify | Update selectors for new DOM structure, add referrers test |

---

### Task 1: Replace dashboard.css with v3 editorial styles

**Files:**
- Rewrite: `templates/dashboard/dashboard.css`

The CSS is extracted from `docs/mockup/v3/dashboard_v3.html` with these adaptations:
- Remove the `body` and `*` reset rules (the layout template handles the body)
- Keep CSS variables, all component styles, and responsive breakpoints
- Remove the paper texture `body::before` (move to layout template)

- [ ] **Step 1: Rewrite dashboard.css**

Extract all CSS from `docs/mockup/v3/dashboard_v3.html` (lines 9–665 of the updated file) into `templates/dashboard/dashboard.css`. Include:

1. `:root` variables (paper, ink, rule, fonts)
2. `.broadsheet` container
3. `.masthead` and sub-elements
4. `.section-nav`
5. `.controls-bar`, `.period-btn`, `.date-field`
6. `.headline-numbers`, `.headline-num`, `.hn-*`
7. `.columns`, `.col-divider`, `.column`
8. `.section-head`, `.section-deck`
9. `.chart-area`, `.chart-header`, `.chart-headline`, `.chart-deck`, `.chart-legend-ed`, `.chart-box`
10. SVG chart classes: `.ed-grid`, `.ed-grid-heavy`, `.ed-label`, `.ed-line-views`, `.ed-area-views`, `.ed-line-visitors`, `.ed-dot`
11. `.two-col`
12. `.ed-table` and children
13. `.event-list`, `.event-item`, `.event-name`, `.event-count`, `.event-bar-*`
14. `.ref-item`, `.ref-source`, `.ref-num`, `.ref-pct`
15. `.folio`, `.folio .shield`
16. Responsive: `@media (max-width: 1000px)` and `@media (max-width: 600px)` blocks

**Do NOT include**: `body`, `*`, `body::before` (paper texture) — those go in layout.

- [ ] **Step 2: Verify CSS loads by running functional test**

Run: `php vendor/bin/phpunit tests/Functional/Controller/DashboardControllerTest.php --filter=index_returns_200`
Expected: PASS (the test only checks HTTP 200 and frame existence, CSS content doesn't affect it)

- [ ] **Step 3: Commit**

```bash
git add templates/dashboard/dashboard.css
git commit -m "style: replace dashboard CSS with v3 editorial styles"
```

---

### Task 2: Rewrite layout.html.twig

**Files:**
- Rewrite: `templates/dashboard/layout.html.twig`

The layout provides the HTML shell. Key changes from the current version:
- Google Fonts link (Libre Franklin, Source Serif 4, IBM Plex Mono)
- Paper texture via `body::before` (inline in `<style>`)
- uPlot CSS stays
- Import map for Turbo, Stimulus, uPlot stays
- Stimulus controllers: `date-range` (updated for new DOM) and `chart` (updated for editorial SVG)

- [ ] **Step 1: Rewrite layout.html.twig**

```twig
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{% block title %}The Analytics Record{% endblock %}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@300;400;500;600;700;800;900&family=IBM+Plex+Mono:wght@300;400;500&family=Source+Serif+4:ital,opsz,wght@0,8..60,300;0,8..60,400;0,8..60,500;0,8..60,600;1,8..60,400&display=swap" rel="stylesheet">
    {% block stylesheets %}
    <style>{{ source('@CookielessAnalytics/dashboard/dashboard.css', ignore_missing=true) }}</style>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: var(--paper);
            color: var(--ink);
            font-family: var(--body);
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            pointer-events: none;
            opacity: 0.4;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.8' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.35'/%3E%3C/svg%3E");
            background-size: 200px;
            z-index: 999;
        }
    </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/uplot@1/dist/uPlot.min.css">
    {% endblock %}
</head>
<body>
    {% block body %}{% endblock %}
    {% block javascripts %}
    <script type="importmap">
    {
        "imports": {
            "@hotwired/turbo": "https://cdn.jsdelivr.net/npm/@hotwired/turbo@8/dist/turbo.es2017-esm.min.js",
            "@hotwired/stimulus": "https://cdn.jsdelivr.net/npm/@hotwired/stimulus@3/dist/stimulus.js",
            "uplot": "https://cdn.jsdelivr.net/npm/uplot@1/dist/uPlot.esm.js"
        }
    }
    </script>
    <script type="module">
    import "@hotwired/turbo";
    import { Application, Controller } from "@hotwired/stimulus";

    const app = Application.start();

    // Date Range Controller
    app.register("date-range", class extends Controller {
        static targets = ["shortcut", "fromInput", "toInput"];
        static values = { from: String, to: String };

        connect() { this.highlightActiveShortcut(); }

        apply() {
            const from = this.fromInputTarget.value;
            const to = this.toInputTarget.value;
            if (!from || !to || from > to) return;
            this.updateFrames(from, to);
        }

        shortcutTargetConnected(element) {
            element.addEventListener("click", () => {
                const { from, to } = this.computePeriod(element.dataset.period);
                this.fromInputTarget.value = from;
                this.toInputTarget.value = to;
                this.updateFrames(from, to);
            });
        }

        computePeriod(period) {
            const today = new Date();
            const to = this.formatDate(today);
            let from;
            switch (period) {
                case "today": from = to; break;
                case "7days": from = this.formatDate(new Date(today.getTime() - 6 * 86400000)); break;
                case "30days": from = this.formatDate(new Date(today.getTime() - 29 * 86400000)); break;
                case "month": from = this.formatDate(new Date(today.getFullYear(), today.getMonth(), 1)); break;
                default: from = this.formatDate(new Date(today.getTime() - 29 * 86400000));
            }
            return { from, to };
        }

        updateFrames(from, to) {
            const url = new URL(window.location);
            url.searchParams.set("from", from);
            url.searchParams.set("to", to);
            window.history.replaceState({}, "", url);
            document.querySelectorAll('turbo-frame[id^="ca-"]').forEach(frame => {
                const src = new URL(frame.src || frame.getAttribute("src"), window.location.origin);
                src.searchParams.set("from", from);
                src.searchParams.set("to", to);
                frame.src = src.toString();
            });
            this.fromValue = from;
            this.toValue = to;
            this.highlightActiveShortcut();
        }

        highlightActiveShortcut() {
            const from = this.fromInputTarget.value;
            const to = this.toInputTarget.value;
            this.shortcutTargets.forEach(btn => {
                const { from: pFrom, to: pTo } = this.computePeriod(btn.dataset.period);
                btn.classList.toggle("active", pFrom === from && pTo === to);
            });
        }

        formatDate(date) { return date.toISOString().slice(0, 10); }
    });

    // Chart Controller (uPlot)
    import uPlot from "uplot";
    app.register("chart", class extends Controller {
        static values = { dates: Array, views: Array, visitors: Array };

        connect() { this.renderChart(); }
        disconnect() { if (this.chart) this.chart.destroy(); }

        renderChart() {
            const timestamps = this.datesValue.map(d => new Date(d + "T00:00:00").getTime() / 1000);
            const opts = {
                width: this.element.clientWidth,
                height: 200,
                series: [
                    { label: "Date" },
                    {
                        label: "Page views",
                        stroke: getComputedStyle(document.documentElement).getPropertyValue('--ink').trim(),
                        width: 2,
                        fill: "rgba(26,23,18,0.06)",
                        points: { show: true, size: 7, stroke: getComputedStyle(document.documentElement).getPropertyValue('--ink').trim(), fill: getComputedStyle(document.documentElement).getPropertyValue('--paper').trim() }
                    },
                    {
                        label: "Visitors",
                        stroke: getComputedStyle(document.documentElement).getPropertyValue('--ink-muted').trim(),
                        width: 1.5,
                        dash: [5, 4],
                        points: { show: false }
                    },
                ],
                axes: [
                    {
                        values: (u, vals) => vals.map(v => {
                            const d = new Date(v * 1000);
                            const months = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];
                            return months[d.getMonth()] + " " + d.getDate();
                        }),
                        font: "9px 'IBM Plex Mono', monospace",
                        stroke: getComputedStyle(document.documentElement).getPropertyValue('--ink-muted').trim(),
                    },
                    {
                        font: "9px 'IBM Plex Mono', monospace",
                        stroke: getComputedStyle(document.documentElement).getPropertyValue('--ink-muted').trim(),
                        grid: { stroke: getComputedStyle(document.documentElement).getPropertyValue('--rule').trim(), width: 0.5 },
                    },
                ],
            };
            this.chart = new uPlot(opts, [timestamps, this.viewsValue, this.visitorsValue], this.element);
        }
    });
    </script>
    {% endblock %}
</body>
</html>
```

- [ ] **Step 2: Verify it renders**

Run: `php vendor/bin/phpunit tests/Functional/Controller/DashboardControllerTest.php --filter=index_returns_200`
Expected: PASS

- [ ] **Step 3: Commit**

```bash
git add templates/dashboard/layout.html.twig
git commit -m "style: rewrite layout template with v3 editorial masthead and fonts"
```

---

### Task 3: Rewrite index.html.twig (overview page structure)

**Files:**
- Rewrite: `templates/dashboard/index.html.twig`

The index page renders the full overview. It contains:
- Masthead (static, from layout via body block)
- Section nav (Overview active, links to future pages)
- Controls bar (date range, period buttons)
- Turbo frames arranged: KPIs → chart → three-column (pages + events + referrers)

- [ ] **Step 1: Rewrite index.html.twig**

```twig
{% extends layout %}

{% block body %}
<div class="broadsheet">

  {# ─── Masthead ─── #}
  <header class="masthead">
    <div class="masthead-above">
      <span>Cookieless Analytics Bundle</span>
      <span>{{ "now"|date("l, F j, Y") }}</span>
      <span>Cookie-Free Since 2024</span>
    </div>
    <h1 class="masthead-title">The Analytics Record</h1>
    <p class="masthead-subtitle">Cookieless &middot; Privacy-First &middot; Honest Data</p>
    <div class="masthead-rule">{{ from }} &mdash; {{ to }}</div>
  </header>

  {# ─── Section Nav ─── #}
  <nav class="section-nav">
    <a href="{{ path('cookieless_analytics_dashboard', {from: from, to: to}) }}" class="active">Overview</a>
    <a href="{{ path('cookieless_analytics_dashboard', {from: from, to: to}) }}">Pages</a>
    <a href="{{ path('cookieless_analytics_dashboard', {from: from, to: to}) }}">Events</a>
    <a href="{{ path('cookieless_analytics_dashboard', {from: from, to: to}) }}">Trends</a>
  </nav>

  {# ─── Date Controls ─── #}
  <div class="controls-bar" data-controller="date-range" data-date-range-from-value="{{ from }}" data-date-range-to-value="{{ to }}">
    <div class="edition-label">Edition <span>&mdash; {{ from }} to {{ to }}</span></div>
    <div class="controls-right">
      <button class="period-btn" data-date-range-target="shortcut" data-period="today">1D</button>
      <button class="period-btn" data-date-range-target="shortcut" data-period="7days">7D</button>
      <button class="period-btn" data-date-range-target="shortcut" data-period="30days">30D</button>
      <button class="period-btn" data-date-range-target="shortcut" data-period="month">MTD</button>
      <span style="width:8px;"></span>
      <input class="date-field" type="text" value="{{ from }}" data-date-range-target="fromInput" name="from">
      <span class="date-dash">&mdash;</span>
      <input class="date-field" type="text" value="{{ to }}" data-date-range-target="toInput" name="to">
      <button class="period-btn active" data-action="date-range#apply" style="background:var(--ink);color:var(--paper);border-color:var(--ink);">Apply</button>
    </div>
  </div>

  {# ─── Headline Numbers (KPIs) ─── #}
  <turbo-frame id="ca-overview" src="{{ path('cookieless_analytics_dashboard_overview', {from: from, to: to}) }}" loading="lazy">
    <div class="headline-numbers">
      <div class="headline-num" style="opacity:0.3;"><div class="hn-label">Loading...</div></div>
      <div class="headline-num" style="opacity:0.3;"><div class="hn-label">Loading...</div></div>
      <div class="headline-num" style="opacity:0.3;"><div class="hn-label">Loading...</div></div>
      <div class="headline-num" style="opacity:0.3;"><div class="hn-label">Loading...</div></div>
    </div>
  </turbo-frame>

  {# ─── Chart ─── #}
  <turbo-frame id="ca-trends" src="{{ path('cookieless_analytics_dashboard_trends', {from: from, to: to}) }}" loading="lazy">
    <div class="chart-area"><div class="chart-box" style="height:200px;opacity:0.3;"></div></div>
  </turbo-frame>

  {# ─── Three-Column Section ─── #}
  <div class="columns">
    <div class="column">
      <turbo-frame id="ca-top-pages" src="{{ path('cookieless_analytics_dashboard_top_pages', {from: from, to: to}) }}" loading="lazy">
        <h2 class="section-head">Top Pages</h2>
        <p class="section-deck">Loading...</p>
      </turbo-frame>
    </div>

    <div class="col-divider"></div>

    <div class="column">
      <turbo-frame id="ca-events" src="{{ path('cookieless_analytics_dashboard_events', {from: from, to: to}) }}" loading="lazy">
        <h2 class="section-head">Events Dispatch</h2>
        <p class="section-deck">Loading...</p>
      </turbo-frame>
    </div>

    <div class="col-divider"></div>

    <div class="column">
      <turbo-frame id="ca-referrers" src="{{ path('cookieless_analytics_dashboard_referrers', {from: from, to: to}) }}" loading="lazy">
        <h2 class="section-head">Sources</h2>
        <p class="section-deck">Loading...</p>
      </turbo-frame>
    </div>
  </div>

  {# ─── Folio ─── #}
  <footer class="folio">
    The Analytics Record &middot; Published continuously since 2024
    <div class="shield">
      <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M8 1.5l5.5 2v4c0 3.5-2.5 6-5.5 7.5-3-1.5-5.5-4-5.5-7.5v-4z"/><polyline points="5.5 8 7 9.5 10.5 6"/></svg>
      No cookies. No tracking scripts. Just honest numbers.
    </div>
  </footer>

</div>
{% endblock %}
```

**Note:** This references a route `cookieless_analytics_dashboard_referrers` that doesn't exist yet — Task 7 adds it. The index template can be committed now; the turbo frame will 404 until Task 7 is done, which won't break the page (Turbo handles frame load failures gracefully).

- [ ] **Step 2: Commit**

```bash
git add templates/dashboard/index.html.twig
git commit -m "feat: rewrite index template with v3 editorial layout and three-column structure"
```

---

### Task 4: Rewrite _overview.html.twig (headline numbers)

**Files:**
- Rewrite: `templates/dashboard/_overview.html.twig`

Renders the 4 KPI cards as newspaper "headline numbers". Each card shows: label, value, change percentage with arrow, and an inline sparkline SVG (placeholder — real sparklines require daily data which would need an extra query; for now we show the change only).

- [ ] **Step 1: Rewrite _overview.html.twig**

```twig
<turbo-frame id="ca-overview">
  <div class="headline-numbers">
    {% for card in [
      {label: 'Page Views', comparison: pageViews},
      {label: 'Unique Visitors', comparison: uniqueVisitors},
      {label: 'Events Tracked', comparison: events},
      {label: 'Pages / Visitor', comparison: pagesPerVisitor},
    ] %}
    <div class="headline-num">
      <div class="hn-label">{{ card.label }}</div>
      <div class="hn-value">
        {%- if card.comparison.currentFloat != card.comparison.current -%}
          {{ card.comparison.currentFloat }}
        {%- else -%}
          {{ card.comparison.current|number_format(0, '.', ',') }}
        {%- endif -%}
      </div>
      {% if card.comparison.previous > 0 %}
        <span class="hn-change {{ card.comparison.changePercent >= 0 ? 'up' : 'down' }}">
          {{ card.comparison.changePercent >= 0 ? '&#9650;' : '&#9660;' }} {{ card.comparison.changePercent|abs }}% vs prev. period
        </span>
      {% endif %}
    </div>
    {% endfor %}
  </div>
</turbo-frame>
```

- [ ] **Step 2: Run functional test**

Run: `php vendor/bin/phpunit tests/Functional/Controller/DashboardControllerTest.php --filter=overview_returns_kpi`
Expected: PASS (test checks for `ca-overview` frame and content)

- [ ] **Step 3: Commit**

```bash
git add templates/dashboard/_overview.html.twig
git commit -m "feat: rewrite overview template with v3 headline numbers"
```

---

### Task 5: Rewrite _trends.html.twig (editorial chart)

**Files:**
- Rewrite: `templates/dashboard/_trends.html.twig`

The chart uses uPlot (already in the import map). The controller passes JSON arrays for dates, views, visitors. The template wraps it in the editorial chart area with a headline and legend.

- [ ] **Step 1: Rewrite _trends.html.twig**

```twig
<turbo-frame id="ca-trends">
  <div class="chart-area">
    <div class="chart-header">
      <div>
        <div class="chart-headline">Traffic Report</div>
        <div class="chart-deck">Daily page views and unique visitors</div>
      </div>
      <div class="chart-legend-ed">
        <span><span class="led-line" style="background:var(--ink);"></span> Page views</span>
        <span><span class="led-line dashed"></span> Visitors</span>
      </div>
    </div>
    <div class="chart-box"
         data-controller="chart"
         data-chart-dates-value="{{ dates }}"
         data-chart-views-value="{{ views }}"
         data-chart-visitors-value="{{ visitors }}">
    </div>
  </div>
</turbo-frame>
```

- [ ] **Step 2: Run functional test**

Run: `php vendor/bin/phpunit tests/Functional/Controller/DashboardControllerTest.php --filter=trends_returns_chart`
Expected: PASS (test checks for `ca-trends` frame and data attributes)

- [ ] **Step 3: Commit**

```bash
git add templates/dashboard/_trends.html.twig
git commit -m "feat: rewrite trends template with v3 editorial chart area"
```

---

### Task 6: Rewrite _top_pages.html.twig (editorial table)

**Files:**
- Rewrite: `templates/dashboard/_top_pages.html.twig`

Newspaper-style ranked table with rank numbers, page URL, views, and unique visitors.

- [ ] **Step 1: Rewrite _top_pages.html.twig**

```twig
<turbo-frame id="ca-top-pages">
  <h2 class="section-head">Top Pages</h2>
  <p class="section-deck">Most visited pages this period, ranked by total views.</p>
  <table class="ed-table">
    <thead>
      <tr>
        <th></th>
        <th style="text-align:left;">Page</th>
        <th>Views</th>
        <th>Uniq.</th>
      </tr>
    </thead>
    <tbody>
      {% for page in pages %}
      <tr>
        <td class="rank-col">{{ '%02d'|format(loop.index) }}</td>
        <td class="page-col" title="{{ page.pageUrl }}">{{ page.pageUrl }}</td>
        <td class="num-col">{{ page.views|number_format(0, '.', ',') }}</td>
        <td class="num-col">{{ page.uniqueVisitors|number_format(0, '.', ',') }}</td>
      </tr>
      {% else %}
      <tr>
        <td colspan="4" style="font-family:var(--editorial);color:var(--ink-muted);padding:16px 0;">No data for this period</td>
      </tr>
      {% endfor %}
    </tbody>
  </table>
</turbo-frame>
```

- [ ] **Step 2: Run functional test**

Run: `php vendor/bin/phpunit tests/Functional/Controller/DashboardControllerTest.php --filter=top_pages_returns`
Expected: PASS (test checks for `ca-top-pages` and content strings like `/home`)

- [ ] **Step 3: Commit**

```bash
git add templates/dashboard/_top_pages.html.twig
git commit -m "feat: rewrite top pages template with v3 editorial ranked table"
```

---

### Task 7: Add referrers controller action and template

**Files:**
- Modify: `src/Controller/DashboardController.php`
- Create: `templates/dashboard/_referrers.html.twig`

This is a new endpoint that the index page's `ca-referrers` turbo frame loads.

- [ ] **Step 1: Write the failing functional test**

Add to `tests/Functional/Controller/DashboardControllerTest.php`:

```php
#[Test]
public function referrers_returns_source_list(): void
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
        pageUrl: '/about',
        referrer: 'https://google.com/search',
        viewedAt: new \DateTimeImmutable('today'),
    ));
    $em->persist(PageView::create(
        fingerprint: str_repeat('c', 64),
        pageUrl: '/home',
        referrer: null,
        viewedAt: new \DateTimeImmutable('today'),
    ));
    $em->flush();

    $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
    $client->request('GET', '/analytics/referrers?from=' . $today . '&to=' . $today);

    self::assertResponseStatusCodeSame(200);
    $content = $client->getResponse()->getContent();
    self::assertStringContainsString('google.com', $content);
    self::assertStringContainsString('Direct', $content);
    self::assertStringContainsString('ca-referrers', $content);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit tests/Functional/Controller/DashboardControllerTest.php --filter=referrers_returns`
Expected: FAIL — route not found (404)

- [ ] **Step 3: Add the controller action**

Add to `DashboardController.php`, after the `events()` method:

```php
#[Route(path: '/referrers', name: 'cookieless_analytics_dashboard_referrers', methods: ['GET'])]
public function referrers(Request $request): Response
{
    $this->denyAccessUnlessGranted();

    $dateRange = $this->dateRangeResolver->resolve(
        $request->query->getString('from') ?: null,
        $request->query->getString('to') ?: null,
    );

    $referrers = $this->pageViewRepo->findTopReferrers($dateRange->from, $dateRange->to, 10);
    $totalVisits = array_sum(array_column($referrers, 'visits'));

    $html = $this->twig->render('@CookielessAnalytics/dashboard/_referrers.html.twig', [
        'referrers' => $referrers,
        'totalVisits' => $totalVisits,
    ]);

    return new Response($html);
}
```

- [ ] **Step 4: Create _referrers.html.twig**

```twig
<turbo-frame id="ca-referrers">
  <h2 class="section-head">Sources</h2>
  <p class="section-deck">Where your readers are arriving from this period.</p>
  <div>
    {% for ref in referrers %}
    <div class="ref-item">
      <span class="ref-source">{{ ref.source }}</span>
      <span>
        <span class="ref-num">{{ ref.visits|number_format(0, '.', ',') }}</span>
        {% if totalVisits > 0 %}
          <span class="ref-pct">{{ ((ref.visits / totalVisits) * 100)|round(1) }}%</span>
        {% endif %}
      </span>
    </div>
    {% else %}
    <div class="ref-item">
      <span class="ref-source" style="color:var(--ink-muted);">No data for this period</span>
    </div>
    {% endfor %}
  </div>
</turbo-frame>
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php vendor/bin/phpunit tests/Functional/Controller/DashboardControllerTest.php --filter=referrers_returns`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add src/Controller/DashboardController.php templates/dashboard/_referrers.html.twig tests/Functional/Controller/DashboardControllerTest.php
git commit -m "feat: add referrers endpoint and v3 editorial sources template"
```

---

### Task 8: Rewrite _events.html.twig (event dispatch list)

**Files:**
- Rewrite: `templates/dashboard/_events.html.twig`

Editorial-style event list with inline bars showing relative frequency.

- [ ] **Step 1: Rewrite _events.html.twig**

```twig
<turbo-frame id="ca-events">
  <h2 class="section-head">Events Dispatch</h2>
  <p class="section-deck">Custom events tracked across the site, ordered by frequency.</p>
  <ul class="event-list">
    {% set maxOccurrences = events|first.occurrences|default(1) %}
    {% for event in events %}
    <li class="event-item">
      <span class="event-name"><span class="bullet"></span> {{ event.name }}</span>
      <span class="event-bar-track"><span class="event-bar-fill" style="width:{{ (event.occurrences / maxOccurrences * 100)|round }}%;"></span></span>
      <span class="event-count">{{ event.occurrences|number_format(0, '.', ',') }}</span>
    </li>
    {% else %}
    <li class="event-item">
      <span class="event-name" style="color:var(--ink-muted);">No data for this period</span>
    </li>
    {% endfor %}
  </ul>
</turbo-frame>
```

- [ ] **Step 2: Run functional test**

Run: `php vendor/bin/phpunit tests/Functional/Controller/DashboardControllerTest.php --filter=events_returns`
Expected: PASS (test checks for `ca-events` and `click-cta`)

- [ ] **Step 3: Commit**

```bash
git add templates/dashboard/_events.html.twig
git commit -m "feat: rewrite events template with v3 editorial dispatch list"
```

---

### Task 9: Update functional tests for new DOM structure

**Files:**
- Modify: `tests/Functional/Controller/DashboardControllerTest.php`

The existing tests check for selectors like `turbo-frame#ca-overview`, `[data-controller="date-range"]`, `input[name="from"]` — most of these still work with the new template. We need to:
1. Add the `ca-referrers` frame check to `index_returns_200`
2. Update the `trends_returns_chart` test to check for new wrapper elements

- [ ] **Step 1: Update index test to check for referrers frame**

In `index_returns_200_with_dashboard_content`, add:

```php
self::assertSelectorExists('turbo-frame#ca-referrers');
```

- [ ] **Step 2: Update index test to check for editorial structure**

In `index_returns_200_with_dashboard_content`, add:

```php
self::assertSelectorExists('.masthead');
self::assertSelectorExists('.section-nav');
self::assertSelectorExists('.columns');
```

- [ ] **Step 3: Run all functional tests**

Run: `php vendor/bin/phpunit tests/Functional/Controller/DashboardControllerTest.php`
Expected: All tests PASS

- [ ] **Step 4: Run full test suite**

Run: `php vendor/bin/phpunit`
Expected: All tests PASS

- [ ] **Step 5: Commit**

```bash
git add tests/Functional/Controller/DashboardControllerTest.php
git commit -m "test: update dashboard functional tests for v3 editorial structure"
```

---

### Task 10: Final verification

- [ ] **Step 1: Run full test suite**

Run: `php vendor/bin/phpunit`
Expected: All tests pass — no regressions

- [ ] **Step 2: Visual check**

Open `http://localhost/analytics/` in a browser (or whatever the configured dashboard URL is) and verify:
- Masthead with "The Analytics Record" title renders
- Section nav shows Overview/Pages/Events/Trends
- Date controls with period buttons work
- KPI headline numbers load via Turbo Frame
- Chart renders with uPlot in editorial style
- Three-column section shows Top Pages, Events Dispatch, Sources
- Folio footer renders at bottom
- Responsive: at ~800px wide, Sources drops to a horizontal rail below the two columns
- Responsive: at ~600px, everything stacks single-column

- [ ] **Step 3: Commit any remaining fixes**
