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
