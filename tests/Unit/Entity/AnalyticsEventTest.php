<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\Unit\Entity;

use Jackfumanchu\CookielessAnalyticsBundle\Entity\AnalyticsEvent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AnalyticsEventTest extends TestCase
{
    #[Test]
    public function create_returns_event_with_all_fields(): void
    {
        $recordedAt = new \DateTimeImmutable('2026-04-10 14:30:00');

        $event = AnalyticsEvent::create(
            fingerprint: 'abc123def456abc123def456abc123def456abc123def456abc123def456abcd',
            name: 'button_click',
            value: 'cta-signup',
            pageUrl: '/landing?ref=newsletter',
            recordedAt: $recordedAt,
        );

        self::assertNull($event->getId());
        self::assertSame('abc123def456abc123def456abc123def456abc123def456abc123def456abcd', $event->getFingerprint());
        self::assertSame('button_click', $event->getName());
        self::assertSame('cta-signup', $event->getValue());
        self::assertSame('/landing?ref=newsletter', $event->getPageUrl());
        self::assertSame($recordedAt, $event->getRecordedAt());
    }

    #[Test]
    public function create_accepts_null_value(): void
    {
        $event = AnalyticsEvent::create(
            fingerprint: 'abc123def456abc123def456abc123def456abc123def456abc123def456abcd',
            name: 'page_scroll',
            value: null,
            pageUrl: '/home',
            recordedAt: new \DateTimeImmutable(),
        );

        self::assertNull($event->getValue());
    }
}
