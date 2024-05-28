<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('/', fn () => $router->app->version());

// LEGACY
Route::get('/new/fires', '\App\Http\Controllers\LegacyController@newFires');
Route::get('/fires/data', '\App\Http\Controllers\LegacyController@firesData');

Route::group(['prefix' => 'fires'], function () {
    Route::get('/', '\App\Http\Controllers\LegacyController@fires');
    Route::get('/danger', '\App\Http\Controllers\LegacyController@firesDanger');
    Route::get('/status', '\App\Http\Controllers\LegacyController@firesStatus');
});

Route::group(['prefix' => 'madeira'], function () {
    Route::get('/fires', '\App\Http\Controllers\LegacyController@firesMadeira');
    Route::get('/fires/status', '\App\Http\Controllers\LegacyController@firesStatusMadeira');
});

Route::group(['prefix' => 'v1'], function () {
    Route::get('warnings', '\App\Http\Controllers\LegacyController@warnings');
    Route::get('warnings/site', '\App\Http\Controllers\LegacyController@warningsSite');
    Route::get('madeira/warnings', '\App\Http\Controllers\LegacyController@warningsMadeira');
    Route::get('now', '\App\Http\Controllers\LegacyController@now');
    Route::get('now/data', '\App\Http\Controllers\LegacyController@nowData');
    Route::get('status', '\App\Http\Controllers\LegacyController@status');
    Route::get('active', '\App\Http\Controllers\LegacyController@active');
    Route::get('aerial', '\App\Http\Controllers\LegacyController@aerial');
    Route::get('stats', '\App\Http\Controllers\LegacyController@stats');
    Route::get('risk', '\App\Http\Controllers\LegacyController@risk');
    Route::get('risk-today', '\App\Http\Controllers\LegacyController@riskToday');
    Route::get('risk-tomorrow', '\App\Http\Controllers\LegacyController@riskTomorrow');
    Route::get('risk-after', '\App\Http\Controllers\LegacyController@riskAfter');
    Route::get('list', '\App\Http\Controllers\LegacyController@listConcelho');

    Route::group(['prefix' => 'stats'], function () {
        Route::get('8hours', '\App\Http\Controllers\LegacyController@stats8hours');
        Route::get('8hours/yesterday', '\App\Http\Controllers\LegacyController@stats8hoursYesterday');
        Route::get('last-night', '\App\Http\Controllers\LegacyController@lastNight');
        Route::get('week', '\App\Http\Controllers\LegacyController@statsWeek');
        Route::get('today', '\App\Http\Controllers\LegacyController@statsToday');
        Route::get('yesterday', '\App\Http\Controllers\LegacyController@statsYesterday');
        Route::get('burn-area', '\App\Http\Controllers\LegacyController@burnedAreaLastDays');
        Route::get('motive', '\App\Http\Controllers\LegacyController@motivesThisMonths');

    });
});

Route::group(['prefix' => 'v2'], function () {
    Route::group(['prefix' => 'other'], function () {
        Route::get('mobile-contributors', '\App\Http\Controllers\OtherController@getMobileContributors');
    });

    Route::group(['prefix' => 'incidents'], function () {
        Route::get('search', '\App\Http\Controllers\IncidentController@search');
        Route::get('active/kml', '\App\Http\Controllers\IncidentController@activeKML');
        Route::get('active', '\App\Http\Controllers\IncidentController@active');
        Route::get('1000ha-burned', '\App\Http\Controllers\IncidentController@burnMoreThan1000');
        Route::get('{id}/kml', '\App\Http\Controllers\IncidentController@kml');
        Route::get('{id}/kmlVost', '\App\Http\Controllers\IncidentController@kmlVost');

        Route::post('{id}/posit', '\App\Http\Controllers\IncidentController@addPosit');
        Route::post('{id}/kml', '\App\Http\Controllers\IncidentController@addKML');
    });

    Route::group(['prefix' => 'weather'], function () {
        Route::get('thunders', '\App\Http\Controllers\WeatherController@thunders');
        Route::get('stations', '\App\Http\Controllers\WeatherController@stations');
        Route::get('daily', '\App\Http\Controllers\WeatherController@daily');
        Route::get('ipma-services', '\App\Http\Controllers\WeatherController@ipmaServicesHTTPS');
    });

    Route::group(['prefix' => 'rcm'], function () {
        Route::get('today', '\App\Http\Controllers\RCMController@today');
        Route::get('tomorrow', '\App\Http\Controllers\RCMController@tomorrow');
        Route::get('after', '\App\Http\Controllers\RCMController@after');
        Route::get('parish', '\App\Http\Controllers\RCMController@parish');
    });

    Route::group(['prefix' => 'planes'], function () {
        Route::get('{icao}', '\App\Http\Controllers\PlanesController@icao');

    });

    Route::group(['prefix' => 'warnings'], function () {
        Route::post('add', '\App\Http\Controllers\WarningsController@add');
    });

    Route::group(['prefix' => 'stats'], function () {
        Route::group(['prefix' => 'today'], function () {
            Route::get('ignitions-hourly', '\App\Http\Controllers\StatsController@ignitionsHourly');

        });

    });
});
