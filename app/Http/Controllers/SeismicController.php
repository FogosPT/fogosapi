<?php

namespace App\Http\Controllers;

use App\Models\Earthquake;
use App\Resources\EarthquakeResource;
use App\Support\SeismicFeedHealth;
use App\Support\SeismicRegion;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

/**
 * Earthquakes for sismos.pt (see App\Support\EarthquakeMerger for how the
 * sources are combined).
 */
class SeismicController extends Controller
{
    private const EARTH_RADIUS_KM = 6378.1;
    private const SITEMAP_PAGE_SIZE = 5000;

    public function events(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'region' => 'nullable|in:'.implode(',', SeismicRegion::ALL),
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'before' => ['nullable', 'regex:/^[0-9T:.\-+Z]+(_[a-f0-9]{24})?$/'],
            'minmag' => 'nullable|numeric',
            'maxmag' => 'nullable|numeric',
            'felt' => 'nullable|in:0,1,true,false',
            'bbox' => ['nullable', 'regex:/^-?\d+(\.\d+)?(,-?\d+(\.\d+)?){3}$/'],
            'limit' => 'nullable|integer|min:1|max:2000',
        ]);

        if ($validator->fails()) {
            return new JsonResponse(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $params = $validator->validated();
        $cacheKey = 'seismic:events:'.md5(json_encode($params));

        $payload = Cache::remember($cacheKey, 60, function () use ($params) {
            $limit = (int) ($params['limit'] ?? 500);
            $query = $this->filtered($params)->orderBy('time', 'desc')->orderBy('_id', 'desc')->limit($limit + 1);
            $rows = $query->get();

            // Cursor = "{time}_{id}": events sharing an origin time are
            // ordered by _id so a page boundary between them loses nothing.
            $next = null;
            if ($rows->count() > $limit) {
                $rows = $rows->slice(0, $limit);
                $last = $rows->last();
                $next = $last->time->copy()->utc()->format('Y-m-d\TH:i:s.v\Z').'_'.$last->id;
            }

            return [
                'success' => true,
                'data' => EarthquakeResource::collection($rows->values())->resolve(),
                'next' => $next,
            ];
        });

        return new JsonResponse($payload);
    }

    public function latest(Request $request)
    {
        $region = $request->query('region');
        if ($region !== null && !in_array($region, SeismicRegion::ALL, true)) {
            return new JsonResponse(['success' => false, 'errors' => ['region' => ['invalid']]], 422);
        }

        $payload = Cache::remember('seismic:latest:'.($region ?? 'all'), 30, function () use ($region) {
            $base = fn () => Earthquake::when($region, fn ($q) => $q->where('region', $region));

            $last = $base()->orderBy('time', 'desc')->first();
            $recent = $base()->where('time', '>=', Carbon::now()->subDays(7))->orderBy('time', 'desc')->limit(200)->get();

            return [
                'success' => true,
                'primarySource' => SeismicFeedHealth::primary(),
                'last' => $last ? (new EarthquakeResource($last))->resolve() : null,
                'data' => EarthquakeResource::collection($recent)->resolve(),
            ];
        });

        return new JsonResponse($payload);
    }

    public function show(string $id)
    {
        if (!preg_match('/^[a-f0-9]{24}$/', $id)) {
            return new JsonResponse(['success' => false, 'error' => 'not_found'], 404);
        }

        $payload = Cache::remember('seismic:event:'.$id, 120, function () use ($id) {
            $quake = Earthquake::find($id);
            if (!$quake) {
                return null;
            }

            $data = (new EarthquakeResource($quake))->resolve();
            $data['sources'] = $quake->sources;
            $data['aftershocks'] = $this->aftershocks($quake);
            $data['nearbyHistory'] = $this->nearbyHistory($quake);

            return ['success' => true, 'data' => $data];
        });

        if ($payload === null) {
            Cache::forget('seismic:event:'.$id);

            return new JsonResponse(['success' => false, 'error' => 'not_found'], 404);
        }

        return new JsonResponse($payload);
    }

    public function sourcesHealth()
    {
        return new JsonResponse([
            'success' => true,
            'primary' => SeismicFeedHealth::primary(),
            'sources' => SeismicFeedHealth::status(),
        ]);
    }

    public function sitemap(Request $request)
    {
        $page = max(1, (int) $request->query('page', 1));
        $minMag = is_numeric($request->query('minmag')) ? (float) $request->query('minmag') : null;

        $payload = Cache::remember("seismic:sitemap:{$page}:{$minMag}", 3600, function () use ($page, $minMag) {
            $query = fn () => Earthquake::when($minMag !== null, fn ($q) => $q->where('magnitude', '>=', $minMag));
            $total = $query()->count();

            $rows = $query()->orderBy('time', 'desc')
                ->skip(($page - 1) * self::SITEMAP_PAGE_SIZE)
                ->limit(self::SITEMAP_PAGE_SIZE)
                ->get(['_id', 'slug', 'time', 'updated', 'magnitude', 'region']);

            return [
                'success' => true,
                'page' => $page,
                'pages' => (int) ceil($total / self::SITEMAP_PAGE_SIZE),
                'total' => $total,
                'data' => $rows->map(fn ($q) => [
                    'id' => $q->id,
                    'slug' => $q->slug,
                    'region' => $q->region,
                    'magnitude' => $q->magnitude,
                    'updated' => ($q->updated ?? $q->time)?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                ])->values(),
            ];
        });

        return new JsonResponse($payload);
    }

    private function filtered(array $params)
    {
        $from = isset($params['from']) ? Carbon::parse($params['from']) : Carbon::now()->subDays(30);
        $to = isset($params['to']) ? Carbon::parse($params['to']) : null;

        $query = Earthquake::where('time', '>=', $from);

        if ($to !== null) {
            $query->where('time', '<=', $to);
        }
        if (isset($params['before'])) {
            [$time, $id] = array_pad(explode('_', $params['before'], 2), 2, null);
            $before = new UTCDateTime(Carbon::parse($time));
            $query->whereRaw(['$or' => array_values(array_filter([
                ['time' => ['$lt' => $before]],
                $id !== null ? ['time' => $before, '_id' => ['$lt' => new ObjectId($id)]] : null,
            ]))]);
        }
        if (isset($params['region'])) {
            $query->where('region', $params['region']);
        }
        if (isset($params['minmag'])) {
            $query->where('magnitude', '>=', (float) $params['minmag']);
        }
        if (isset($params['maxmag'])) {
            $query->where('magnitude', '<=', (float) $params['maxmag']);
        }
        if (isset($params['felt'])) {
            $query->where('felt', filter_var($params['felt'], FILTER_VALIDATE_BOOLEAN));
        }
        if (isset($params['bbox'])) {
            [$minLon, $minLat, $maxLon, $maxLat] = array_map('floatval', explode(',', $params['bbox']));
            $query->whereBetween('lon', [$minLon, $maxLon])->whereBetween('lat', [$minLat, $maxLat]);
        }

        return $query;
    }

    /**
     * Gardner & Knopoff (1974) space/time windows: the usual definition of
     * which later events count as aftershocks of a mainshock.
     *
     * @return array{days: float, km: float}
     */
    public static function gardnerKnopoffWindow(float $magnitude): array
    {
        return [
            'days' => $magnitude >= 6.5 ? 10 ** (0.032 * $magnitude + 2.7389) : 10 ** (0.5409 * $magnitude - 0.547),
            'km' => 10 ** (0.1238 * $magnitude + 0.983),
        ];
    }

    private function aftershocks(Earthquake $quake): array
    {
        if ($quake->magnitude === null || $quake->magnitude < 2.5) {
            return ['window' => null, 'count' => 0, 'data' => []];
        }

        $window = self::gardnerKnopoffWindow($quake->magnitude);
        $query = $this->within($quake, $window['km'])
            ->where('time', '>', $quake->time)
            ->where('time', '<=', $quake->time->copy()->addSeconds((int) ($window['days'] * 86400)));

        $count = (clone $query)->count();
        $rows = $query->orderBy('time', 'asc')->limit(500)->get();

        return [
            'window' => $window,
            'count' => $count,
            'data' => EarthquakeResource::collection($rows)->resolve(),
        ];
    }

    private function nearbyHistory(Earthquake $quake): array
    {
        $rows = $this->within($quake, 50)
            ->where('time', '<', $quake->time)
            ->whereNotNull('magnitude')
            ->orderBy('magnitude', 'desc')
            ->limit(10)
            ->get();

        return EarthquakeResource::collection($rows)->resolve();
    }

    private function within(Earthquake $quake, float $km)
    {
        return Earthquake::where('_id', '!=', $quake->getKey())->whereRaw([
            'location' => ['$geoWithin' => ['$centerSphere' => [[$quake->lon, $quake->lat], $km / self::EARTH_RADIUS_KM]]],
        ]);
    }
}
