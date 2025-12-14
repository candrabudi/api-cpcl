<?php

/** @var Laravel\Lumen\Routing\Router $router */
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
            $router->get('{id}', 'GroupFieldController@show');
            $router->post('/', 'GroupFieldController@store');
            $router->put('{id}', 'GroupFieldController@update');
            $router->delete('{id}', 'GroupFieldController@destroy');
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
