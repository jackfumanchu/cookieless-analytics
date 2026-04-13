# SQL Dialect Abstraction Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace PostgreSQL-specific raw SQL in repositories with dialect-aware expressions, enabling MySQL and SQLite support.

**Architecture:** A `SqlDialect` service detects the DBAL platform and exposes `dateToDay()` and `extractDomain()` methods returning SQL fragments. Repositories inject this service and swap hardcoded PostgreSQL syntax with dialect calls.

**Tech Stack:** PHP 8.2+, Doctrine DBAL 4, Symfony 7.4+, PHPUnit 13

**Spec:** `docs/superpowers/specs/2026-04-13-sql-dialect-abstraction-design.md`

---

### Task 1: Create `SqlDialect` service with `dateToDay()`

**Files:**
- Create: `src/Service/SqlDialect.php`
- Create: `tests/Unit/Service/SqlDialectTest.php`

- [ ] **Step 1: Write the failing tests for `dateToDay()`**

```php
<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\Unit\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Jackfumanchu\CookielessAnalyticsBundle\Service\SqlDialect;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SqlDialectTest extends TestCase
{
    private function createDialect(string $platformClass): SqlDialect
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new $platformClass());

        return new SqlDialect($connection);
    }

    #[Test]
    public function date_to_day_postgresql(): void
    {
        $dialect = $this->createDialect(PostgreSQLPlatform::class);

        self::assertSame("TO_CHAR(viewed_at, 'YYYY-MM-DD')", $dialect->dateToDay('viewed_at'));
    }

    #[Test]
    public function date_to_day_mysql(): void
    {
        $dialect = $this->createDialect(MySQLPlatform::class);

        self::assertSame("DATE_FORMAT(viewed_at, '%Y-%m-%d')", $dialect->dateToDay('viewed_at'));
    }

    #[Test]
    public function date_to_day_sqlite(): void
    {
        $dialect = $this->createDialect(SQLitePlatform::class);

        self::assertSame("strftime('%Y-%m-%d', viewed_at)", $dialect->dateToDay('viewed_at'));
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit tests/Unit/Service/SqlDialectTest.php --colors=never`
Expected: FAIL — class `SqlDialect` not found

- [ ] **Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;

final class SqlDialect
{
    private string $platform;

    public function __construct(Connection $connection)
    {
        $dbPlatform = $connection->getDatabasePlatform();

        $this->platform = match (true) {
            $dbPlatform instanceof PostgreSQLPlatform => 'postgresql',
            $dbPlatform instanceof MySQLPlatform => 'mysql',
            $dbPlatform instanceof SQLitePlatform => 'sqlite',
            default => throw new \RuntimeException(sprintf(
                'Unsupported database platform: %s',
                $dbPlatform::class,
            )),
        };
    }

    public function dateToDay(string $column): string
    {
        return match ($this->platform) {
            'postgresql' => "TO_CHAR({$column}, 'YYYY-MM-DD')",
            'mysql' => "DATE_FORMAT({$column}, '%Y-%m-%d')",
            'sqlite' => "strftime('%Y-%m-%d', {$column})",
        };
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit tests/Unit/Service/SqlDialectTest.php --colors=never`
Expected: OK (3 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Service/SqlDialect.php tests/Unit/Service/SqlDialectTest.php
git commit -m "feat: add SqlDialect service with dateToDay() for PostgreSQL, MySQL, SQLite"
```

---

### Task 2: Add `extractDomain()` to `SqlDialect`

**Files:**
- Modify: `src/Service/SqlDialect.php`
- Modify: `tests/Unit/Service/SqlDialectTest.php`

- [ ] **Step 1: Write the failing tests for `extractDomain()`**

Add to `SqlDialectTest.php`:

```php
#[Test]
public function extract_domain_postgresql(): void
{
    $dialect = $this->createDialect(PostgreSQLPlatform::class);

    self::assertSame("SUBSTRING(referrer FROM '://([^/]+)')", $dialect->extractDomain('referrer'));
}

#[Test]
public function extract_domain_mysql(): void
{
    $dialect = $this->createDialect(MySQLPlatform::class);

    self::assertSame(
        "SUBSTRING_INDEX(SUBSTRING_INDEX(referrer, '://', -1), '/', 1)",
        $dialect->extractDomain('referrer'),
    );
}

#[Test]
public function extract_domain_sqlite(): void
{
    $dialect = $this->createDialect(SQLitePlatform::class);

    self::assertSame(
        "RTRIM(REPLACE(REPLACE(referrer, 'https://', ''), 'http://', ''), '/')",
        $dialect->extractDomain('referrer'),
    );
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit tests/Unit/Service/SqlDialectTest.php --colors=never`
Expected: FAIL — method `extractDomain` not found

- [ ] **Step 3: Implement `extractDomain()`**

Add to `SqlDialect.php`:

```php
public function extractDomain(string $column): string
{
    return match ($this->platform) {
        'postgresql' => "SUBSTRING({$column} FROM '://([^/]+)')",
        'mysql' => "SUBSTRING_INDEX(SUBSTRING_INDEX({$column}, '://', -1), '/', 1)",
        'sqlite' => "RTRIM(REPLACE(REPLACE({$column}, 'https://', ''), 'http://', ''), '/')",
    };
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit tests/Unit/Service/SqlDialectTest.php --colors=never`
Expected: OK (6 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Service/SqlDialect.php tests/Unit/Service/SqlDialectTest.php
git commit -m "feat: add extractDomain() to SqlDialect for multi-database referrer parsing"
```

---

### Task 3: Add unsupported platform test

**Files:**
- Modify: `tests/Unit/Service/SqlDialectTest.php`

- [ ] **Step 1: Write the test**

Add to `SqlDialectTest.php`:

```php
#[Test]
public function throws_on_unsupported_platform(): void
{
    $connection = $this->createStub(Connection::class);
    $connection->method('getDatabasePlatform')->willReturn(
        $this->createStub(\Doctrine\DBAL\Platforms\OraclePlatform::class)
    );

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Unsupported database platform');

    new SqlDialect($connection);
}
```

- [ ] **Step 2: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Service/SqlDialectTest.php --colors=never`
Expected: OK (7 tests)

- [ ] **Step 3: Commit**

```bash
git add tests/Unit/Service/SqlDialectTest.php
git commit -m "test: add unsupported platform test for SqlDialect"
```

---

### Task 4: Register `SqlDialect` in bundle services

**Files:**
- Modify: `src/CookielessAnalyticsBundle.php:72-98`

- [ ] **Step 1: Add `SqlDialect` service registration**

Add this line after the `DateRangeResolver` registration (around line 89) in the `loadExtension` method:

```php
$services->set(SqlDialect::class);
```

Also add the import at the top of the file:

```php
use Jackfumanchu\CookielessAnalyticsBundle\Service\SqlDialect;
```

- [ ] **Step 2: Run existing tests to verify nothing broke**

Run: `vendor/bin/phpunit --testsuite default --colors=never`
Expected: OK (189 tests)

- [ ] **Step 3: Commit**

```bash
git add src/CookielessAnalyticsBundle.php
git commit -m "chore: register SqlDialect service in bundle DI"
```

---

### Task 5: Refactor `PageViewRepository::countByDay()` and `countByDayForPage()`

**Files:**
- Modify: `src/Repository/PageViewRepository.php:14-19,120-139,205-225`

- [ ] **Step 1: Add `SqlDialect` to constructor**

Change the constructor from:

```php
public function __construct(ManagerRegistry $registry)
{
    parent::__construct($registry, PageView::class);
}
```

to:

```php
public function __construct(
    ManagerRegistry $registry,
    private readonly SqlDialect $dialect,
) {
    parent::__construct($registry, PageView::class);
}
```

Add the import:

```php
use Jackfumanchu\CookielessAnalyticsBundle\Service\SqlDialect;
```

- [ ] **Step 2: Refactor `countByDay()`**

Replace the method body:

```php
public function countByDay(\DateTimeImmutable $from, \DateTimeImmutable $to): array
{
    $conn = $this->getEntityManager()->getConnection();
    $dateExpr = $this->dialect->dateToDay('viewed_at');

    $sql = "
        SELECT
            {$dateExpr} AS date,
            COUNT(*) AS count,
            COUNT(DISTINCT fingerprint) AS unique
        FROM ca_page_view
        WHERE viewed_at >= :from AND viewed_at <= :to
        GROUP BY date
        ORDER BY date ASC
    ";

    return $conn->executeQuery($sql, [
        'from' => $from->format('Y-m-d H:i:s'),
        'to' => $to->format('Y-m-d H:i:s'),
    ])->fetchAllAssociative();
}
```

- [ ] **Step 3: Refactor `countByDayForPage()`**

Replace the method body:

```php
public function countByDayForPage(string $pageUrl, \DateTimeImmutable $from, \DateTimeImmutable $to): array
{
    $conn = $this->getEntityManager()->getConnection();
    $dateExpr = $this->dialect->dateToDay('viewed_at');

    $sql = "
        SELECT
            {$dateExpr} AS date,
            COUNT(*) AS count,
            COUNT(DISTINCT fingerprint) AS unique
        FROM ca_page_view
        WHERE viewed_at >= :from AND viewed_at <= :to AND page_url = :pageUrl
        GROUP BY date
        ORDER BY date ASC
    ";

    return $conn->executeQuery($sql, [
        'from' => $from->format('Y-m-d H:i:s'),
        'to' => $to->format('Y-m-d H:i:s'),
        'pageUrl' => $pageUrl,
    ])->fetchAllAssociative();
}
```

- [ ] **Step 4: Run existing tests to verify nothing broke**

Run: `vendor/bin/phpunit --testsuite default --colors=never`
Expected: OK (189 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Repository/PageViewRepository.php
git commit -m "refactor: use SqlDialect.dateToDay() in PageViewRepository date methods"
```

---

### Task 6: Refactor `PageViewRepository::findTopReferrers()` and `findTopReferrersForPage()`

**Files:**
- Modify: `src/Repository/PageViewRepository.php:144-200`

- [ ] **Step 1: Refactor `findTopReferrers()`**

Replace the method body:

```php
public function findTopReferrers(\DateTimeImmutable $from, \DateTimeImmutable $to, int $limit = 10): array
{
    $conn = $this->getEntityManager()->getConnection();
    $domainExpr = $this->dialect->extractDomain('referrer');

    $sql = "
        SELECT source, visits FROM (
            SELECT
                CASE
                    WHEN referrer IS NULL OR referrer = '' THEN 'Direct'
                    ELSE {$domainExpr}
                END AS source,
                COUNT(*) AS visits
            FROM ca_page_view
            WHERE viewed_at >= :from AND viewed_at <= :to
            GROUP BY source
        ) sub
        ORDER BY visits DESC, source = 'Direct' ASC, source ASC
        LIMIT :limit
    ";

    return $conn->executeQuery($sql, [
        'from' => $from->format('Y-m-d H:i:s'),
        'to' => $to->format('Y-m-d H:i:s'),
        'limit' => $limit,
    ])->fetchAllAssociative();
}
```

- [ ] **Step 2: Refactor `findTopReferrersForPage()`**

Replace the method body:

```php
public function findTopReferrersForPage(string $pageUrl, \DateTimeImmutable $from, \DateTimeImmutable $to, int $limit = 10): array
{
    $conn = $this->getEntityManager()->getConnection();
    $domainExpr = $this->dialect->extractDomain('referrer');

    $sql = "
        SELECT source, visits FROM (
            SELECT
                CASE
                    WHEN referrer IS NULL OR referrer = '' THEN 'Direct'
                    ELSE {$domainExpr}
                END AS source,
                COUNT(*) AS visits
            FROM ca_page_view
            WHERE viewed_at >= :from AND viewed_at <= :to AND page_url = :pageUrl
            GROUP BY source
        ) sub
        ORDER BY visits DESC, source = 'Direct' ASC, source ASC
        LIMIT :limit
    ";

    return $conn->executeQuery($sql, [
        'from' => $from->format('Y-m-d H:i:s'),
        'to' => $to->format('Y-m-d H:i:s'),
        'pageUrl' => $pageUrl,
        'limit' => $limit,
    ])->fetchAllAssociative();
}
```

- [ ] **Step 3: Run existing tests to verify nothing broke**

Run: `vendor/bin/phpunit --testsuite default --colors=never`
Expected: OK (189 tests)

- [ ] **Step 4: Commit**

```bash
git add src/Repository/PageViewRepository.php
git commit -m "refactor: use SqlDialect.extractDomain() in PageViewRepository referrer methods"
```

---

### Task 7: Refactor `AnalyticsEventRepository::countByDayForEvent()` and `countByDay()`

**Files:**
- Modify: `src/Repository/AnalyticsEventRepository.php:14-19,78-97,143-161`

- [ ] **Step 1: Add `SqlDialect` to constructor**

Change the constructor from:

```php
public function __construct(ManagerRegistry $registry)
{
    parent::__construct($registry, AnalyticsEvent::class);
}
```

to:

```php
public function __construct(
    ManagerRegistry $registry,
    private readonly SqlDialect $dialect,
) {
    parent::__construct($registry, AnalyticsEvent::class);
}
```

Add the import:

```php
use Jackfumanchu\CookielessAnalyticsBundle\Service\SqlDialect;
```

- [ ] **Step 2: Refactor `countByDayForEvent()`**

Replace the method body:

```php
public function countByDayForEvent(string $name, \DateTimeImmutable $from, \DateTimeImmutable $to): array
{
    $conn = $this->getEntityManager()->getConnection();
    $dateExpr = $this->dialect->dateToDay('recorded_at');

    $sql = "
        SELECT
            {$dateExpr} AS date,
            COUNT(*) AS count
        FROM ca_analytics_event
        WHERE recorded_at >= :from AND recorded_at <= :to AND name = :name
        GROUP BY date
        ORDER BY date ASC
    ";

    return $conn->executeQuery($sql, [
        'from' => $from->format('Y-m-d H:i:s'),
        'to' => $to->format('Y-m-d H:i:s'),
        'name' => $name,
    ])->fetchAllAssociative();
}
```

- [ ] **Step 3: Refactor `countByDay()`**

Replace the method body:

```php
public function countByDay(\DateTimeImmutable $from, \DateTimeImmutable $to): array
{
    $conn = $this->getEntityManager()->getConnection();
    $dateExpr = $this->dialect->dateToDay('recorded_at');

    $sql = "
        SELECT
            {$dateExpr} AS date,
            COUNT(*) AS count
        FROM ca_analytics_event
        WHERE recorded_at >= :from AND recorded_at <= :to
        GROUP BY date
        ORDER BY date ASC
    ";

    return $conn->executeQuery($sql, [
        'from' => $from->format('Y-m-d H:i:s'),
        'to' => $to->format('Y-m-d H:i:s'),
    ])->fetchAllAssociative();
}
```

- [ ] **Step 4: Run all tests to verify nothing broke**

Run: `vendor/bin/phpunit --testsuite default --colors=never`
Expected: OK (189 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Repository/AnalyticsEventRepository.php
git commit -m "refactor: use SqlDialect.dateToDay() in AnalyticsEventRepository date methods"
```

---

### Task 8: Delete dead `SqlDateHelper` files

**Files:**
- Delete: `src/SqlDateHelper.php`
- Delete: `src/Infrastructure/Persistence/Sql/SqlDateHelper.php`
- Delete: `src/Infrastructure/Persistence/Sql/` (directory)
- Delete: `src/Infrastructure/Persistence/` (directory)
- Delete: `src/Infrastructure/` (directory)

- [ ] **Step 1: Remove the files and empty directories**

```bash
rm src/SqlDateHelper.php
rm -r src/Infrastructure/
```

- [ ] **Step 2: Run all tests to verify nothing broke**

Run: `vendor/bin/phpunit --testsuite default --colors=never`
Expected: OK (189 tests)

- [ ] **Step 3: Commit**

```bash
git add -A src/SqlDateHelper.php src/Infrastructure/
git commit -m "chore: remove dead SqlDateHelper files"
```

---

### Task 9: Add SQLite integration test configuration

**Files:**
- Modify: `tests/App/config/doctrine.yaml`
- Modify: `phpunit.dist.xml`

- [ ] **Step 1: Make test doctrine config support SQLite via env var**

Replace `tests/App/config/doctrine.yaml` content:

```yaml
doctrine:
    dbal:
        driver: '%env(default:DOCTRINE_DRIVER_DEFAULT:DOCTRINE_DRIVER)%'
        url: '%env(DATABASE_URL)%'
        # TEST_TOKEN is set by ParaTest/Infection for parallel processes
        dbname_suffix: '%env(default::TEST_TOKEN)%'
        server_version: '18.3'
    orm:
        auto_generate_proxy_classes: true

        auto_mapping: true
        mappings:
            CookielessAnalyticsBundle:
                is_bundle: false
                type: attribute
                dir: '%kernel.project_dir%/../../src/Entity'
                prefix: 'Jackfumanchu\CookielessAnalyticsBundle\Entity'
                alias: CookielessAnalytics
```

Wait — the `url` parameter already drives the driver selection in DBAL. A simpler approach: just swap `DATABASE_URL` to a SQLite URL when running the SQLite suite.

Revert to keeping `doctrine.yaml` as-is (it already reads `DATABASE_URL`). Instead, add a separate PHPUnit config for SQLite.

Create `phpunit.sqlite.xml`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         colors="true"
         bootstrap="tests/bootstrap.php"
         cacheDirectory=".phpunit.cache"
>
    <php>
        <ini name="display_errors" value="1" />
        <ini name="error_reporting" value="-1" />
        <server name="APP_ENV" value="test" force="true" />
        <server name="SHELL_VERBOSITY" value="-1" />
        <server name="KERNEL_CLASS" value="Jackfumanchu\CookielessAnalyticsBundle\Tests\App\Kernel" force="true" />
        <env name="DATABASE_URL" value="sqlite:///%kernel.project_dir%/var/test.db" />
    </php>

    <testsuites>
        <testsuite name="sqlite">
            <directory>tests/Integration</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

- [ ] **Step 2: Check that `tests/bootstrap.php` works with SQLite**

The bootstrap creates the database and schema. The `CREATE DATABASE` statement will fail for SQLite (SQLite creates the file automatically). The bootstrap already wraps it in a try/catch, so it should be fine. Verify by running:

Run: `vendor/bin/phpunit -c phpunit.sqlite.xml --colors=never`

If the bootstrap fails because SQLite doesn't support `CREATE DATABASE`, the fix is to skip that step for SQLite. Modify `tests/bootstrap.php` — wrap the database creation block:

Replace:

```php
// Connect without a database to create it if needed
$tmpParams = $params;
$tmpParams['dbname'] = 'postgres';
$tmpConn = \Doctrine\DBAL\DriverManager::getConnection($tmpParams);

try {
    $tmpConn->executeStatement(sprintf('CREATE DATABASE "%s"', $dbName));
} catch (\Doctrine\DBAL\Exception) {
    // Database already exists — ignore
}
$tmpConn->close();
```

with:

```php
// Create database (skip for SQLite — file is created automatically)
if (!$conn->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\SQLitePlatform) {
    $tmpParams = $params;
    $tmpParams['dbname'] = 'postgres';
    $tmpConn = \Doctrine\DBAL\DriverManager::getConnection($tmpParams);

    try {
        $tmpConn->executeStatement(sprintf('CREATE DATABASE "%s"', $dbName));
    } catch (\Doctrine\DBAL\Exception) {
        // Database already exists — ignore
    }
    $tmpConn->close();
}
```

- [ ] **Step 3: Also handle `dbname_suffix` for SQLite**

The `doctrine.yaml` has `dbname_suffix: '%env(default::TEST_TOKEN)%'`. This appends a suffix to the database name for parallel test processes. For SQLite, this translates to appending to the filename. This should work out of the box — Doctrine appends it to the `path` parameter. Verify it works in the test run.

- [ ] **Step 4: Run SQLite integration tests**

Run: `vendor/bin/phpunit -c phpunit.sqlite.xml --colors=never`
Expected: OK (8 tests) — all integration tests pass against SQLite

- [ ] **Step 5: Run PostgreSQL tests to confirm they still pass**

Run: `vendor/bin/phpunit --testsuite default --colors=never`
Expected: OK (189 tests)

- [ ] **Step 6: Commit**

```bash
git add phpunit.sqlite.xml tests/bootstrap.php
git commit -m "test: add SQLite integration test configuration"
```

---

### Task 10: Add SQLite run to CI

**Files:**
- Modify: `.github/workflows/ci.yml`

- [ ] **Step 1: Add a SQLite test step after the main test run**

In the `tests` job, after the existing PHPUnit step and before the Mutation testing step, add:

```yaml
      - name: SQLite integration tests
        run: vendor/bin/phpunit -c phpunit.sqlite.xml --no-coverage
```

This runs the integration tests against SQLite on all PHP versions, confirming multi-database SQL works.

- [ ] **Step 2: Commit**

```bash
git add .github/workflows/ci.yml
git commit -m "ci: add SQLite integration test run"
```
