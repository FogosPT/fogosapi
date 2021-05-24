<?php

/** @var \Laravel\Lumen\Routing\Router $router */
use \App\Http\Controllers\LegacyController;
/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

if(env('APP_ENV') === 'production'){
    \Illuminate\Support\Facades\URL::forceScheme('https');
}
$router->get('/', function () use ($router) {
    return $router->app->version();
});

// LEGACY
$router->get('/new/fires', '\App\Http\Controllers\LegacyController@newFires');
$router->get('/teste', '\App\Http\Controllers\LegacyController@test');

$router->group(['prefix' => 'fires'], function () use ($router) {
    $router->get('/', '\App\Http\Controllers\LegacyController@fires');
    $router->get('/danger', '\App\Http\Controllers\LegacyController@firesDanger');
    $router->get('/status', '\App\Http\Controllers\LegacyController@firesStatus');
});

$router->group(['prefix' => 'madeira'], function () use ($router) {
    $router->get('/fires', '\App\Http\Controllers\LegacyController@firesMadeira');
    $router->get('/fires/status', '\App\Http\Controllers\LegacyController@firesStatusMadeira');
});



$router->group(['prefix' => 'v1'], function () use ($router) {
    $router->get('warnings', '\App\Http\Controllers\LegacyController@warnings');
    $router->get('madeira/warnings', '\App\Http\Controllers\LegacyController@warningsMadeira');
    $router->get('now', '\App\Http\Controllers\LegacyController@now');
    $router->get('now/data', '\App\Http\Controllers\LegacyController@nowData');
    $router->get('status', '\App\Http\Controllers\LegacyController@status');
    $router->get('active', '\App\Http\Controllers\LegacyController@active');
    $router->get('aerial', '\App\Http\Controllers\LegacyController@aerial');
    $router->get('stats', '\App\Http\Controllers\LegacyController@stats');
    $router->get('risk', '\App\Http\Controllers\LegacyController@risk');
    $router->get('list', '\App\Http\Controllers\LegacyController@listConcelho');

    $router->group(['prefix' => 'stats'], function () use ($router) {
        $router->get('8hours', '\App\Http\Controllers\LegacyController@stats8hours');
        $router->get('8hours/yesterday', '\App\Http\Controllers\LegacyController@stats8hoursYesterday');
        $router->get('last-night', '\App\Http\Controllers\LegacyController@lastNight');
        $router->get('week', '\App\Http\Controllers\LegacyController@statsWeek');

    });
});
