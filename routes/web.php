<?php

/** @var Laravel\Lumen\Routing\Router $router */
$router->group(['prefix' => 'api'], function () use ($router) {
    $router->post('/login', 'AuthController@login');

    $router->group(['middleware' => 'auth'], function () use ($router) {
        $router->post('/logout', 'AuthController@logout');

        $router->get('/profile', 'ProfileController@show');
        $router->put('/profile', 'ProfileController@update');
        $router->post('/change-password', 'ProfileController@changePassword');

        $router->get('/group-fields', 'GroupFieldController@index');
        $router->get('/group-fields/{id}', 'GroupFieldController@show');
        $router->post('/group-fields', 'GroupFieldController@store');
        $router->put('/group-fields/{id}', 'GroupFieldController@update');
        $router->delete('/group-fields/{id}', 'GroupFieldController@destroy');

        $router->get('/cpcl-documents', 'CpclDocumentController@index');
        $router->get('/cpcl-documents/{id}', 'CpclDocumentController@show');
        $router->post('/cpcl-documents', 'CpclDocumentController@store');
        $router->put('/cpcl-documents/{id}', 'CpclDocumentController@update');
        $router->delete('/cpcl-documents/{id}', 'CpclDocumentController@destroy');
        $router->put('/cpcl-documents/{id}/status', 'CpclDocumentController@updateStatus');

        $router->group(['prefix' => 'cpcl-applicants'], function () use ($router) {
            $router->get('/', ['uses' => 'CpclApplicantController@index']);
            $router->get('{id}', ['uses' => 'CpclApplicantController@show']);
            $router->post('/', ['uses' => 'CpclApplicantController@store']);
            $router->put('{id}', ['uses' => 'CpclApplicantController@update']);
            $router->delete('{id}', ['uses' => 'CpclApplicantController@destroy']);
        });

        $router->group(['prefix' => 'cpcl-answers'], function () use ($router) {
            $router->post('/', [
                'uses' => 'CpclAnswerController@store',
            ]);

            $router->get('/{cpclDocumentId}', [
                'uses' => 'CpclAnswerController@show',
            ]);
        });
    });
});
