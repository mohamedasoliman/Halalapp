<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\AwqatMasjidNotFoundException;
use App\Exceptions\AwqatUpstreamException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MasjidTimingCorrectionRequest;
use App\Http\Requests\Api\MasjidTimingsBatchRequest;
use App\Http\Requests\Api\MasjidTimingsRequest;
use App\Models\MasjidModel\Masjid;
use App\Models\MasjidTimingCorrection;
use App\Services\AwqatPrayerTimeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MasjidTimingController extends Controller
{
    public function batch(
        MasjidTimingsBatchRequest $request,
        AwqatPrayerTimeService $service,
    ): JsonResponse {
        $requested = collect($request->validated('masjids'))
            ->unique(fn (array $item): string => $item['area_id'].'|'.$item['masjid_id'])
            ->values();
        $localMasjids = Masjid::query()
            ->whereIn('Website', $requested->pluck('masjid_id'))
            ->get()
            ->keyBy(fn (Masjid $masjid): string => (string) $masjid->Area_id.'|'.(string) $masjid->Website);

        $timings = [];
        $unavailable = [];
        foreach ($requested as $item) {
            $key = $item['area_id'].'|'.$item['masjid_id'];
            $masjid = $localMasjids->get($key);
            if ($masjid === null) {
                $unavailable[] = $item;

                continue;
            }

            try {
                $record = $service->current(
                    (string) $masjid->Area_id,
                    (string) $masjid->Website,
                );
                $timings[] = [
                    'area_id' => (string) $masjid->Area_id,
                    'masjid_id' => (string) $masjid->Website,
                    'times' => $service->publicTimes($record),
                ];
            } catch (AwqatMasjidNotFoundException|AwqatUpstreamException $exception) {
                $unavailable[] = $item;
                Log::warning('Awqat batch prayer-time read failed.', [
                    'area_id' => $item['area_id'],
                    'masjid_id' => $item['masjid_id'],
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return response()->json([
            'data' => $timings,
            'unavailable' => $unavailable,
        ]);
    }

    public function show(
        MasjidTimingsRequest $request,
        AwqatPrayerTimeService $service,
    ): JsonResponse {
        $masjid = $this->localMasjid(
            $request->string('area_id')->toString(),
            $request->string('masjid_id')->toString(),
        );

        if ($masjid === null) {
            return response()->json([
                'message' => 'This masjid is not available in Halal Kiwi.',
            ], 404);
        }

        try {
            $record = $service->current(
                (string) $masjid->Area_id,
                (string) $masjid->Website,
            );

            return response()->json([
                'data' => [
                    'masjid_id' => (string) $masjid->Website,
                    'masjid_name' => (string) $masjid->Masjid_name,
                    'times' => $service->publicTimes($record),
                ],
            ]);
        } catch (AwqatMasjidNotFoundException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (AwqatUpstreamException $exception) {
            Log::warning('Awqat prayer-time read failed.', [
                'masjid_id' => (string) $masjid->Website,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Live prayer times are temporarily unavailable.',
            ], 502);
        }
    }

    public function correct(
        MasjidTimingCorrectionRequest $request,
        AwqatPrayerTimeService $service,
    ): JsonResponse {
        $areaId = $request->string('area_id')->toString();
        $masjidId = $request->string('masjid_id')->toString();
        $masjid = $this->localMasjid($areaId, $masjidId);

        if ($masjid === null) {
            return response()->json([
                'message' => 'This masjid is not available in Halal Kiwi.',
            ], 404);
        }

        $submitted = collect($request->validated('changes'))
            ->map(fn (string $time): string => strtoupper(trim($time)))
            ->all();

        $audit = MasjidTimingCorrection::create([
            'masjid_id' => $masjidId,
            'area_id' => $areaId,
            'masjid_name' => (string) $masjid->Masjid_name,
            'status' => 'pending',
            'submitted_changes' => $submitted,
            'request_fingerprint' => $this->requestFingerprint($request),
            'install_fingerprint' => $this->installFingerprint($request),
        ]);

        try {
            $correctionCacheMaxAge = max(
                0,
                (int) config('awqat.correction_cache_max_age', 120),
            );
            $current = $service->current(
                $areaId,
                $masjidId,
                fresh: $correctionCacheMaxAge === 0,
                maxAgeSeconds: $correctionCacheMaxAge > 0
                    ? $correctionCacheMaxAge
                    : null,
            );
            $currentTimes = $service->publicTimes($current);
            $expected = $request->validated('current_times');

            $audit->update(['original_times' => $currentTimes]);

            foreach (array_keys($submitted) as $prayer) {
                $expectedTime = $service->normaliseTime($expected[$prayer] ?? null);
                if ($expectedTime !== ($currentTimes[$prayer] ?? null)) {
                    $audit->update([
                        'status' => 'conflict',
                        'verified_times' => $currentTimes,
                        'failure_reason' => 'The displayed schedule was stale.',
                    ]);

                    return response()->json([
                        'message' => 'These prayer times were updated recently. Refresh and try again.',
                        'data' => ['times' => $currentTimes],
                    ], 409);
                }
            }

            $effectiveChanges = collect($submitted)
                ->reject(
                    fn (string $time, string $prayer): bool => $time === ($currentTimes[$prayer] ?? null)
                )
                ->all();

            if ($effectiveChanges === []) {
                $audit->update([
                    'status' => 'rejected_no_change',
                    'verified_times' => $currentTimes,
                    'failure_reason' => 'No submitted value differed from the live schedule.',
                ]);

                return response()->json([
                    'message' => 'No prayer times were changed.',
                ], 422);
            }

            $published = $service->publishAcknowledged(
                $current,
                $effectiveChanges,
            );
            $publishedTimes = $service->publicTimes($published);

            $audit->update([
                'status' => 'published_acknowledged',
                'submitted_changes' => $effectiveChanges,
            ]);

            return response()->json([
                'message' => 'JazakAllah khair. The corrected jamaat times are now live.',
                'data' => [
                    'correction_id' => $audit->id,
                    'times' => $publishedTimes,
                ],
            ]);
        } catch (AwqatMasjidNotFoundException $exception) {
            $this->failAudit($audit, 'failed', $exception->getMessage());

            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (AwqatUpstreamException $exception) {
            $status = $exception->uncertain ? 'uncertain' : 'failed';
            $this->failAudit($audit, $status, $exception->getMessage());
            Log::warning('Awqat prayer-time correction failed.', [
                'correction_id' => $audit->id,
                'masjid_id' => $masjidId,
                'status' => $status,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => $exception->uncertain
                    ? 'The correction could not be confirmed. Refresh before trying again.'
                    : 'The correction could not be published right now. Please try again.',
            ], 502);
        }
    }

    private function localMasjid(string $areaId, string $masjidId): ?Masjid
    {
        return Masjid::query()
            ->where('Area_id', $areaId)
            ->where('Website', $masjidId)
            ->first();
    }

    private function requestFingerprint(Request $request): string
    {
        return hash_hmac(
            'sha256',
            (string) $request->ip().'|'.(string) $request->userAgent(),
            (string) config('app.key', 'halal-kiwi'),
        );
    }

    private function installFingerprint(Request $request): ?string
    {
        $installId = trim((string) $request->header('X-Install-ID', ''));
        if ($installId === '') {
            return null;
        }

        return hash_hmac(
            'sha256',
            $installId,
            (string) config('app.key', 'halal-kiwi'),
        );
    }

    private function failAudit(
        MasjidTimingCorrection $audit,
        string $status,
        string $reason,
    ): void {
        $audit->update([
            'status' => $status,
            'failure_reason' => mb_substr($reason, 0, 1000),
        ]);
    }
}
