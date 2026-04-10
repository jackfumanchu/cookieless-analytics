<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Service;

class DateRange
{
    public function __construct(
        public readonly \DateTimeImmutable $from,
        public readonly \DateTimeImmutable $to,
        public readonly \DateTimeImmutable $comparisonFrom,
        public readonly \DateTimeImmutable $comparisonTo,
    ) {
    }
}
