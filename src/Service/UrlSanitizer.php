<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Service;

class UrlSanitizer
{
    /**
     * @param list<string> $stripParams
     */
    public function __construct(
        private readonly array $stripParams,
    ) {
    }

    public function sanitize(string $url): string
    {
        $parsed = parse_url($url);

        if (!isset($parsed['query']) || $this->stripParams === []) {
            return $url;
        }

        parse_str($parsed['query'], $queryParams);

        $filtered = array_diff_key($queryParams, array_flip($this->stripParams));

        $base = '';
        if (isset($parsed['scheme'], $parsed['host'])) {
            $base = $parsed['scheme'] . '://' . $parsed['host'];
        }

        $path = $parsed['path'] ?? '/';

        if ($filtered === []) {
            return $base . $path;
        }

        return $base . $path . '?' . http_build_query($filtered);
    }
}
