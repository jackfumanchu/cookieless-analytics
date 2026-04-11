<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\Unit\Twig;

use Jackfumanchu\CookielessAnalyticsBundle\Repository\PageViewRepository;
use Jackfumanchu\CookielessAnalyticsBundle\Twig\CookielessAnalyticsExtension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CookielessAnalyticsExtensionTest extends TestCase
{
    private function createExtension(string $collectUrl = '/ca'): CookielessAnalyticsExtension
    {
        $repo = $this->createStub(PageViewRepository::class);
        $repo->method('findEarliestViewedAt')->willReturn(null);

        return new CookielessAnalyticsExtension($collectUrl, $repo);
    }

    #[Test]
    public function get_functions_registers_cookieless_analytics_script(): void
    {
        $extension = $this->createExtension();

        $functions = $extension->getFunctions();

        self::assertCount(1, $functions);
        self::assertSame('cookieless_analytics_script', $functions[0]->getName());
    }

    #[Test]
    public function render_script_contains_correct_endpoint(): void
    {
        $extension = $this->createExtension();

        $script = $extension->renderScript();

        self::assertStringContainsString('/ca/collect', $script);
        self::assertStringContainsString('<script>', $script);
        self::assertStringContainsString('navigator.sendBeacon', $script);
    }

    #[Test]
    public function render_script_strips_trailing_slash_from_prefix(): void
    {
        $extension = $this->createExtension('/ca/');

        $script = $extension->renderScript();

        self::assertStringContainsString('/ca/collect', $script);
        self::assertStringNotContainsString('/ca//collect', $script);
    }

    #[Test]
    public function render_script_uses_blob_for_json_content_type(): void
    {
        $extension = $this->createExtension();

        $script = $extension->renderScript();

        self::assertStringContainsString("new Blob([d],{type:'application/json'})", $script);
    }

    #[Test]
    public function render_script_contains_click_listener(): void
    {
        $extension = $this->createExtension();

        $script = $extension->renderScript();

        self::assertStringContainsString("closest('[data-ca-event]')", $script);
        self::assertStringContainsString('click', $script);
    }

    #[Test]
    public function render_script_contains_event_endpoint(): void
    {
        $extension = $this->createExtension();

        $script = $extension->renderScript();

        self::assertStringContainsString('/ca/event', $script);
    }
}
