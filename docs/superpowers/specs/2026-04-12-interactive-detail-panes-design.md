# Interactive Detail Panes (Pages) — Design Spec

**Issue:** #1 — Interactive detail panes for Pages and Events sub-pages
**Date:** 2026-04-12
**Scope:** Pages sub-page only. Events will follow as a separate issue.

## Summary

Clicking a row in the Pages list updates the detail pane dynamically without a full page reload. The clicked row is highlighted instantly (client-side), and the detail pane fetches via Turbo Frame.

## Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Detail pane update | Turbo Frame on detail pane | Consistent with existing patterns (search, pagination); no list re-render |
| Row highlight | Client-side class toggle | Instant feedback, no round-trip needed |
| New route vs header detection | Turbo-Frame header detection | Follows existing `ca-pages-list` pattern; keeps URLs clean |
| Scope | Pages only | Events follows as separate work |
| Detail on pagination change | Leave stale detail visible | Data is still valid; row just scrolled away |

## Controller layer

In `DashboardController::pagesView()`, add a second Turbo Frame check (after the existing `ca-pages-list` check):

When `Turbo-Frame: ca-page-detail` is present and `?selected=<url>` is provided:
1. Read `selected` from query string
2. Call existing repository methods to build detail data:
   - `countByPeriodForPage` (with period comparison)
   - `countUniqueVisitorsByPeriodForPage` (with period comparison)
   - `countByDayForPage`
   - `findTopReferrersForPage`
3. Render `_page_detail.html.twig` partial
4. Return early

No new repository methods needed — all detail queries already exist.

## Stimulus controller

New `row-select` controller registered inline in `layout.html.twig`:

- Attached to the `.pages-table` table element
- Targets: none needed
- Values: `url` (String — base URL for the detail frame), `from` (String), `to` (String)
- Action: `click->row-select#select` on each `<tr>` in tbody

On click:
1. Read the page URL from `data-row-select-url-param` on the clicked row (passed via Stimulus action params)
2. Remove `.selected` from all `<tbody tr>` in the table
3. Add `.selected` to the clicked row
4. Build URL: `?selected=<url>&from=...&to=...`
5. Set `src` on `document.getElementById("ca-page-detail")` — Turbo fetches and replaces the frame

## Template changes

### `pages.html.twig`

- The `.pages-table` gets `data-controller="row-select"` with `url`, `from`, `to` values
- Each `<tr>` in tbody gets `data-action="click->row-select#select"` and `data-row-select-url-param="{{ page.pageUrl }}"`
- Remove the server-side `class="selected"` conditional on `<tr>` (Stimulus handles highlight now)
- Wrap the detail pane in `<turbo-frame id="ca-page-detail">`

### New: `_page_detail.html.twig`

Extract the detail pane content (KPIs, chart, referrers) into a standalone partial wrapped in `<turbo-frame id="ca-page-detail">`. Used for:
- Turbo Frame responses (row click)
- Included in the full page template for initial render

### `_pages_list.html.twig` (Turbo Frame partial for search/pagination)

- Each `<tr>` also gets the row-select action and URL param (same as full template)
- No detail pane in this partial (it's a separate frame)

## Initial page load behavior

- First page is still pre-selected server-side (existing behavior)
- The detail pane renders inside `<turbo-frame id="ca-page-detail">` with the first page's data
- The first row gets `class="selected"` server-side on initial load (Stimulus takes over on click)

## Interaction with search and pagination

- **Search fires:** List frame updates, detail pane is untouched (stays showing last selected, or empty if searching from fresh load). The row highlight disappears since list content changed.
- **Pagination fires:** Same as search — list updates, detail stays.
- **Row click after search/pagination:** Detail pane updates for the clicked row.
- **No change** to search or pagination logic.

## Testing

- Functional test: `?selected=/home` with `Turbo-Frame: ca-page-detail` header returns only the detail partial
- Functional test: detail partial contains KPIs, chart data, referrers for the selected page
- Functional test: `?selected=/nonexistent` returns empty state
- Functional test: full page load still pre-selects first page
- Manual: browser test for click → highlight + detail update, search interaction, pagination interaction
