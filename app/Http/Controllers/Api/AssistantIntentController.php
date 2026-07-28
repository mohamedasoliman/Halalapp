<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AssistantIntentRequest;
use App\Services\GeminiIntentService;
use App\Services\GeminiNotConfiguredException;
use App\Services\GeminiUpstreamException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class AssistantIntentController extends Controller
{
    public function __invoke(
        AssistantIntentRequest $request,
        GeminiIntentService $service,
    ): JsonResponse {
        try {
            return response()->json(
                $service->interpret(
                    $request->string('query')->toString(),
                    $request->boolean('has_product_context'),
                    $request->string('assistant_context', 'general')->toString(),
                )
            );
        } catch (GeminiNotConfiguredException) {
            Log::error('Gemini intent proxy is not configured.');

            return response()->json([
                'message' => 'Assistant service is unavailable.',
            ], 503);
        } catch (GeminiUpstreamException $exception) {
            Log::warning('Gemini intent proxy failed.', [
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Assistant service is temporarily unavailable.',
            ], 502);
        }
    }
}
