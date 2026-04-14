<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\App\Controller;

use Jackfumanchu\CookielessAnalyticsBundle\Twig\CookielessAnalyticsExtension;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Serves minimal HTML pages with the tracking script for browser tests.
 * The links use pushState to simulate SPA navigation without full reloads.
 */
class TrackingTestController
{
    public function __construct(
        private readonly CookielessAnalyticsExtension $analyticsExtension,
    ) {
    }

    #[Route(path: '/test/{page}', name: 'tracking_test_page', defaults: ['page' => 'home'])]
    public function __invoke(string $page): Response
    {
        $script = $this->analyticsExtension->renderScript();

        $html = <<<HTML
        <!DOCTYPE html>
        <html>
        <head><title>Test - {$page}</title>{$script}</head>
        <body>
            <h1 id="page-title">{$page}</h1>
            <a href="/test/about" id="link-about" onclick="event.preventDefault();history.pushState({},'','/test/about');document.getElementById('page-title').textContent='about';">About</a>
            <a href="/test/contact" id="link-contact" onclick="event.preventDefault();history.pushState({},'','/test/contact');document.getElementById('page-title').textContent='contact';">Contact</a>
        </body>
        </html>
        HTML;

        return new Response($html);
    }
}
