<?php

declare(strict_types=1);

use Jackfumanchu\CookielessAnalyticsBundle\Routing\RouteLoader;
use Jackfumanchu\CookielessAnalyticsBundle\Tests\App\Controller\TrackingTestController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->import(RouteLoader::class, 'service');
    $routes->import(TrackingTestController::class, 'attribute');
};
