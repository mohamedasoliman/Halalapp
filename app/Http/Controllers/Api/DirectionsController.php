<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DirectionsRequest;
use App\Services\MapboxDirectionsService;
use Illuminate\Http\JsonResponse;

class DirectionsController extends Controller
{
    public function __invoke(
        DirectionsRequest $request,
        MapboxDirectionsService $service,
    ): JsonResponse {
        $route = $service->route(
            (float) $request->input('from_lat'),
            (float) $request->input('from_lon'),
            (float) $request->input('to_lat'),
            (float) $request->input('to_lon'),
        );

        return response()->json($route ?? ['available' => false]);
    }
}
