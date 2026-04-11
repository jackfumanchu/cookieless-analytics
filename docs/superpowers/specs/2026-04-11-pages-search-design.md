# Pages Search — Design Spec

**Issue:** #2 — Add backend search/filtering for Pages sub-page
**Date:** 2026-04-11

## Summary

Add live search to the Pages sub-page. The search bar UI already exists but has no backend wiring. Typing in the input filters the pages list via debounced Turbo Frame updates, matching anywhere in the URL.

## Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Filtering approach | Stimulus + Turbo Frame | Consistent with existing date-range pattern; searches full dataset, not just loaded rows |
| Match type | `LIKE %term%` (anywhere in URL) | User preference; most flexible for path matching |
| Detail pane on search | Clear (empty state) | Avoids hammering detail queries on every keystroke |
| Query objects / Domain layer | Not introduced | YAGNI — read-only dashboard with 3-4 scalar params per query; revisit if param count grows |

## Repository layer

`findTopPages()` gains an optional `?string $search = null` parameter:

```php
public function findTopPages(
    \DateTimeImmutable $from,
    \DateTimeImmutable $to,
    int $limit = 10,
    ?string $search = null,
): array
```

When `$search` is provided:
- Add `->andWhere('p.pageUrl LIKE :search')->setParameter('search', '%' . $search . '%')`
- The total count method gets the same filter so the "N results" hint remains accurate

No new repository methods — just an optional parameter on the existing one.

## Controller layer

`DashboardController::pagesView()` changes:

- Read `$search = $request->query->get('search')` from the query string
- Pass `$search` to `findTopPages()`
- When `$search` is non-empty, skip the detail pane data (no pre-selected page)
- Pass `search` to the template for the input's `value` attribute

## Stimulus controller

New `search` controller (`assets/controllers/search_controller.js`):

- Attached to the search input
- On `input` event, debounced ~300ms
- Reads current `from`/`to` values and the search term
- Updates the `src` attribute of the `ca-pages-list` Turbo Frame with `?search=<term>&from=...&to=...`
- Turbo fetches the frame content — only the list re-renders

## Template changes

`templates/dashboard/pages/pages.html.twig`:

- Wrap the pages list (table + results hint) in `<turbo-frame id="ca-pages-list">`
- Search input gets `data-controller="search"` and `data-action="input->search#filter"`
- Search input gets `value="{{ search }}"` to preserve the term across frame reloads
- Detail pane shows empty state ("Select a page to view details") when no page is selected
- Results hint (`{{ totalPages }} results`) lives inside the frame so it updates with filtered count

## What stays unchanged

- Date range handling — `search` is additive alongside `from`/`to` query params
- Detail pane rendering logic — just skipped when search is active
- No new routes — same `pagesView` action handles filtered and unfiltered requests
- No new repository methods

## Testing

- Unit test: `findTopPages()` with search parameter filters results correctly
- Unit test: `findTopPages()` without search returns all results (existing behavior preserved)
- Controller test: `?search=blog` returns only matching pages
- Controller test: detail pane data absent when search is active
- Stimulus: manual browser testing for debounce and frame updates
