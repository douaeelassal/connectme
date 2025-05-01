<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

// Add custom middleware group without CSRF
$app->router->group([
    'prefix' => 'api',
    'middleware' => ['api'],
], function ($router) {
    require __DIR__.'/routes/api_custom.php';
});

// Continue with normal application bootstrapping
$app->run();
