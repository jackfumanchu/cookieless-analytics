# Pages Pagination — Design Spec

**Issue:** #3 — Add pagination for Pages list
**Date:** 2026-04-12

## Summary

Add numbered pagination to the Pages list. 20 results per page, with numbered page links inside the Turbo Frame. Composes with existing search and date range features.

## Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Pagination style | Numbered (1, 2, 3...) | User preference |
| Page size | 20 (hardcoded for now) | Configurable page size is a future enhancement |
| Controls location | Inside Turbo Frame | Must update with search/pagination frame reloads |
| Out-of-bounds page | Clamp to last valid page | Defensive — no cross-controller coupling needed |
| Page reset on search | Automatic (search omits `page` param → defaults to 1) | Search controller already builds URL without `page` |
| Page on date change | Server-side clamp | Date-range controller preserves all query params; if page exceeds new total, show last page |

## Repository layer

### Modified: `findTopPages()`

Add `int $offset = 0` parameter:

```php
public function findTopPages(
    \DateTimeImmutable $from,
    \DateTimeImmutable $to,
    int $limit = 20,
    ?string $search = null,
    int $offset = 0,
): array
```

Apply offset: `->setFirstResult($offset)`

### New: `countDistinctPages()`

```php
public function countDistinctPages(
    \DateTimeImmutable $from,
    \DateTimeImmutable $to,
    ?string $search = null,
): int
```

Returns `COUNT(DISTINCT p.pageUrl)` with the same date range and optional search filter as `findTopPages()`. Used to compute total pages for pagination metadata.

## Controller layer

`pagesView()` changes:

1. Read `?page=N` from request (default 1, clamped to >= 1)
2. Call `countDistinctPages()` to get total count
3. Compute `$totalPagesCount` = `ceil($total / $perPage)`
4. Clamp `$page` to `min($page, max(1, $totalPagesCount))` — handles out-of-bounds
5. Compute `$offset = ($page - 1) * $perPage`
6. Call `findTopPages()` with offset and limit
7. Pass to template: `currentPage`, `totalPagesCount`, `perPage` (in addition to existing vars)

The Turbo Frame partial (`_pages_list.html.twig`) also receives these pagination variables.

## Template — pagination controls

Inside the Turbo Frame, below the table, render pagination:

```
< Prev  1  2  [3]  4  5  Next >
```

Rules:
- Current page is highlighted (not a link)
- Prev/Next disabled (not shown or greyed) on first/last page
- Each link is a plain `<a>` with full query params: `?page=N&search=...&from=...&to=...`
- Turbo intercepts clicks inside the frame automatically — no JS needed
- For many pages, show a window: `1 ... 4 [5] 6 ... 20` (first page, window of 2 around current, last page)
- No pagination controls shown when total pages <= 1

Link generation: use Twig `path()` with query params, or build the URL manually since `page`, `search`, `from`, `to` are all query params on the same route.

## Search integration

- The search Stimulus controller builds URLs with `from`, `to`, and optionally `search` — no `page` param. This naturally resets to page 1.
- `countDistinctPages()` respects the search filter, so pagination reflects filtered results.
- The results hint (updated client-side after frame load) continues to count `tbody tr` rows — this shows the current page's count. The total count should also be visible somewhere (e.g., "Page 2 of 5" or "41-60 of 93 pages").

## Date range integration

- The date-range controller preserves all query params including `page` when navigating.
- If the new date range has fewer pages, the server clamps `page` to the last valid page.
- No changes to the date-range Stimulus controller needed.

## What stays unchanged

- Search behavior (search resets page to 1 automatically)
- Detail pane behavior (clears during search, pre-selects on full page load)
- Turbo Frame partial optimization (Task 5 from search implementation)
- The `page` default limit changes from 50 to 20

## Future enhancement

- Configurable page size (e.g., 20/50/100 dropdown). Not in scope for this issue.

## Testing

- Unit test: `findTopPages()` with offset returns correct slice
- Unit test: `countDistinctPages()` returns correct count, with and without search
- Functional test: `?page=2` returns second page of results
- Functional test: `?page=999` clamps to last valid page
- Functional test: `?search=blog&page=2` paginates within search results
- Functional test: Turbo Frame request with pagination returns partial
- Manual: browser test for numbered links, search + pagination interaction, date change clamping
