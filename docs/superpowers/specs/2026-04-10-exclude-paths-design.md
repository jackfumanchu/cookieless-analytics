# Exclude Paths — Design Spec

## Overview

Add configurable path exclusion to the cookieless analytics bundle. Paths matching regex patterns (e.g. `^/admin`, `^/_`, `^/api`) are silently discarded — no page view or event is persisted. Filtering happens server-side in both controllers.

## Decisions

| Decision | Choice | Rationale |
|---|---|---|
| Scope | Both page views and events | If a page is excluded, no tracking data from it should be recorded |
| Where | Server-side only | Config lives in Symfony, JS doesn't need the patterns, no client bypass |
| Response for excluded | 204 (same as success) | Client doesn't need to know data was discarded |
| Architecture | Dedicated PathExcluder service | Single responsibility, reusable, testable, follows existing service pattern |

## Service: PathExcluder

Location: `src/Service/PathExcluder.php`

- Constructor: `array<string> $patterns` (regex patterns from bundle config)
- Method: `isExcluded(string $url): bool`
- Logic: extract path from URL (strip query string), iterate patterns, return `true` if any `preg_match('#' . $pattern . '#', $path)` matches (wraps pattern in `#` delimiters so users write bare regex like `^/admin`)
- Empty patterns array → always returns `false`

## Bundle Configuration

Location: `src/CookielessAnalyticsBundle.php`

New config key:

```yaml
cookieless_analytics:
    exclude_paths:
        - '^/admin'
        - '^/_'
        - '^/api'
```

Default: empty array (nothing excluded). Injected into `PathExcluder` constructor.

## Controller Changes

Both `CollectController` and `EventController`:

- Add `PathExcluder` as constructor dependency (autowired)
- After URL validation, before fingerprinting: check `$this->pathExcluder->isExcluded($url)`
- If excluded: return `204 No Content` immediately, no persist
- CollectController checks the `url` field, EventController checks the `pageUrl` field

## File Structure

**New files:**
```
src/Service/PathExcluder.php
tests/Unit/Service/PathExcluderTest.php
```

**Modified files:**
```
src/CookielessAnalyticsBundle.php
src/Controller/CollectController.php
src/Controller/EventController.php
tests/Functional/Controller/CollectControllerTest.php
tests/Functional/Controller/EventControllerTest.php
tests/App/config/cookieless_analytics.yaml
```

## Testing Strategy

### Unit Tests

- `PathExcluderTest`:
  - Matches single pattern
  - Matches one of multiple patterns
  - No match returns false
  - Empty patterns array never excludes
  - Strips query string before matching (URL with `?foo=bar` still matched by path pattern)
  - Pattern anchoring works (`^/admin` does not match `/dashboard/admin`)

### Functional Tests

- `CollectControllerTest` — add: POST with excluded URL → 204, no entity persisted
- `EventControllerTest` — add: POST with excluded pageUrl → 204, no entity persisted
