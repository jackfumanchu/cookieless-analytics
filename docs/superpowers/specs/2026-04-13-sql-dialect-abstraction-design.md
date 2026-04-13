# SQL Dialect Abstraction

## Problem

All 6 raw SQL methods in `PageViewRepository` and `AnalyticsEventRepository` use PostgreSQL-specific syntax (`TO_CHAR`, `SUBSTRING ... FROM` with regex). The bundle only works with PostgreSQL. Users on MySQL or SQLite cannot use it.

## Goal

Support PostgreSQL, MySQL, and SQLite by abstracting the two dialect-sensitive SQL operations behind a single service.

## Design

### New service: `SqlDialect`

**Location:** `src/Service/SqlDialect.php`

A service injected into repositories that provides dialect-aware SQL expression fragments. It detects the database platform once from the DBAL connection.

**Platform detection:** Uses `instanceof` checks on `$connection->getDatabasePlatform()` (DBAL 4 idiomatic — `getName()` was removed).

**Methods:**

#### `dateToDay(string $column): string`

Returns a SQL expression that formats a timestamp column as `YYYY-MM-DD`.

| Platform   | Output                                    |
|------------|-------------------------------------------|
| PostgreSQL | `TO_CHAR(column, 'YYYY-MM-DD')`          |
| MySQL      | `DATE_FORMAT(column, '%Y-%m-%d')`         |
| SQLite     | `strftime('%Y-%m-%d', column)`            |

#### `extractDomain(string $column): string`

Returns a SQL expression that extracts the domain from a URL (e.g. `https://google.com/search` -> `google.com`).

| Platform   | Output                                                                        |
|------------|-------------------------------------------------------------------------------|
| PostgreSQL | `SUBSTRING(column FROM '://([^/]+)')`                                        |
| MySQL      | `SUBSTRING_INDEX(SUBSTRING_INDEX(column, '://', -1), '/', 1)`               |
| SQLite     | `RTRIM(REPLACE(REPLACE(column, 'https://', ''), 'http://', ''), '/')`        |

The SQLite variant is simplified (handles `http://` and `https://` protocols, strips trailing path). This is acceptable for an analytics context.

### Repository changes

The 6 raw SQL methods are refactored to use `SqlDialect` instead of hardcoded PostgreSQL fragments. `SqlDialect` is added as a constructor dependency alongside `ManagerRegistry`.

| Repository               | Method                  | Dialect method used |
|--------------------------|-------------------------|---------------------|
| PageViewRepository       | `countByDay`            | `dateToDay`         |
| PageViewRepository       | `countByDayForPage`     | `dateToDay`         |
| PageViewRepository       | `findTopReferrers`      | `extractDomain`     |
| PageViewRepository       | `findTopReferrersForPage` | `extractDomain`   |
| AnalyticsEventRepository | `countByDayForEvent`    | `dateToDay`         |
| AnalyticsEventRepository | `countByDay`            | `dateToDay`         |

The `CASE WHEN referrer IS NULL OR referrer = '' THEN 'Direct' ELSE ... END` logic in referrer methods stays as plain SQL (standard across all dialects). Only the domain extraction expression inside the `ELSE` uses the dialect.

Everything else in the methods (table names, WHERE clauses, GROUP BY, ORDER BY, LIMIT, parameter binding) remains unchanged.

### Cleanup

- Delete `src/SqlDateHelper.php` (dead code, never wired)
- Delete `src/Infrastructure/Persistence/Sql/SqlDateHelper.php` (dead code, duplicate)
- Delete `src/Infrastructure/` directory if empty after removal

### Testing

**Unit tests for `SqlDialect`:**
- 3 platforms x 2 methods = 6 test cases
- Stub the DBAL `Connection` to return different `AbstractPlatform` instances (`PostgreSQLPlatform`, `MySQLPlatform`, `SQLitePlatform`)
- Assert the SQL fragment strings are correct
- Test unsupported platform throws or falls back gracefully

**Existing integration + functional tests:**
- Already cover all 6 repository methods through PostgreSQL
- Verify nothing broke after refactor — no new tests needed

**SQLite integration run:**
- Add a SQLite-specific test configuration (env-var-driven database URL switch in the test kernel)
- Run the existing integration tests against SQLite to validate the generated SQL actually executes
- Zero infrastructure needed (no server)

## Not in scope

- No `AnalyticsQueryBuilder` or `AnalyticsQueryService` layer
- No table name abstraction (table names stay hardcoded in SQL)
- No granularity parameter (`day`/`hour`/`week`) — dialect methods can be extended later
- No SQL Server support
