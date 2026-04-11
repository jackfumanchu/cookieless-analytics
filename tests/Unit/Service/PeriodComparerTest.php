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

    #[Test]
    public function compare_pages_per_visitor_computes_ratio(): void
    {
        $pageViews = \Jackfumanchu\CookielessAnalyticsBundle\Service\PeriodComparison::from(390, 370);
        $visitors = \Jackfumanchu\CookielessAnalyticsBundle\Service\PeriodComparison::from(100, 100);

        $result = $this->comparer->comparePagesPerVisitor($pageViews, $visitors);

        self::assertSame(3.9, $result->currentFloat);
        self::assertSame(3.7, $result->previousFloat);
        self::assertSame(5.4, $result->changePercent);
    }

    #[Test]
    public function compare_pages_per_visitor_handles_zero_visitors(): void
    {
        $pageViews = \Jackfumanchu\CookielessAnalyticsBundle\Service\PeriodComparison::from(100, 50);
        $visitors = \Jackfumanchu\CookielessAnalyticsBundle\Service\PeriodComparison::from(0, 0);

        $result = $this->comparer->comparePagesPerVisitor($pageViews, $visitors);

        self::assertSame(0.0, $result->currentFloat);
        self::assertSame(0.0, $result->previousFloat);
        self::assertSame(0.0, $result->changePercent);
    }

    #[Test]
    public function compare_pages_per_visitor_rounds_to_one_decimal(): void
    {
        // 10/3 = 3.333... → round(x, 1) = 3.3, round(x, 2) = 3.33
        $pageViews = \Jackfumanchu\CookielessAnalyticsBundle\Service\PeriodComparison::from(10, 10);
        $visitors = \Jackfumanchu\CookielessAnalyticsBundle\Service\PeriodComparison::from(3, 3);

        $result = $this->comparer->comparePagesPerVisitor($pageViews, $visitors);

        self::assertSame(3.3, $result->currentFloat);
        self::assertSame(3.3, $result->previousFloat);
    }
}
