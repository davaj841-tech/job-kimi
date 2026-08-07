<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class ApiDocumentationController extends Controller
{
    public function ui(): View
    {
        return view('api-documentation', [
            'specUrl' => url('/api/documentation.json'),
        ]);
    }

    public function spec(): JsonResponse
    {
        $path = base_path('docs/openapi.json');
        if (! is_file($path)) {
            return response()->json(['error' => 'OpenAPI spec missing'], 404);
        }

        return response()->json(json_decode(file_get_contents($path), true));
    }
}
