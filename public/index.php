<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Framework\JsonResponse;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use function FastRoute\simpleDispatcher;

$requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$requestUri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/';

if ($requestMethod === 'OPTIONS') {
    JsonResponse::empty(204);
}

$dispatcher = simpleDispatcher(function (RouteCollector $routes) {
    $routes->addRoute('GET', '/api/health', ['App\Controllers\HealthController', 'show']);

    $routes->addRoute('POST', '/api/auth/register', ['App\Controllers\AuthController', 'register']);
    $routes->addRoute('POST', '/api/auth/login', ['App\Controllers\AuthController', 'login']);
    $routes->addRoute('POST', '/api/auth/forgot-password', ['App\Controllers\AuthController', 'forgotPassword']);
    $routes->addRoute('POST', '/api/auth/reset-password', ['App\Controllers\AuthController', 'resetPassword']);
    $routes->addRoute('GET', '/api/auth/me', ['App\Controllers\AuthController', 'me']);

    $routes->addRoute('GET', '/api/profile', ['App\Controllers\ProfileController', 'show']);
    $routes->addRoute('PUT', '/api/profile', ['App\Controllers\ProfileController', 'update']);
    $routes->addRoute('PUT', '/api/profile/password', ['App\Controllers\ProfileController', 'updatePassword']);

    $routes->addRoute('GET', '/api/cars', ['App\Controllers\CarController', 'index']);
    $routes->addRoute('POST', '/api/cars', ['App\Controllers\CarController', 'store']);
    $routes->addRoute('GET', '/api/cars/{id:\d+}', ['App\Controllers\CarController', 'show']);
    $routes->addRoute('PUT', '/api/cars/{id:\d+}', ['App\Controllers\CarController', 'update']);
    $routes->addRoute('DELETE', '/api/cars/{id:\d+}', ['App\Controllers\CarController', 'destroy']);
    $routes->addRoute('GET', '/api/cars/{id:\d+}/availability', ['App\Controllers\CarController', 'availability']);

    $routes->addRoute('GET', '/api/bookings', ['App\Controllers\BookingController', 'index']);
    $routes->addRoute('POST', '/api/bookings', ['App\Controllers\BookingController', 'store']);
    $routes->addRoute('PUT', '/api/bookings/{id:\d+}', ['App\Controllers\BookingController', 'update']);

    $routes->addRoute('GET', '/api/users', ['App\Controllers\UserController', 'index']);
    $routes->addRoute('PUT', '/api/users/{id:\d+}', ['App\Controllers\UserController', 'update']);

    $routes->addRoute('GET', '/api/conversations', ['App\Controllers\ConversationController', 'index']);
    $routes->addRoute('POST', '/api/conversations', ['App\Controllers\ConversationController', 'store']);
    $routes->addRoute('GET', '/api/conversations/{id:\d+}/messages', ['App\Controllers\ConversationController', 'messages']);
    $routes->addRoute('POST', '/api/conversations/{id:\d+}/messages', ['App\Controllers\ConversationController', 'send']);
});

$routeInfo = $dispatcher->dispatch($requestMethod, $requestUri);

switch ($routeInfo[0]) {
    case Dispatcher::NOT_FOUND:
        JsonResponse::error('Route not found.', 404);
        break;

    case Dispatcher::METHOD_NOT_ALLOWED:
        JsonResponse::error('Method not allowed.', 405);
        break;

    case Dispatcher::FOUND:
        [$controllerClass, $methodName] = $routeInfo[1];
        $controller = new $controllerClass();
        $controller->$methodName($routeInfo[2]);
        break;
}
