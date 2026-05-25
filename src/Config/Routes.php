<?php

declare(strict_types=1);
use CodeIgniter\Router\RouteCollection;

/**
 * Courier tracking routes.
 *
 * These routes are auto-discovered when the host app has 'routes' in its
 * Config/Modules::$aliases. If you need a different URL prefix or want full
 * control, leave module discovery disabled and add the group manually in
 * your app/Config/Routes.php instead.
 *
 * @var RouteCollection $routes
 */
$routes->group('courier', ['namespace' => 'Myth\Courier\Controllers'], static function ($routes): void {
    $routes->get('open/(:segment)', 'CourierController::open/$1');
    $routes->get('click/(:segment)', 'CourierController::click/$1');
    $routes->get('unsubscribe/(:segment)', 'CourierController::unsubscribe/$1');
    $routes->post('capture', 'CourierController::capture', ['filter' => 'courier_throttle']);
    $routes->post('webhook', 'CourierController::webhook');
});
