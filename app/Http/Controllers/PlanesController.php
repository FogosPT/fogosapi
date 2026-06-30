<?php

namespace App\Http\Controllers;

use App\Models\FlightPosition;
use App\Models\Planes;
use App\Models\TrackedAircraft;
use App\Resources\PlaneRecentResource;
use App\Resources\PlaneResource;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;

class PlanesController extends Controller
{
    private const RECENT_DEFAULT_HOURS = 6;
    private const RECENT_MAX_HOURS = 24;
    private const RECENT_CACHE_TTL_SECONDS = 60;
    private const ACTIVE_MINUTES = 10;

    public function index(): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'data' => PlaneResource::collection($this->trackedWithLatestPositions()),
        ]);
    }

    public function active(): JsonResponse
    {
        $tracked = $this->trackedWithLatestPositions()->filter(function ($aircraft) {
            $position = $aircraft->latest_position;

            return $position
                && $position->created
                && $position->created->diffInMinutes(Carbon::now()) <= self::ACTIVE_MINUTES;
        })->values();

        return new JsonResponse([
            'success' => true,
            'data' => PlaneResource::collection($tracked),
        ]);
    }

    public function recent(Request $request): JsonResponse
    {
        $hours = (int) $request->query('hours', self::RECENT_DEFAULT_HOURS);
        if ($hours < 1) {
            $hours = 1;
        }
        if ($hours > self::RECENT_MAX_HOURS) {
            $hours = self::RECENT_MAX_HOURS;
        }

        $cacheKey = "v2.planes.recent.{$hours}";

        $data = Cache::remember($cacheKey, self::RECENT_CACHE_TTL_SECONDS, function () use ($hours) {
            $tracked = TrackedAircraft::where('active', true)->get();
            if ($tracked->isEmpty()) {
                return [];
            }

            $icaos = $tracked->pluck('icao')->filter()->values()->all();
            $since = Carbon::now()->subHours($hours);

            $positions = FlightPosition::whereIn('icao', $icaos)
                ->where('created', '>=', $since)
                ->orderBy('created', 'asc')
                ->get()
                ->groupBy('icao');

            return $tracked->map(function ($aircraft) use ($positions) {
                $aircraft->setRelation('positions', $positions->get($aircraft->icao, collect()));

                return $aircraft;
            })->all();
        });

        return new JsonResponse([
            'success' => true,
            'data' => PlaneRecentResource::collection(collect($data)),
        ]);
    }

    public function track($icao): JsonResponse
    {
        $icao = strtolower((string) $icao);

        $positions = FlightPosition::where('icao', $icao)
            ->orderBy('created', 'desc')
            ->limit(20)
            ->get();

        return new JsonResponse([
            'success' => true,
            'data' => $positions,
        ]);
    }

    public function icao($icao): JsonResponse
    {
        $plane = Planes::where('icao', $icao)
            ->where('lat', '<>', '')
            ->orderBy('created', 'desc')
            ->limit(20)
            ->get();

        return new JsonResponse([
            'success' => true,
            'data' => $plane,
        ]);
    }

    private function trackedWithLatestPositions()
    {
        $tracked = TrackedAircraft::all();

        return $tracked->map(function ($aircraft) {
            $position = FlightPosition::where('icao', $aircraft->icao)
                ->orderBy('created', 'desc')
                ->first();
            $aircraft->setAttribute('latest_position', $position);

            return $aircraft;
        });
    }
}
