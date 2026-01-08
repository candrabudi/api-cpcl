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

        $router->group(['middleware' => ['auth', 'admin']], function () use ($router) {
            $router->group(['prefix' => 'admin-users'], function () use ($router) {
                $router->get('/', 'AdminUserController@index');
                $router->get('/{id}/show', 'AdminUserController@show');
                $router->post('/store', 'AdminUserController@store');
                $router->put('/{id}/update', 'AdminUserController@update');
                $router->delete('/{id}/delete', 'AdminUserController@destroy');

                $router->put('/{id}/lock', 'AdminUserController@lock');
                $router->put('/{id}/unlock', 'AdminUserController@unlock');
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

                $router->get('{cooperativeID}/procurements', 'CooperativeController@getCooperativeProcurements');
                $router->get('{cooperativeID}/procurements/{procurementID}/items', 'CooperativeController@getCooperativeProcurementItems');
            });

            $router->group(['prefix' => 'cpcl-answers'], function () use ($router) {
                $router->post('/store', 'CpclAnswerController@store');
                $router->get('{cpclDocumentId}/detail', 'CpclAnswerController@show');
            });

            $router->group(['prefix' => 'cpcl-documents/{cpclDocumentId}/fishing-vessels'], function () use ($router) {
                $router->get('/', 'CpclFishingVesselController@show');
                $router->post('/store', 'CpclFishingVesselController@store');
                $router->put('/update', 'CpclFishingVesselController@update');
            });

            $router->group(['prefix' => 'areas'], function () use ($router) {
                $router->get('/search', 'AreaSearchController@search');
            });

            $router->group(['prefix' => 'plenary-meetings'], function () use ($router) {
                $router->get('/', 'PlenaryMeetingController@index');
                $router->get('{id}/show', 'PlenaryMeetingController@show');
                $router->post('/store', 'PlenaryMeetingController@store');
                $router->put('{id}/update', 'PlenaryMeetingController@update');
                $router->delete('{id}/delete', 'PlenaryMeetingController@destroy');
                $router->get('/unpronounced-items',
                    'PlenaryMeetingController@listUnpronouncedPlenaryMeetings'
                );
            });

            $router->group(['prefix' => 'annual-budgets'], function () use ($router) {
                $router->get('/', 'AnnualBudgetController@index');
                $router->get('/{id}/show', 'AnnualBudgetController@show');
                $router->post('/', 'AnnualBudgetController@store');
                $router->put('/{id}/update', 'AnnualBudgetController@update');
                $router->delete('/{id}/delete', 'AnnualBudgetController@destroy');
            });

            $router->group(['prefix' => 'vendors'], function () use ($router) {
                $router->get('/', 'VendorController@index');
                $router->get('/{id}/show', 'VendorController@show');
                $router->post('/store', 'VendorController@store');
                $router->put('/{id}/update', 'VendorController@update');
                $router->delete('/{id}/delete', 'VendorController@destroy');
                $router->post('/{id}/restore', 'VendorController@restore');
                $router->get('/procurements/{vendorID}/show', 'VendorController@showWithProcurements');

                $router->get('/procurements/{vendorID}/{procurementID}/items', 'VendorController@getVendorProcurementItems');
            });

            $router->group(['prefix' => 'procurements'], function () use ($router) {
                $router->get('/', 'ProcurementController@index');
                $router->get('/{id}/show', 'ProcurementController@show');
                $router->post('/store', 'ProcurementController@store');
                $router->put('/{id}/update', 'ProcurementController@update');
                $router->delete('/{id}/delete', 'ProcurementController@destroy');
            });

            $router->group(['prefix' => 'items'], function () use ($router) {
                $router->get('/', 'ItemController@index');
                $router->get('/{id}/show', 'ItemController@show');
                $router->post('/store', 'ItemController@store');
                $router->put('/{id}/update', 'ItemController@update');
                $router->delete('/{id}/delete', 'ItemController@destroy');
                $router->get('types', 'ItemController@types');
            });

            $router->group(['prefix' => 'item-types'], function () use ($router) {
                $router->get('/', 'ItemTypeController@index');
                $router->get('/{id}/show', 'ItemTypeController@show');
                $router->post('/store', 'ItemTypeController@store');
                $router->put('/{id}/update', 'ItemTypeController@update');
                $router->delete('/{id}/delete', 'ItemTypeController@destroy');

                $router->get('/{id}/budgets', 'ItemTypeController@getBudgets');
                $router->post('/{id}/budgets', 'ItemTypeController@storeBudget');
                $router->put('/{id}/budgets/{budgetId}', 'ItemTypeController@updateBudget');
                $router->delete('/{id}/budgets/{budgetId}', 'ItemTypeController@destroyBudget');
            });

            $router->group(['prefix' => 'document-types'], function () use ($router) {
                $router->get('/', 'DocumentTypeController@index');
                $router->get('/{id}/show', 'DocumentTypeController@show');
                $router->post('/store', 'DocumentTypeController@store');
                $router->put('/{id}/update', 'DocumentTypeController@update');
                $router->delete('/{id}/delete', 'DocumentTypeController@destroy');
            });

            $router->group(['prefix' => 'shipments'], function () use ($router) {
                $router->get('/', 'ShipmentController@index');
                $router->get('/{id}/show', 'ShipmentController@show');
                $router->post('/store', 'ShipmentController@store');
                $router->put('/{id}/status', 'ShipmentController@updateStatus');
            });
        });

        $router->group(['prefix' => 'vendor'], function () use ($router) {
            $router->get('/procurements', 'ProcurementVendorController@index');
            $router->put('/procurements/{id}/delivery-status', 'ProcurementVendorController@updateDeliveryStatus');
            $router->put('/procurements/{id}/process-status', 'ProcurementVendorController@updateProcessStatus');
        });
    });
});
