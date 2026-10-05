<?php

namespace App\Http\Controllers\Integration;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class InfoController extends Controller
{
    /** GET /v1/info */
    public function show(): JsonResponse
    {
        return new JsonResponse(['applicationVersion' => config('unifi_mock.app_version')]);
    }
}
