<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\Unit\Service;

use Jackfumanchu\CookielessAnalyticsBundle\Service\UrlSanitizer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class UrlSanitizerTest extends TestCase
{
    private UrlSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new UrlSanitizer(['token', 'password', 'secret']);
    }

    #[Test]
    public function sanitize_strips_configured_params(): void
    {
        $result = $this->sanitizer->sanitize('/page?token=abc&category=music');

        self::assertSame('/page?category=music', $result);
    }

    #[Test]
    public function sanitize_strips_multiple_configured_params(): void
    {
        $result = $this->sanitizer->sanitize('/page?token=abc&password=xyz&category=music');

        self::assertSame('/page?category=music', $result);
    }

    #[Test]
    public function sanitize_returns_path_only_when_all_params_stripped(): void
    {
        $result = $this->sanitizer->sanitize('/page?token=abc&password=xyz');

        self::assertSame('/page', $result);
    }

    #[Test]
    public function sanitize_preserves_url_without_query_string(): void
    {
        $result = $this->sanitizer->sanitize('/page');

        self::assertSame('/page', $result);
    }

    #[Test]
    public function sanitize_preserves_safe_params(): void
    {
        $result = $this->sanitizer->sanitize('/search?q=shoes&page=2');

        self::assertSame('/search?q=shoes&page=2', $result);
    }

    #[Test]
    public function sanitize_handles_encoded_params(): void
    {
        $result = $this->sanitizer->sanitize('/page?token=abc%20def&category=music');

        self::assertSame('/page?category=music', $result);
    }

    #[Test]
    public function sanitize_handles_empty_strip_list(): void
    {
        $sanitizer = new UrlSanitizer([]);

        $result = $sanitizer->sanitize('/page?token=abc&category=music');

        self::assertSame('/page?token=abc&category=music', $result);
    }

    #[Test]
    public function sanitize_handles_full_url_with_host(): void
    {
        $result = $this->sanitizer->sanitize('https://example.com/page?token=abc&category=music');

        self::assertSame('https://example.com/page?category=music', $result);
    }
}
