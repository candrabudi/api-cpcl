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

$router->group(['prefix' => 'api'], function () use ($router) {
    $router->post('/auth/login', 'AuthController@login');
    $router->post('/auth/verify-otp', 'AuthController@verifyOtp');
    $router->post('/auth/refresh', 'AuthController@refresh');

    $router->group(['middleware' => 'auth'], function () use ($router) {
        $router->post('/auth/logout', 'AuthController@logout');

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
            $router->get('{id}/show', 'CpclDocumentController@show');
            $router->post('/store', 'CpclDocumentController@store');
            $router->put('{id}/update', 'CpclDocumentController@update');
            $router->delete('{id}/delete', 'CpclDocumentController@destroy');
            $router->put('{id}/status', 'CpclDocumentController@updateStatus');
        });

        $router->group(['prefix' => 'cpcl-applicants'], function () use ($router) {
            $router->get('/', 'CpclApplicantController@index');
            $router->get('{id}/show', 'CpclApplicantController@show');
            $router->post('/store', 'CpclApplicantController@store');
            $router->put('{id}/update', 'CpclApplicantController@update');
            $router->delete('{id}/delete', 'CpclApplicantController@destroy');
        });

        $router->group(['prefix' => 'cooperatives'], function () use ($router) {
            $router->get('/', 'CooperativeController@index');
            $router->get('{id}/show', 'CooperativeController@show');
            $router->post('/store', 'CooperativeController@store');
            $router->put('{id}/update', 'CooperativeController@update');
            $router->delete('{id}/delete', 'CooperativeController@destroy');
        });

        $router->group(['prefix' => 'cpcl-answers'], function () use ($router) {
            $router->post('/store', 'CpclAnswerController@store');
            $router->get('{cpcl_document_id}/detail', 'CpclAnswerController@show');
        });

        $router->group(['prefix' => 'cpcl-documents/{cpcl_document_id}/fishing-vessels'], function () use ($router) {
            $router->get('/', 'CpclFishingVesselController@show');
            $router->post('/', 'CpclFishingVesselController@store');
            $router->put('/', 'CpclFishingVesselController@update');
        });

        $router->group(['prefix' => '/areas'], function () use ($router) {
            $router->get('/search', 'AreaSearchController@search');
        });
    });
});
