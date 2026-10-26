<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\IncidentHistory;
use App\Resources\IncidentResource;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use MongoDB\BSON\UTCDateTime;
use voku\helper\UTF8;

class StatsController extends Controller
{
    /**
     * Means tracked in incident history: response key => history field.
     */
    private const MEANS = [
        'man' => 'man',
        'terrain' => 'terrain',
        'aerial' => 'aerial',
    ];

    private const MEANS_PEAK_MAX_DAYS = 366;
    private const MEANS_PEAK_CACHE_SECONDS = 3600;

    public function ignitionsHourly(Request $request)
    {
        $day = $request->get('day');

        if($day){
            $day = new Carbon($day);
        } else {
            $day = Carbon::today();
        }


        $incidents = Incident::whereBetween(
                        'dateTime',
                        [
                            Carbon::parse($day->startOfDay()),
                            Carbon::parse($day->endOfDay())
                        ]
                        )
                        ->where('isFire', true)
                        ->get();

        $hours = [];

        foreach ($incidents as $i){
            $k = $i->dateTime->startOfHour()->hour . 'H' . ' - ' . ($i->dateTime->startOfHour()->hour + 1) . 'H';
            if(isset($hours[$k])){
                $hours[$k]++;
            } else {
                $hours[$k] = 1;
            }
        }
        ksort($hours);

        return new JsonResponse([
            'success' => true,
            'data' => $hours,
        ]);
    }

    /**
     * Peak number of operatives, ground and aerial means reached by a single
     * incident, for incidents that started between `after` and `before`
     * (inclusive days), optionally filtered by district/concelho/freguesia.
     * Each peak comes from the incident history snapshots, with the time it
     * was recorded and the incident (#30).
     */
    public function meansPeak(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'after' => 'required|date_format:Y-m-d',
            'before' => 'required|date_format:Y-m-d|after_or_equal:after',
            'district' => 'sometimes|string',
            'concelho' => 'sometimes|string',
            'freguesia' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            return new JsonResponse([
                'success' => false,
                'error' => 'invalid_parameters',
                'errors' => $validator->errors(),
            ], 422);
        }

        $after = Carbon::createFromFormat('Y-m-d', $request->query('after'), 'Europe/Lisbon')->startOfDay();
        $before = Carbon::createFromFormat('Y-m-d', $request->query('before'), 'Europe/Lisbon')->endOfDay();

        if ($after->diffInDays($before) > self::MEANS_PEAK_MAX_DAYS) {
            return new JsonResponse([
                'success' => false,
                'error' => 'interval_too_long',
                'message' => 'The interval between after and before cannot exceed '.self::MEANS_PEAK_MAX_DAYS.' days.',
            ], 422);
        }

        $filters = array_filter([
            'district' => $request->query('district'),
            'concelho' => $request->query('concelho'),
            'freguesia' => $request->query('freguesia'),
        ], fn ($v) => is_string($v) && trim($v) !== '');
        $all = $request->boolean('all');

        $cacheKey = 'stats.meansPeak.v1.'.md5(json_encode([$after->toDateString(), $before->toDateString(), $filters, $all]));

        $data = Cache::remember($cacheKey, self::MEANS_PEAK_CACHE_SECONDS, function () use ($after, $before, $filters, $all) {
            $ids = $this->incidentIds($after, $before, $filters, $all);

            $result = ['incidents' => count($ids)];

            foreach (self::MEANS as $key => $field) {
                $result[$key] = empty($ids) ? null : $this->peakFor($field, $ids);
            }

            return $result;
        });

        return new JsonResponse([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Business ids of the incidents that started in the interval. Raw query:
     * laravel-mongodb v5 aliases `id` to `_id`, but legacy documents keep the
     * business id in a separate `id` field, which is what history references.
     *
     * @param array<string, string> $filters
     * @return array<int, string>
     */
    private function incidentIds(Carbon $after, Carbon $before, array $filters, bool $all): array
    {
        $match = [
            'dateTime' => [
                '$gte' => new UTCDateTime($after),
                '$lte' => new UTCDateTime($before),
            ],
        ];

        if (!$all) {
            $match['isFire'] = true;
        }

        foreach ($filters as $field => $value) {
            // Match what was sent and the casing the ingestion jobs store.
            $match[$field] = ['$in' => array_values(array_unique([$value, UTF8::ucwords(mb_strtolower(trim($value)))]))];
        }

        $docs = Incident::raw(fn ($collection) => $collection->find($match, [
            'projection' => ['id' => 1],
            'typeMap' => ['root' => 'array', 'document' => 'array'],
        ])->toArray());

        $ids = array_map(fn ($doc) => (string) ($doc['id'] ?? $doc['_id']), $docs);

        return array_values(array_unique($ids));
    }

    /**
     * Highest history snapshot for one kind of means (earliest one on ties).
     *
     * @param array<int, string> $ids
     */
    private function peakFor(string $field, array $ids): ?array
    {
        $snapshots = IncidentHistory::raw(fn ($collection) => $collection->find([
            '$or' => [['incidentId' => ['$in' => $ids]], ['id' => ['$in' => $ids]]],
            // Old snapshots may hold strings; Mongo sorts strings above numbers.
            $field => ['$type' => 'number', '$gt' => 0],
        ], [
            'sort' => [$field => -1, 'created' => 1],
            'limit' => 1,
            'typeMap' => ['root' => 'array', 'document' => 'array'],
        ])->toArray());

        if (empty($snapshots)) {
            return null;
        }

        $snapshot = $snapshots[0];
        $incidentId = (string) ($snapshot['incidentId'] ?? $snapshot['id']);
        $incident = Incident::whereFireId($incidentId)->first();
        $created = $snapshot['created'] ?? null;

        return [
            'value' => (int) $snapshot[$field],
            'at' => $created instanceof UTCDateTime
                ? Carbon::instance($created->toDateTime())->setTimezone('Europe/Lisbon')->toIso8601String()
                : null,
            'incident' => $incident ? (new IncidentResource($incident))->resolve() : ['id' => $incidentId],
        ];
    }
}
