<?php

/** @var \Laravel\Lumen\Routing\Router $router */
$router->get('/', fn () => $router->app->version());

// LEGACY
$router->get('/new/fires', '\App\Http\Controllers\LegacyController@newFires');
$router->get('/fires/data', '\App\Http\Controllers\LegacyController@firesData');

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
    $router->get('warnings/site', '\App\Http\Controllers\LegacyController@warningsSite');
    $router->get('madeira/warnings', '\App\Http\Controllers\LegacyController@warningsMadeira');
    $router->get('now', '\App\Http\Controllers\LegacyController@now');
    $router->get('now/data', '\App\Http\Controllers\LegacyController@nowData');
    $router->get('status', '\App\Http\Controllers\LegacyController@status');
    $router->get('active', '\App\Http\Controllers\LegacyController@active');
    $router->get('aerial', '\App\Http\Controllers\LegacyController@aerial');
    $router->get('stats', '\App\Http\Controllers\LegacyController@stats');
    $router->get('risk', '\App\Http\Controllers\LegacyController@risk');
    $router->get('risk-today', '\App\Http\Controllers\LegacyController@riskToday');
    $router->get('risk-tomorrow', '\App\Http\Controllers\LegacyController@riskTomorrow');
    $router->get('risk-after', '\App\Http\Controllers\LegacyController@riskAfter');
    $router->get('list', '\App\Http\Controllers\LegacyController@listConcelho');

    $router->group(['prefix' => 'stats'], function () use ($router) {
        $router->get('8hours', '\App\Http\Controllers\LegacyController@stats8hours');
        $router->get('8hours/yesterday', '\App\Http\Controllers\LegacyController@stats8hoursYesterday');
        $router->get('last-night', '\App\Http\Controllers\LegacyController@lastNight');
        $router->get('week', '\App\Http\Controllers\LegacyController@statsWeek');
        $router->get('today', '\App\Http\Controllers\LegacyController@statsToday');
        $router->get('yesterday', '\App\Http\Controllers\LegacyController@statsYesterday');
        $router->get('burn-area', '\App\Http\Controllers\LegacyController@burnedAreaLastDays');
        $router->get('motive', '\App\Http\Controllers\LegacyController@motivesThisMonths');

    });
});

$router->group(['prefix' => 'v2'], function () use ($router) {
    $router->group(['prefix' => 'other'], function () use ($router) {
        $router->get('mobile-contributors', '\App\Http\Controllers\OtherController@getMobileContributors');
    });

    $router->group(['prefix' => 'incidents'], function () use ($router) {
        $router->get('search', '\App\Http\Controllers\IncidentController@search');
        $router->get('active/kml', '\App\Http\Controllers\IncidentController@activeKML');
        $router->get('active', '\App\Http\Controllers\IncidentController@active');
        $router->get('{id}/kml', '\App\Http\Controllers\IncidentController@kml');
        $router->post('{id}/posit', '\App\Http\Controllers\IncidentController@addPosit');
        $router->post('{id}/kml', '\App\Http\Controllers\IncidentController@addKML');
    });

    $router->group(['prefix' => 'weather'], function () use ($router) {
        $router->get('thunders', '\App\Http\Controllers\WeatherController@thunders');
        $router->get('stations', '\App\Http\Controllers\WeatherController@stations');
        $router->get('ipma-services', '\App\Http\Controllers\WeatherController@ipmaServicesHTTPS');
    });

    $router->group(['prefix' => 'rcm'], function () use ($router) {
        $router->get('today', '\App\Http\Controllers\RCMController@today');
        $router->get('tomorrow', '\App\Http\Controllers\RCMController@tomorrow');
        $router->get('after', '\App\Http\Controllers\RCMController@after');
    });

    $router->group(['prefix' => 'planes'], function () use ($router) {
        $router->get('{icao}', '\App\Http\Controllers\PlanesController@icao');

    });

    $router->group(['prefix' => 'warnings'], function () use ($router) {
        $router->post('add', '\App\Http\Controllers\WarningsController@add');
    });

    $router->group(['prefix' => 'stats'], function () use ($router) {
        $router->group(['prefix' => 'today'], function () use ($router) {
            $router->get('ignitions-hourly', '\App\Http\Controllers\StatsController@ignitionsHourly');

        });

    });
});
