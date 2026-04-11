<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\Unit\Service;

use Jackfumanchu\CookielessAnalyticsBundle\Service\TrendsStatsCalculator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TrendsStatsCalculatorTest extends TestCase
{
    private TrendsStatsCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new TrendsStatsCalculator();
    }

    #[Test]
    public function compute_returns_stats_from_daily_data(): void
    {
        $daily = [
            ['date' => '2026-04-06', 'count' => '100', 'unique' => '50'],  // Monday
            ['date' => '2026-04-07', 'count' => '200', 'unique' => '80'],  // Tuesday
            ['date' => '2026-04-08', 'count' => '150', 'unique' => '60'],  // Wednesday
            ['date' => '2026-04-09', 'count' => '180', 'unique' => '70'],  // Thursday
            ['date' => '2026-04-10', 'count' => '160', 'unique' => '65'],  // Friday
            ['date' => '2026-04-11', 'count' => '80', 'unique' => '30'],   // Saturday
            ['date' => '2026-04-12', 'count' => '60', 'unique' => '25'],   // Sunday
        ];

        $stats = $this->calculator->compute($daily);

        self::assertSame(['date' => '2026-04-07', 'views' => 200], $stats['peakDay']);
        self::assertSame(['date' => '2026-04-12', 'views' => 60], $stats['lowDay']);
        self::assertSame(133, $stats['dailyAvgViews']); // 930/7
        self::assertSame(54, $stats['dailyAvgVisitors']); // 380/7
        self::assertSame(158, $stats['weekdayAvg']); // 790/5
        self::assertSame(70, $stats['weekendAvg']); // 140/2
    }

    #[Test]
    public function compute_returns_defaults_for_empty_data(): void
    {
        $stats = $this->calculator->compute([]);

        self::assertNull($stats['peakDay']);
        self::assertNull($stats['lowDay']);
        self::assertSame(0, $stats['dailyAvgViews']);
        self::assertSame(0, $stats['dailyAvgVisitors']);
        self::assertSame(0, $stats['weekdayAvg']);
        self::assertSame(0, $stats['weekendAvg']);
    }

    #[Test]
    public function compute_handles_single_day(): void
    {
        $daily = [
            ['date' => '2026-04-07', 'count' => '42', 'unique' => '15'], // Tuesday
        ];

        $stats = $this->calculator->compute($daily);

        self::assertSame(['date' => '2026-04-07', 'views' => 42], $stats['peakDay']);
        self::assertSame(['date' => '2026-04-07', 'views' => 42], $stats['lowDay']);
        self::assertSame(42, $stats['dailyAvgViews']);
        self::assertSame(15, $stats['dailyAvgVisitors']);
        self::assertSame(42, $stats['weekdayAvg']);
        self::assertSame(0, $stats['weekendAvg']);
    }

    #[Test]
    public function compute_handles_weekend_only(): void
    {
        $daily = [
            ['date' => '2026-04-11', 'count' => '80', 'unique' => '30'], // Saturday
            ['date' => '2026-04-12', 'count' => '60', 'unique' => '25'], // Sunday
        ];

        $stats = $this->calculator->compute($daily);

        self::assertSame(0, $stats['weekdayAvg']);
        self::assertSame(70, $stats['weekendAvg']);
    }
}
