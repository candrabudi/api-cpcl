<?php

/** @var Laravel\Lumen\Routing\Router $router */
use Carbon\Carbon;

$router->get('/', function () {
    return response()->json([
        'project' => 'CPCL API',
        'type' => 'Government Assistance Management',
        'version' => 'v1',
        'status' => 'running',
        'timestamp' => Carbon::now()->toIso8601String(),
    ]);
});

$router->get('/docs', function () {
    return response()->file(base_path('public/swagger/index.html'));
});

$router->get('/docs/json', function () {
    if (env('APP_ENV') === 'production') {
        abort(404);
    }

    return app('App\Http\Controllers\Docs\ApiDocsController')->json();
});

$router->group(['prefix' => 'api'], function () use ($router) {
    $router->post('login', 'AuthController@login');

    $router->group(['middleware' => 'auth'], function () use ($router) {
        $router->post('logout', 'AuthController@logout');

        $router->group(['prefix' => 'profile'], function () use ($router) {
            $router->get('/', 'ProfileController@show');
            $router->put('/', 'ProfileController@update');
            $router->post('change-password', 'ProfileController@changePassword');
        });

        $router->group(['prefix' => 'group-fields'], function () use ($router) {
            $router->get('/', 'GroupFieldController@index');
            $router->get('{id}/show', 'GroupFieldController@show');
            $router->post('/', 'GroupFieldController@store');
            $router->put('{id}/update', 'GroupFieldController@update');
            $router->delete('{id}/delete', 'GroupFieldController@destroy');
        });

        $router->group(['prefix' => 'cpcl-documents'], function () use ($router) {
            $router->get('/', 'CpclDocumentController@index');
            $router->get('{id}', 'CpclDocumentController@show');
            $router->post('/', 'CpclDocumentController@store');
            $router->put('{id}', 'CpclDocumentController@update');
            $router->delete('{id}', 'CpclDocumentController@destroy');
            $router->put('{id}/status', 'CpclDocumentController@updateStatus');
        });

        $router->group(['prefix' => 'cpcl-applicants'], function () use ($router) {
            $router->get('/', 'CpclApplicantController@index');
            $router->get('{id}', 'CpclApplicantController@show');
            $router->post('/', 'CpclApplicantController@store');
            $router->put('{id}', 'CpclApplicantController@update');
            $router->delete('{id}', 'CpclApplicantController@destroy');
        });

        $router->group(['prefix' => 'cpcl-answers'], function () use ($router) {
            $router->post('/', 'CpclAnswerController@store');
            $router->get('{cpcl_document_id}', 'CpclAnswerController@show');
        });
    });
});
