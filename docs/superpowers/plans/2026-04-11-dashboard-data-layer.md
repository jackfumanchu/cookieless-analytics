# Dashboard Data Layer Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add all missing repository methods and a PeriodComparer service so the dashboard templates have every data point the v3 mockups require.

**Architecture:** New repository methods stay in the existing `PageViewRepository` and `AnalyticsEventRepository`. A `PeriodComparer` service encapsulates the "fetch current + fetch comparison + compute % change" pattern. A `PeriodComparison` DTO carries the result. The existing `DashboardController::overview()` is refactored to use `PeriodComparer` instead of inline comparison logic. All new code is TDD with the existing KernelTestCase pattern for repositories and a pure PHPUnit TestCase for the service.

**Tech Stack:** PHP 8.2+, Symfony 7.4, Doctrine ORM 3, PostgreSQL, PHPUnit 10+

---

### Task 1: PeriodComparison DTO

**Files:**
- Create: `src/Service/PeriodComparison.php`
- Test: `tests/Unit/Service/PeriodComparisonTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\Unit\Service;

use Jackfumanchu\CookielessAnalyticsBundle\Service\PeriodComparison;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PeriodComparisonTest extends TestCase
{
    #[Test]
    public function it_computes_positive_change(): void
    {
        $comparison = PeriodComparison::from(114, 100);

        self::assertSame(114, $comparison->current);
        self::assertSame(100, $comparison->previous);
        self::assertSame(14.0, $comparison->changePercent);
    }

    #[Test]
    public function it_computes_negative_change(): void
    {
        $comparison = PeriodComparison::from(80, 100);

        self::assertSame(80, $comparison->current);
        self::assertSame(100, $comparison->previous);
        self::assertSame(-20.0, $comparison->changePercent);
    }

    #[Test]
    public function it_handles_zero_previous(): void
    {
        $comparison = PeriodComparison::from(50, 0);

        self::assertSame(50, $comparison->current);
        self::assertSame(0, $comparison->previous);
        self::assertSame(0.0, $comparison->changePercent);
    }

    #[Test]
    public function it_rounds_to_one_decimal(): void
    {
        $comparison = PeriodComparison::from(103, 100);

        self::assertSame(3.0, $comparison->changePercent);
    }

    #[Test]
    public function it_supports_float_values(): void
    {
        $comparison = PeriodComparison::fromFloat(3.9, 3.7);

        self::assertSame(3.9, $comparison->currentFloat);
        self::assertSame(3.7, $comparison->previousFloat);
        self::assertSame(5.4, $comparison->changePercent);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit tests/Unit/Service/PeriodComparisonTest.php`
Expected: FAIL — class PeriodComparison not found

- [ ] **Step 3: Write the implementation**

```php
<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Service;

class PeriodComparison
{
    private function __construct(
        public readonly int $current,
        public readonly int $previous,
        public readonly float $currentFloat,
        public readonly float $previousFloat,
        public readonly float $changePercent,
    ) {
    }

    public static function from(int $current, int $previous): self
    {
        return new self(
            $current,
            $previous,
            (float) $current,
            (float) $previous,
            self::computeChange($current, $previous),
        );
    }

    public static function fromFloat(float $current, float $previous): self
    {
        return new self(
            (int) $current,
            (int) $previous,
            $current,
            $previous,
            self::computeChange($current, $previous),
        );
    }

    private static function computeChange(float $current, float $previous): float
    {
        if ($previous == 0) {
            return 0.0;
        }

        return round(($current - $previous) / $previous * 100, 1);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php vendor/bin/phpunit tests/Unit/Service/PeriodComparisonTest.php`
Expected: OK (5 tests, 15 assertions)

- [ ] **Step 5: Commit**

```bash
git add src/Service/PeriodComparison.php tests/Unit/Service/PeriodComparisonTest.php
git commit -m "feat: add PeriodComparison DTO with percentage change calculation"
```

---

### Task 2: PeriodComparer service

**Files:**
- Create: `src/Service/PeriodComparer.php`
- Test: `tests/Unit/Service/PeriodComparerTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\Unit\Service;

use Jackfumanchu\CookielessAnalyticsBundle\Service\DateRange;
use Jackfumanchu\CookielessAnalyticsBundle\Service\PeriodComparer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PeriodComparerTest extends TestCase
{
    private PeriodComparer $comparer;

    protected function setUp(): void
    {
        $this->comparer = new PeriodComparer();
    }

    private function makeDateRange(): DateRange
    {
        return new DateRange(
            from: new \DateTimeImmutable('2026-04-05 00:00:00'),
            to: new \DateTimeImmutable('2026-04-07 23:59:59'),
            comparisonFrom: new \DateTimeImmutable('2026-04-02 00:00:00'),
            comparisonTo: new \DateTimeImmutable('2026-04-04 23:59:59'),
        );
    }

    #[Test]
    public function compare_calls_query_for_both_periods(): void
    {
        $range = $this->makeDateRange();
        $calls = [];

        $query = function (\DateTimeImmutable $from, \DateTimeImmutable $to) use (&$calls): int {
            $calls[] = [$from->format('Y-m-d'), $to->format('Y-m-d H:i:s')];
            return count($calls) === 1 ? 114 : 100;
        };

        $result = $this->comparer->compare($range, $query);

        self::assertCount(2, $calls);
        self::assertSame('2026-04-05', $calls[0][0]);
        self::assertSame('2026-04-07 23:59:59', $calls[0][1]);
        self::assertSame('2026-04-02', $calls[1][0]);
        self::assertSame('2026-04-04 23:59:59', $calls[1][1]);
        self::assertSame(114, $result->current);
        self::assertSame(100, $result->previous);
        self::assertSame(14.0, $result->changePercent);
    }

    #[Test]
    public function compare_float_calls_query_for_both_periods(): void
    {
        $range = $this->makeDateRange();

        $query = function (\DateTimeImmutable $from, \DateTimeImmutable $to): float {
            return $from->format('Y-m-d') === '2026-04-05' ? 3.9 : 3.7;
        };

        $result = $this->comparer->compareFloat($range, $query);

        self::assertSame(3.9, $result->currentFloat);
        self::assertSame(3.7, $result->previousFloat);
        self::assertSame(5.4, $result->changePercent);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit tests/Unit/Service/PeriodComparerTest.php`
Expected: FAIL — class PeriodComparer not found

- [ ] **Step 3: Write the implementation**

```php
<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Service;

class PeriodComparer
{
    /**
     * @param callable(\DateTimeImmutable, \DateTimeImmutable): int $query
     */
    public function compare(DateRange $range, callable $query): PeriodComparison
    {
        $current = $query($range->from, $range->to);
        $previous = $query($range->comparisonFrom, $range->comparisonTo);

        return PeriodComparison::from($current, $previous);
    }

    /**
     * @param callable(\DateTimeImmutable, \DateTimeImmutable): float $query
     */
    public function compareFloat(DateRange $range, callable $query): PeriodComparison
    {
        $current = $query($range->from, $range->to);
        $previous = $query($range->comparisonFrom, $range->comparisonTo);

        return PeriodComparison::fromFloat($current, $previous);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php vendor/bin/phpunit tests/Unit/Service/PeriodComparerTest.php`
Expected: OK (2 tests, 9 assertions)

- [ ] **Step 5: Commit**

```bash
git add src/Service/PeriodComparer.php tests/Unit/Service/PeriodComparerTest.php
git commit -m "feat: add PeriodComparer service for current vs previous period comparison"
```

---

### Task 3: PageViewRepository — findTopReferrers

**Files:**
- Modify: `src/Repository/PageViewRepository.php`
- Modify: `tests/Unit/Repository/PageViewRepositoryTest.php`

- [ ] **Step 1: Write the failing test**

Add to `PageViewRepositoryTest.php`:

```php
#[Test]
public function find_top_referrers_returns_grouped_domains(): void
{
    $this->createPageView('/home', 'aaa', '2026-04-05', 'https://google.com/search?q=test');
    $this->createPageView('/about', 'bbb', '2026-04-05', 'https://google.com/search?q=other');
    $this->createPageView('/home', 'ccc', '2026-04-06', 'https://twitter.com/post/123');
    $this->createPageView('/home', 'ddd', '2026-04-06', null); // direct traffic

    $from = new \DateTimeImmutable('2026-04-05 00:00:00');
    $to = new \DateTimeImmutable('2026-04-07 23:59:59');

    $result = $this->repository->findTopReferrers($from, $to, 10);

    self::assertCount(3, $result);
    self::assertSame('google.com', $result[0]['source']);
    self::assertSame(2, (int) $result[0]['visits']);
    self::assertSame('twitter.com', $result[1]['source']);
    self::assertSame(1, (int) $result[1]['visits']);
    self::assertSame('Direct', $result[2]['source']);
    self::assertSame(1, (int) $result[2]['visits']);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit tests/Unit/Repository/PageViewRepositoryTest.php --filter=find_top_referrers`
Expected: FAIL — method findTopReferrers does not exist

- [ ] **Step 3: Write the implementation**

Add to `PageViewRepository.php`:

```php
/**
 * @return list<array{source: string, visits: int}>
 */
public function findTopReferrers(\DateTimeImmutable $from, \DateTimeImmutable $to, int $limit = 10): array
{
    $conn = $this->getEntityManager()->getConnection();

    $sql = <<<'SQL'
        SELECT
            CASE
                WHEN referrer IS NULL OR referrer = '' THEN 'Direct'
                ELSE SUBSTRING(referrer FROM '://([^/]+)')
            END AS source,
            COUNT(*) AS visits
        FROM ca_page_view
        WHERE viewed_at >= :from AND viewed_at <= :to
        GROUP BY source
        ORDER BY visits DESC
        LIMIT :limit
    SQL;

    return $conn->executeQuery($sql, [
        'from' => $from->format('Y-m-d H:i:s'),
        'to' => $to->format('Y-m-d H:i:s'),
        'limit' => $limit,
    ])->fetchAllAssociative();
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php vendor/bin/phpunit tests/Unit/Repository/PageViewRepositoryTest.php --filter=find_top_referrers`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Repository/PageViewRepository.php tests/Unit/Repository/PageViewRepositoryTest.php
git commit -m "feat: add findTopReferrers to PageViewRepository"
```

---

### Task 4: PageViewRepository — findTopReferrersForPage

**Files:**
- Modify: `src/Repository/PageViewRepository.php`
- Modify: `tests/Unit/Repository/PageViewRepositoryTest.php`

- [ ] **Step 1: Write the failing test**

Add to `PageViewRepositoryTest.php`:

```php
#[Test]
public function find_top_referrers_for_page_filters_by_url(): void
{
    $this->createPageView('/docs', 'aaa', '2026-04-05', 'https://google.com/search');
    $this->createPageView('/docs', 'bbb', '2026-04-05', 'https://twitter.com/post');
    $this->createPageView('/home', 'ccc', '2026-04-05', 'https://google.com/search');

    $from = new \DateTimeImmutable('2026-04-05 00:00:00');
    $to = new \DateTimeImmutable('2026-04-07 23:59:59');

    $result = $this->repository->findTopReferrersForPage('/docs', $from, $to, 10);

    self::assertCount(2, $result);
    self::assertSame('google.com', $result[0]['source']);
    self::assertSame(1, (int) $result[0]['visits']);
    self::assertSame('twitter.com', $result[1]['source']);
    self::assertSame(1, (int) $result[1]['visits']);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit tests/Unit/Repository/PageViewRepositoryTest.php --filter=find_top_referrers_for_page`
Expected: FAIL — method does not exist

- [ ] **Step 3: Write the implementation**

Add to `PageViewRepository.php`:

```php
/**
 * @return list<array{source: string, visits: int}>
 */
public function findTopReferrersForPage(string $pageUrl, \DateTimeImmutable $from, \DateTimeImmutable $to, int $limit = 10): array
{
    $conn = $this->getEntityManager()->getConnection();

    $sql = <<<'SQL'
        SELECT
            CASE
                WHEN referrer IS NULL OR referrer = '' THEN 'Direct'
                ELSE SUBSTRING(referrer FROM '://([^/]+)')
            END AS source,
            COUNT(*) AS visits
        FROM ca_page_view
        WHERE viewed_at >= :from AND viewed_at <= :to AND page_url = :pageUrl
        GROUP BY source
        ORDER BY visits DESC
        LIMIT :limit
    SQL;

    return $conn->executeQuery($sql, [
        'from' => $from->format('Y-m-d H:i:s'),
        'to' => $to->format('Y-m-d H:i:s'),
        'pageUrl' => $pageUrl,
        'limit' => $limit,
    ])->fetchAllAssociative();
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php vendor/bin/phpunit tests/Unit/Repository/PageViewRepositoryTest.php --filter=find_top_referrers_for_page`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Repository/PageViewRepository.php tests/Unit/Repository/PageViewRepositoryTest.php
git commit -m "feat: add findTopReferrersForPage to PageViewRepository"
```

---

### Task 5: PageViewRepository — countByDayForPage

**Files:**
- Modify: `src/Repository/PageViewRepository.php`
- Modify: `tests/Unit/Repository/PageViewRepositoryTest.php`

- [ ] **Step 1: Write the failing test**

Add to `PageViewRepositoryTest.php`:

```php
#[Test]
public function count_by_day_for_page_filters_by_url(): void
{
    $fp = str_repeat('a', 64);
    $fp2 = str_repeat('b', 64);

    $this->createPageView('/docs', $fp, '2026-04-05 10:00:00');
    $this->createPageView('/docs', $fp2, '2026-04-05 11:00:00');
    $this->createPageView('/home', $fp, '2026-04-05 12:00:00');
    $this->createPageView('/docs', $fp, '2026-04-06 10:00:00');

    $from = new \DateTimeImmutable('2026-04-05 00:00:00');
    $to = new \DateTimeImmutable('2026-04-06 23:59:59');

    $result = $this->repository->countByDayForPage('/docs', $from, $to);

    self::assertCount(2, $result);
    self::assertSame('2026-04-05', $result[0]['date']);
    self::assertSame(2, (int) $result[0]['count']);
    self::assertSame(2, (int) $result[0]['unique']);
    self::assertSame('2026-04-06', $result[1]['date']);
    self::assertSame(1, (int) $result[1]['count']);
    self::assertSame(1, (int) $result[1]['unique']);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit tests/Unit/Repository/PageViewRepositoryTest.php --filter=count_by_day_for_page`
Expected: FAIL — method does not exist

- [ ] **Step 3: Write the implementation**

Add to `PageViewRepository.php`:

```php
/**
 * @return list<array{date: string, count: int, unique: int}>
 */
public function countByDayForPage(string $pageUrl, \DateTimeImmutable $from, \DateTimeImmutable $to): array
{
    $conn = $this->getEntityManager()->getConnection();

    $sql = <<<'SQL'
        SELECT
            TO_CHAR(viewed_at, 'YYYY-MM-DD') AS date,
            COUNT(*) AS count,
            COUNT(DISTINCT fingerprint) AS unique
        FROM ca_page_view
        WHERE viewed_at >= :from AND viewed_at <= :to AND page_url = :pageUrl
        GROUP BY date
        ORDER BY date ASC
    SQL;

    return $conn->executeQuery($sql, [
        'from' => $from->format('Y-m-d H:i:s'),
        'to' => $to->format('Y-m-d H:i:s'),
        'pageUrl' => $pageUrl,
    ])->fetchAllAssociative();
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php vendor/bin/phpunit tests/Unit/Repository/PageViewRepositoryTest.php --filter=count_by_day_for_page`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Repository/PageViewRepository.php tests/Unit/Repository/PageViewRepositoryTest.php
git commit -m "feat: add countByDayForPage to PageViewRepository"
```

---

### Task 6: AnalyticsEventRepository — countDistinctTypes and countUniqueActors

**Files:**
- Modify: `src/Repository/AnalyticsEventRepository.php`
- Modify: `tests/Unit/Repository/AnalyticsEventRepositoryTest.php`

- [ ] **Step 1: Write the failing tests**

Add to `AnalyticsEventRepositoryTest.php`. Note: the existing `createEvent` helper uses a hardcoded fingerprint. We need a variant that accepts a fingerprint. Add a new helper:

```php
private function createEventWithFingerprint(string $name, string $fingerprint, string $date, ?string $value = null, string $pageUrl = '/home'): void
{
    $event = AnalyticsEvent::create(
        fingerprint: $fingerprint,
        name: $name,
        value: $value,
        pageUrl: $pageUrl,
        recordedAt: new \DateTimeImmutable($date),
    );
    $this->em->persist($event);
    $this->em->flush();
}
```

Then the tests:

```php
#[Test]
public function count_distinct_types_returns_unique_event_names(): void
{
    $this->createEvent('click-cta', '2026-04-05');
    $this->createEvent('click-cta', '2026-04-06');
    $this->createEvent('signup', '2026-04-06');
    $this->createEvent('download', '2026-04-07');

    $from = new \DateTimeImmutable('2026-04-05 00:00:00');
    $to = new \DateTimeImmutable('2026-04-07 23:59:59');

    self::assertSame(3, $this->repository->countDistinctTypes($from, $to));
}

#[Test]
public function count_unique_actors_returns_distinct_fingerprints(): void
{
    $fp1 = str_repeat('a', 64);
    $fp2 = str_repeat('b', 64);

    $this->createEventWithFingerprint('click-cta', $fp1, '2026-04-05');
    $this->createEventWithFingerprint('signup', $fp1, '2026-04-06');
    $this->createEventWithFingerprint('click-cta', $fp2, '2026-04-06');

    $from = new \DateTimeImmutable('2026-04-05 00:00:00');
    $to = new \DateTimeImmutable('2026-04-07 23:59:59');

    self::assertSame(2, $this->repository->countUniqueActors($from, $to));
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php vendor/bin/phpunit tests/Unit/Repository/AnalyticsEventRepositoryTest.php --filter="count_distinct_types|count_unique_actors"`
Expected: FAIL — methods do not exist

- [ ] **Step 3: Write the implementation**

Add to `AnalyticsEventRepository.php`:

```php
public function countDistinctTypes(\DateTimeImmutable $from, \DateTimeImmutable $to): int
{
    return (int) $this->createQueryBuilder('e')
        ->select('COUNT(DISTINCT e.name)')
        ->where('e.recordedAt >= :from')
        ->andWhere('e.recordedAt <= :to')
        ->setParameter('from', $from)
        ->setParameter('to', $to)
        ->getQuery()
        ->getSingleScalarResult();
}

public function countUniqueActors(\DateTimeImmutable $from, \DateTimeImmutable $to): int
{
    return (int) $this->createQueryBuilder('e')
        ->select('COUNT(DISTINCT e.fingerprint)')
        ->where('e.recordedAt >= :from')
        ->andWhere('e.recordedAt <= :to')
        ->setParameter('from', $from)
        ->setParameter('to', $to)
        ->getQuery()
        ->getSingleScalarResult();
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php vendor/bin/phpunit tests/Unit/Repository/AnalyticsEventRepositoryTest.php --filter="count_distinct_types|count_unique_actors"`
Expected: OK (2 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Repository/AnalyticsEventRepository.php tests/Unit/Repository/AnalyticsEventRepositoryTest.php
git commit -m "feat: add countDistinctTypes and countUniqueActors to AnalyticsEventRepository"
```

---

### Task 7: AnalyticsEventRepository — countByDayForEvent

**Files:**
- Modify: `src/Repository/AnalyticsEventRepository.php`
- Modify: `tests/Unit/Repository/AnalyticsEventRepositoryTest.php`

- [ ] **Step 1: Write the failing test**

Add to `AnalyticsEventRepositoryTest.php`:

```php
#[Test]
public function count_by_day_for_event_filters_by_name(): void
{
    $this->createEvent('click-cta', '2026-04-05 10:00:00');
    $this->createEvent('click-cta', '2026-04-05 11:00:00');
    $this->createEvent('signup', '2026-04-05 12:00:00');
    $this->createEvent('click-cta', '2026-04-06 10:00:00');

    $from = new \DateTimeImmutable('2026-04-05 00:00:00');
    $to = new \DateTimeImmutable('2026-04-06 23:59:59');

    $result = $this->repository->countByDayForEvent('click-cta', $from, $to);

    self::assertCount(2, $result);
    self::assertSame('2026-04-05', $result[0]['date']);
    self::assertSame(2, (int) $result[0]['count']);
    self::assertSame('2026-04-06', $result[1]['date']);
    self::assertSame(1, (int) $result[1]['count']);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit tests/Unit/Repository/AnalyticsEventRepositoryTest.php --filter=count_by_day_for_event`
Expected: FAIL — method does not exist

- [ ] **Step 3: Write the implementation**

Add to `AnalyticsEventRepository.php`:

```php
/**
 * @return list<array{date: string, count: int}>
 */
public function countByDayForEvent(string $name, \DateTimeImmutable $from, \DateTimeImmutable $to): array
{
    $conn = $this->getEntityManager()->getConnection();

    $sql = <<<'SQL'
        SELECT
            TO_CHAR(recorded_at, 'YYYY-MM-DD') AS date,
            COUNT(*) AS count
        FROM ca_analytics_event
        WHERE recorded_at >= :from AND recorded_at <= :to AND name = :name
        GROUP BY date
        ORDER BY date ASC
    SQL;

    return $conn->executeQuery($sql, [
        'from' => $from->format('Y-m-d H:i:s'),
        'to' => $to->format('Y-m-d H:i:s'),
        'name' => $name,
    ])->fetchAllAssociative();
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php vendor/bin/phpunit tests/Unit/Repository/AnalyticsEventRepositoryTest.php --filter=count_by_day_for_event`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Repository/AnalyticsEventRepository.php tests/Unit/Repository/AnalyticsEventRepositoryTest.php
git commit -m "feat: add countByDayForEvent to AnalyticsEventRepository"
```

---

### Task 8: AnalyticsEventRepository — findValueBreakdown

**Files:**
- Modify: `src/Repository/AnalyticsEventRepository.php`
- Modify: `tests/Unit/Repository/AnalyticsEventRepositoryTest.php`

- [ ] **Step 1: Write the failing test**

Add to `AnalyticsEventRepositoryTest.php`:

```php
#[Test]
public function find_value_breakdown_groups_by_value(): void
{
    $this->createEvent('click-cta', '2026-04-05', 'hero-button');
    $this->createEvent('click-cta', '2026-04-05', 'hero-button');
    $this->createEvent('click-cta', '2026-04-06', 'footer-button');
    $this->createEvent('signup', '2026-04-06', 'organic');

    $from = new \DateTimeImmutable('2026-04-05 00:00:00');
    $to = new \DateTimeImmutable('2026-04-07 23:59:59');

    $result = $this->repository->findValueBreakdown('click-cta', $from, $to, 10);

    self::assertCount(2, $result);
    self::assertSame('hero-button', $result[0]['value']);
    self::assertSame(2, (int) $result[0]['count']);
    self::assertSame('footer-button', $result[1]['value']);
    self::assertSame(1, (int) $result[1]['count']);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit tests/Unit/Repository/AnalyticsEventRepositoryTest.php --filter=find_value_breakdown`
Expected: FAIL — method does not exist

- [ ] **Step 3: Write the implementation**

Add to `AnalyticsEventRepository.php`:

```php
/**
 * @return list<array{value: string, count: int}>
 */
public function findValueBreakdown(string $name, \DateTimeImmutable $from, \DateTimeImmutable $to, int $limit = 10): array
{
    return $this->createQueryBuilder('e')
        ->select('e.value AS value, COUNT(e.id) AS count')
        ->where('e.recordedAt >= :from')
        ->andWhere('e.recordedAt <= :to')
        ->andWhere('e.name = :name')
        ->andWhere('e.value IS NOT NULL')
        ->setParameter('from', $from)
        ->setParameter('to', $to)
        ->setParameter('name', $name)
        ->groupBy('e.value')
        ->orderBy('count', 'DESC')
        ->setMaxResults($limit)
        ->getQuery()
        ->getResult();
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php vendor/bin/phpunit tests/Unit/Repository/AnalyticsEventRepositoryTest.php --filter=find_value_breakdown`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Repository/AnalyticsEventRepository.php tests/Unit/Repository/AnalyticsEventRepositoryTest.php
git commit -m "feat: add findValueBreakdown to AnalyticsEventRepository"
```

---

### Task 9: AnalyticsEventRepository — findTopPagesForEvent

**Files:**
- Modify: `src/Repository/AnalyticsEventRepository.php`
- Modify: `tests/Unit/Repository/AnalyticsEventRepositoryTest.php`

- [ ] **Step 1: Write the failing test**

Add to `AnalyticsEventRepositoryTest.php`. First, add a helper that accepts a pageUrl (the existing `createEvent` hardcodes `/home`):

```php
private function createEventOnPage(string $name, string $pageUrl, string $date, ?string $value = null): void
{
    $event = AnalyticsEvent::create(
        fingerprint: str_repeat('a', 64),
        name: $name,
        value: $value,
        pageUrl: $pageUrl,
        recordedAt: new \DateTimeImmutable($date),
    );
    $this->em->persist($event);
    $this->em->flush();
}
```

Then the test:

```php
#[Test]
public function find_top_pages_for_event_groups_by_page(): void
{
    $this->createEventOnPage('click-cta', '/home', '2026-04-05');
    $this->createEventOnPage('click-cta', '/home', '2026-04-06');
    $this->createEventOnPage('click-cta', '/pricing', '2026-04-06');
    $this->createEventOnPage('signup', '/home', '2026-04-06');

    $from = new \DateTimeImmutable('2026-04-05 00:00:00');
    $to = new \DateTimeImmutable('2026-04-07 23:59:59');

    $result = $this->repository->findTopPagesForEvent('click-cta', $from, $to, 10);

    self::assertCount(2, $result);
    self::assertSame('/home', $result[0]['pageUrl']);
    self::assertSame(2, (int) $result[0]['count']);
    self::assertSame('/pricing', $result[1]['pageUrl']);
    self::assertSame(1, (int) $result[1]['count']);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit tests/Unit/Repository/AnalyticsEventRepositoryTest.php --filter=find_top_pages_for_event`
Expected: FAIL — method does not exist

- [ ] **Step 3: Write the implementation**

Add to `AnalyticsEventRepository.php`:

```php
/**
 * @return list<array{pageUrl: string, count: int}>
 */
public function findTopPagesForEvent(string $name, \DateTimeImmutable $from, \DateTimeImmutable $to, int $limit = 10): array
{
    return $this->createQueryBuilder('e')
        ->select('e.pageUrl, COUNT(e.id) AS count')
        ->where('e.recordedAt >= :from')
        ->andWhere('e.recordedAt <= :to')
        ->andWhere('e.name = :name')
        ->setParameter('from', $from)
        ->setParameter('to', $to)
        ->setParameter('name', $name)
        ->groupBy('e.pageUrl')
        ->orderBy('count', 'DESC')
        ->setMaxResults($limit)
        ->getQuery()
        ->getResult();
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php vendor/bin/phpunit tests/Unit/Repository/AnalyticsEventRepositoryTest.php --filter=find_top_pages_for_event`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Repository/AnalyticsEventRepository.php tests/Unit/Repository/AnalyticsEventRepositoryTest.php
git commit -m "feat: add findTopPagesForEvent to AnalyticsEventRepository"
```

---

### Task 10: Wire PeriodComparer in the bundle and refactor DashboardController

**Files:**
- Modify: `src/CookielessAnalyticsBundle.php`
- Modify: `src/Controller/DashboardController.php`
- Modify: `tests/Functional/Controller/DashboardControllerTest.php`

- [ ] **Step 1: Register PeriodComparer in the bundle**

Add to `CookielessAnalyticsBundle.php`, after the `DateRangeResolver` service registration:

```php
$services->set(\Jackfumanchu\CookielessAnalyticsBundle\Service\PeriodComparer::class);
```

Add the import at the top of the file:

```php
use Jackfumanchu\CookielessAnalyticsBundle\Service\PeriodComparer;
```

And update the registration to use the short name:

```php
$services->set(PeriodComparer::class);
```

- [ ] **Step 2: Refactor DashboardController::overview()**

Replace the comparison logic in `DashboardController.php`. Update the constructor to inject `PeriodComparer`:

```php
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
```

Add the import:

```php
use Jackfumanchu\CookielessAnalyticsBundle\Service\PeriodComparer;
```

Replace the `overview()` method body (after `$this->denyAccessUnlessGranted()`):

```php
$dateRange = $this->dateRangeResolver->resolve(
    $request->query->getString('from') ?: null,
    $request->query->getString('to') ?: null,
);

$pageViews = $this->periodComparer->compare($dateRange, $this->pageViewRepo->countByPeriod(...));
$uniqueVisitors = $this->periodComparer->compare($dateRange, $this->pageViewRepo->countUniqueVisitorsByPeriod(...));
$events = $this->periodComparer->compare($dateRange, $this->eventRepo->countByPeriod(...));

$pagesPerVisitor = $uniqueVisitors->current > 0
    ? round($pageViews->current / $uniqueVisitors->current, 1)
    : 0.0;
$prevPagesPerVisitor = $uniqueVisitors->previous > 0
    ? round($pageViews->previous / $uniqueVisitors->previous, 1)
    : 0.0;
$pagesPerVisitorComparison = \Jackfumanchu\CookielessAnalyticsBundle\Service\PeriodComparison::fromFloat($pagesPerVisitor, $prevPagesPerVisitor);

$html = $this->twig->render('@CookielessAnalytics/dashboard/_overview.html.twig', [
    'pageViews' => $pageViews,
    'uniqueVisitors' => $uniqueVisitors,
    'events' => $events,
    'pagesPerVisitor' => $pagesPerVisitorComparison,
]);

return new Response($html);
```

- [ ] **Step 3: Update the overview template to use PeriodComparison objects**

Replace `templates/dashboard/_overview.html.twig`:

```twig
<turbo-frame id="ca-overview">
    <div class="ca-kpi-grid">
        {% for card in [
            {label: 'Pages vues', comparison: pageViews},
            {label: 'Visiteurs uniques', comparison: uniqueVisitors},
            {label: 'Événements', comparison: events},
            {label: 'Pages/visiteur', comparison: pagesPerVisitor},
        ] %}
        <div class="ca-kpi-card">
            <span class="ca-kpi-card__label">{{ card.comparison.currentFloat == card.comparison.current ? card.comparison.current : card.comparison.currentFloat }}</span>
            <span class="ca-kpi-card__value">{{ card.label }}</span>
            {% if card.comparison.previous > 0 %}
                <span class="ca-kpi-card__change {{ card.comparison.changePercent >= 0 ? 'ca-kpi-card__change--up' : 'ca-kpi-card__change--down' }}">
                    {{ card.comparison.changePercent >= 0 ? '↑' : '↓' }} {{ card.comparison.changePercent|abs }}%
                </span>
            {% endif %}
        </div>
        {% endfor %}
    </div>
</turbo-frame>
```

- [ ] **Step 4: Run the existing functional tests to verify nothing is broken**

Run: `php vendor/bin/phpunit tests/Functional/Controller/DashboardControllerTest.php`
Expected: All tests pass (the tests check for `ca-overview` frame and content, which still works)

- [ ] **Step 5: Run full test suite**

Run: `php vendor/bin/phpunit`
Expected: All tests pass

- [ ] **Step 6: Commit**

```bash
git add src/CookielessAnalyticsBundle.php src/Controller/DashboardController.php templates/dashboard/_overview.html.twig
git commit -m "refactor: use PeriodComparer in DashboardController overview"
```

---

### Task 11: Run full test suite and verify

- [ ] **Step 1: Run all tests**

Run: `php vendor/bin/phpunit`
Expected: All tests pass — no regressions

- [ ] **Step 2: Verify method inventory**

Confirm the following methods now exist by checking the repository files:

**PageViewRepository** (existing + new):
- `countByPeriod($from, $to): int`
- `countUniqueVisitorsByPeriod($from, $to): int`
- `findTopPages($from, $to, $limit): array`
- `countByDay($from, $to): array`
- `findTopReferrers($from, $to, $limit): array` (new)
- `findTopReferrersForPage($pageUrl, $from, $to, $limit): array` (new)
- `countByDayForPage($pageUrl, $from, $to): array` (new)

**AnalyticsEventRepository** (existing + new):
- `countByPeriod($from, $to): int`
- `findTopEvents($from, $to, $limit): array`
- `countByDay($from, $to): array`
- `countDistinctTypes($from, $to): int` (new)
- `countUniqueActors($from, $to): int` (new)
- `countByDayForEvent($name, $from, $to): array` (new)
- `findValueBreakdown($name, $from, $to, $limit): array` (new)
- `findTopPagesForEvent($name, $from, $to, $limit): array` (new)

**Service layer** (new):
- `PeriodComparison` — DTO with `from()` and `fromFloat()` factories
- `PeriodComparer` — `compare()` and `compareFloat()` methods

- [ ] **Step 3: Commit any remaining changes**

If any cleanup was needed, commit it now.
