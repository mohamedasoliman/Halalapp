<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use JsonException;

class GeminiIntentService
{
    private const ALLOWED_INTENTS = [
        'masjid',
        'restaurant',
        'product_alternative',
        'unsupported',
    ];

    private const PRAYERS = [
        'Fajr',
        'Zohar',
        'Asr',
        'Magrib',
        'Isha',
        'Jumma',
    ];

    /**
     * @return array{intent: string, prayer: string, food_query: string}
     */
    public function interpret(string $query, bool $hasProductContext): array
    {
        $apiKey = trim((string) config('gemini.api_key'));
        if ($apiKey === '') {
            throw new GeminiNotConfiguredException;
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withHeaders(['x-goog-api-key' => $apiKey])
                ->connectTimeout(max(1, (int) config('gemini.connect_timeout', 5)))
                ->timeout(max(1, (int) config('gemini.timeout', 12)))
                ->post((string) config('gemini.endpoint'), [
                    'model' => (string) config('gemini.model', 'gemini-3.5-flash-lite'),
                    'store' => false,
                    'input' => trim($query),
                    'system_instruction' => $this->systemInstruction($hasProductContext),
                    'response_format' => [
                        'type' => 'text',
                        'mime_type' => 'application/json',
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'intent' => [
                                    'type' => 'string',
                                    'enum' => self::ALLOWED_INTENTS,
                                ],
                                'prayer' => ['type' => 'string'],
                                'food_query' => ['type' => 'string'],
                            ],
                            'required' => ['intent', 'prayer', 'food_query'],
                        ],
                    ],
                ]);
        } catch (ConnectionException $exception) {
            throw new GeminiUpstreamException(
                'Unable to connect to Gemini.',
                previous: $exception,
            );
        }

        if (! $response->successful()) {
            throw new GeminiUpstreamException(
                'Gemini returned HTTP '.$response->status().'.'
            );
        }

        $output = $this->extractOutput($response->json());

        return $this->validateOutput($output, $hasProductContext);
    }

    private function systemInstruction(bool $hasProductContext): string
    {
        $context = $hasProductContext ? 'true' : 'false';

        return <<<PROMPT
Classify a request for the Halal Kiwi mobile app.
You only extract intent. Never recommend or name any place, business, Masjid, restaurant, or product.

Valid intents:
- masjid: finding a Masjid for a congregational prayer
- restaurant: finding food or a restaurant
- product_alternative: finding a halal alternative to the product currently open
- unsupported: anything else

Use only these prayer values: Fajr, Zohar, Asr, Magrib, Isha, Jumma, or an empty string.
For restaurant requests, food_query must contain only the requested food or cuisine.
Use product_alternative only when product context is available.
Product context available: {$context}.
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function extractOutput(array $response): array
    {
        $steps = $response['steps'] ?? null;
        if (! is_array($steps)) {
            throw new GeminiUpstreamException('Gemini response has no steps.');
        }

        foreach (array_reverse($steps) as $step) {
            if (! is_array($step) || ($step['type'] ?? null) !== 'model_output') {
                continue;
            }

            foreach (($step['content'] ?? []) as $content) {
                if (! is_array($content) || ($content['type'] ?? null) !== 'text') {
                    continue;
                }

                $text = $content['text'] ?? null;
                if (! is_string($text) || trim($text) === '') {
                    continue;
                }

                try {
                    $decoded = json_decode($text, true, flags: JSON_THROW_ON_ERROR);
                } catch (JsonException $exception) {
                    throw new GeminiUpstreamException(
                        'Gemini returned invalid JSON.',
                        previous: $exception,
                    );
                }

                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        throw new GeminiUpstreamException('Gemini response has no text output.');
    }

    /**
     * @param  array<string, mixed>  $output
     * @return array{intent: string, prayer: string, food_query: string}
     */
    private function validateOutput(array $output, bool $hasProductContext): array
    {
        $intent = is_string($output['intent'] ?? null)
            ? trim($output['intent'])
            : 'unsupported';
        if (! in_array($intent, self::ALLOWED_INTENTS, true)) {
            $intent = 'unsupported';
        }
        if ($intent === 'product_alternative' && ! $hasProductContext) {
            $intent = 'unsupported';
        }

        $prayer = is_string($output['prayer'] ?? null)
            ? trim($output['prayer'])
            : '';
        if (! in_array($prayer, self::PRAYERS, true)) {
            $prayer = '';
        }
        if ($intent !== 'masjid') {
            $prayer = '';
        }

        $foodQuery = is_string($output['food_query'] ?? null)
            ? trim($output['food_query'])
            : '';
        $foodQuery = preg_replace('/[^\pL\pN\s&\'-]+/u', '', $foodQuery) ?? '';
        $foodQuery = trim(preg_replace('/\s+/u', ' ', $foodQuery) ?? '');
        $foodQuery = mb_substr($foodQuery, 0, 80);
        if ($intent !== 'restaurant') {
            $foodQuery = '';
        }

        return [
            'intent' => $intent,
            'prayer' => $prayer,
            'food_query' => $foodQuery,
        ];
    }
}
