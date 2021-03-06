<?php
chdir(dirname(__FILE__).'/..');
require 'vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

session_start();

$router = new League\Route\RouteCollection;
$templates = new League\Plates\Engine(dirname(__FILE__).'/../views');

$router->addRoute('GET', '/', 'Controllers\\Main::index');
$router->addRoute('GET', '/docs', 'Controllers\\Main::docs');
$router->addRoute('GET', '/dashboard', 'Controllers\\Main::dashboard');
$router->addRoute('GET', '/import', 'Controllers\\Main::import');
$router->addRoute('POST', '/checkin/test.json', 'Controllers\\Main::test_post_checkin');
$router->addRoute('GET', '/checkin/preview.json', 'Controllers\\Main::preview_checkin');
$router->addRoute('POST', '/checkin/preview.json', 'Controllers\\Main::preview_checkin');
$router->addRoute('POST', '/checkin/import.json', 'Controllers\\Main::import_checkin');
$router->addRoute('POST', '/checkin/reset.json', 'Controllers\\Main::reset_checkin');
$router->addRoute('GET', '/checkin/recent.json', 'Controllers\\Main::get_recent_checkins');
$router->addRoute('POST', '/user/prefs.json', 'Controllers\\Main::save_user_preferences');
$router->addRoute('GET', '/user/syndication-targets.json', 'Controllers\\Main::get_syndication_targets');
$router->addRoute('POST', '/user/syndication-targets.json', 'Controllers\\Main::reload_syndication_targets');
$router->addRoute('POST', '/user/syndication-rules.json', 'Controllers\\Main::post_syndication_rules');

$router->addRoute('GET', '/checkin/{checkin}/{hash}', 'Controllers\\Foursquare::comment');

$router->addRoute('GET', '/user/{userid}/checkin/{checkin}/{type}/{hash}', 'Controllers\\FoursquarePermalink::response');
$router->addRoute('GET', '/user/{userid}/checkin/{checkin}', 'Controllers\\FoursquarePermalink::checkin');

$router->addRoute('GET', '/auth/signin', 'Controllers\\Auth::signin');
$router->addRoute('GET', '/auth/start', 'Controllers\\Auth::start');
$router->addRoute('GET', '/auth/signout', 'Controllers\\Auth::signout');
$router->addRoute('GET', '/auth/callback', 'Controllers\\Auth::callback');

$router->addRoute('GET', '/foursquare', 'Controllers\\Foursquare::index');
$router->addRoute('GET', '/foursquare/auth', 'Controllers\\Foursquare::auth');
$router->addRoute('GET', '/foursquare/disconnect', 'Controllers\\Foursquare::disconnect');
$router->addRoute('GET', '/foursquare/disconnect-other', 'Controllers\\Foursquare::disconnect_other');
$router->addRoute('GET', '/foursquare/callback', 'Controllers\\Foursquare::callback');
$router->addRoute('POST', '/foursquare/push', 'Controllers\\Foursquare::push');

$dispatcher = $router->getDispatcher();
$request = Request::createFromGlobals();

try {
  $response = $dispatcher->dispatch($request->getMethod(), $request->getPathInfo());
  $response->send();
} catch(League\Route\Http\Exception\NotFoundException $e) {
  $response = new Response;
  $response->setStatusCode(404);
  $response->setContent("Not Found\n");
  $response->send();
} catch(League\Route\Http\Exception\MethodNotAllowedException $e) {
  $response = new Response;
  $response->setStatusCode(405);
  $response->setContent("Method not allowed\n");
  $response->send();
}
