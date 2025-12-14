<?php

namespace App\Http\Controllers\Docs;

use Laravel\Lumen\Routing\Controller;

class ApiDocsController extends Controller
{
    public function ui()
    {
        return response()->file(
            base_path('public/swagger/index.html'),
            ['Content-Type' => 'text/html']
        );
    }

    public function json()
    {
        $path = resource_path('docs/api/openapi.yaml');

        if (!file_exists($path)) {
            return response('OpenAPI file not found', 404);
        }

        return response(
            file_get_contents($path),
            200,
            ['Content-Type' => 'application/yaml']
        );
    }
}
