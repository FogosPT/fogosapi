<?php

namespace App\Http\Controllers;

use App\Models\MadeiraEvent;
use App\Models\MadeiraEventSnapshot;
use App\Models\MadeiraOccurrence;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

/**
 * SRPC-RAM (Madeira) adverse-weather events (ProcessMadeiraEvents) and the
 * individual occurrences of their ArcGIS statistics view
 * (ProcessMadeiraOccurrences). Current versions only; history stays in the
 * revision collections.
 */
class MadeiraController extends Controller
{
    /**
     * The event currently active, or `data: null` when there is none.
     * GET v2/madeira/events/current
     */
    public function current()
    {
        $payload = Cache::remember('madeira:events:current', 60, function () {
            $event = MadeiraEvent::where('active', true)->orderBy('sourceUpdatedAt', 'desc')->first();

            return ['success' => true, 'data' => $event ? $this->detail($event) : null];
        });

        return new JsonResponse($payload);
    }

    /**
     * Events newest first, without the per-municipality/hour breakdowns.
     * GET v2/madeira/events?limit=20 (1-100)
     */
    public function events(Request $request)
    {
        $limit = 20;
        if ($request->has('limit')) {
            $limit = filter_var($request->query('limit'), FILTER_VALIDATE_INT);
            if ($limit === false || $limit < 1 || $limit > 100) {
                return new JsonResponse(['success' => false, 'error' => 'invalid_limit'], 422);
            }
        }

        $payload = Cache::remember('madeira:events:'.$limit, 60, function () use ($limit) {
            $events = MadeiraEvent::orderBy('start', 'desc')->limit($limit)->get();

            return ['success' => true, 'data' => $events->map(fn (MadeiraEvent $e) => $this->summary($e))->values()];
        });

        return new JsonResponse($payload);
    }

    /**
     * GET v2/madeira/events/{id}
     */
    public function show(string $id)
    {
        $event = $this->find($id);
        if (!$event) {
            return new JsonResponse(['success' => false, 'error' => 'not_found'], 404);
        }

        return new JsonResponse(Cache::remember('madeira:event:'.$id, 60, fn () => [
            'success' => true,
            'data' => $this->detail($event),
        ]));
    }

    /**
     * KPIs at every feed update of the event, oldest first.
     * GET v2/madeira/events/{id}/timeline
     */
    public function timeline(string $id)
    {
        $event = $this->find($id);
        if (!$event) {
            return new JsonResponse(['success' => false, 'error' => 'not_found'], 404);
        }

        return new JsonResponse(Cache::remember('madeira:event:'.$id.':timeline', 60, function () use ($event) {
            $rows = MadeiraEventSnapshot::where('eventId', $event->eventId)->orderBy('time', 'asc')->get();

            return [
                'success' => true,
                'eventId' => $event->eventId,
                'data' => $rows->map(fn (MadeiraEventSnapshot $s) => [
                    'time' => $this->iso($s->time),
                    'occurrences' => $s->occurrences,
                    'activeOccurrences' => $s->activeOccurrences,
                    'operationals' => $s->operationals,
                    'groundAssets' => $s->groundAssets,
                    'municipalities' => $s->municipalities,
                ])->values(),
            ];
        }));
    }

    /**
     * Occurrences currently published by the source, newest first. Without
     * `from`, the last 24 hours.
     * GET v2/madeira/occurrences?from=&to=&municipality=3103&parish=310302&nature=3315&limit=500
     */
    public function occurrences(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'municipality' => 'nullable|in:'.implode(',', array_keys(MadeiraOccurrence::MUNICIPALITIES)),
            'parish' => ['nullable', 'regex:/^3[12]\d{4}$/'],
            'nature' => ['nullable', 'regex:/^\d{4}$/'],
            'limit' => 'nullable|integer|min:1|max:2000',
        ]);

        if ($validator->fails()) {
            return new JsonResponse(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $params = $validator->validated();

        $payload = Cache::remember('madeira:occurrences:'.md5(json_encode($params)), 60, function () use ($params) {
            $from = isset($params['from']) ? Carbon::parse($params['from']) : Carbon::now()->subDay();

            $rows = MadeiraOccurrence::whereNull('removedAt')
                ->where('start', '>=', $from)
                ->when($params['to'] ?? null, fn ($q, $to) => $q->where('start', '<=', Carbon::parse($to)))
                ->when($params['municipality'] ?? null, fn ($q, $m) => $q->where('municipalityCode', $m))
                ->when($params['parish'] ?? null, fn ($q, $p) => $q->where('parishCode', $p))
                ->when($params['nature'] ?? null, fn ($q, $n) => $q->where('natureCode', $n))
                ->orderBy('start', 'desc')
                ->orderBy('key', 'desc')
                ->limit((int) ($params['limit'] ?? 500))
                ->get();

            return ['success' => true, 'data' => $rows->map(fn (MadeiraOccurrence $o) => $this->occurrence($o))->values()];
        });

        return new JsonResponse($payload);
    }

    private function find(string $id): ?MadeiraEvent
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $id)) {
            return null;
        }

        return MadeiraEvent::where('eventId', $id)->first();
    }

    private function summary(MadeiraEvent $event): array
    {
        return [
            'id' => $event->eventId,
            'title' => $event->title,
            'zone' => $event->zone,
            'status' => $event->status,
            'active' => $event->active,
            'start' => $this->iso($event->start),
            'end' => $this->iso($event->end),
            'untilNow' => $event->untilNow,
            'updatedAt' => $this->iso($event->sourceUpdatedAt),
            'kpi' => $event->kpi,
            'summary' => $event->summary,
        ];
    }

    private function detail(MadeiraEvent $event): array
    {
        return $this->summary($event) + [
            'recommendations' => $event->recommendations ?? [],
            'municipalities' => $event->municipalities ?? [],
            'natures' => $event->natures ?? [],
            'hourly' => $event->hourly ?? [],
            'roadRestrictions' => $event->roadRestrictions ?? [],
            'communiques' => $event->communiques ?? [],
            'notes' => $event->notes,
            'source' => $event->source,
        ] + $this->occurrenceBreakdowns($event);
    }

    /**
     * Breakdowns the aggregate feed lacks, from the individual occurrences:
     * those inside the event period whose nature is one of the event's (the
     * selection SRPC-RAM uses for the event).
     */
    private function occurrenceBreakdowns(MadeiraEvent $event): array
    {
        $codes = collect($event->natures ?? [])->pluck('code')->filter()->values()->all();

        $rows = MadeiraOccurrence::whereNull('removedAt')
            ->where('start', '>=', $event->start)
            ->where('start', '<=', $event->end ?? Carbon::now())
            ->when($codes, fn ($q) => $q->whereIn('natureCode', $codes))
            ->get();

        return [
            'parishes' => $rows->groupBy(fn (MadeiraOccurrence $o) => $o->parishCode ?? '')
                ->map(fn (Collection $group, $code) => [
                    'code' => $code === '' ? null : (string) $code,
                    'name' => $code === '' ? null : $this->mostCommon($group->pluck('parish')),
                    'municipality' => $group->first()->municipality,
                    'occurrences' => $group->sum(fn (MadeiraOccurrence $o) => $o->copies ?? 1),
                    'firefighters' => $group->sum(fn (MadeiraOccurrence $o) => ($o->firefighters ?? 0) * ($o->copies ?? 1)),
                    'vehicles' => $group->sum(fn (MadeiraOccurrence $o) => ($o->vehicles ?? 0) * ($o->copies ?? 1)),
                ])
                ->sortByDesc('occurrences')
                ->values()
                ->all(),
            'entities' => $rows->flatMap(fn (MadeiraOccurrence $o) => array_fill(0, $o->copies ?? 1, $o->entities ?? []))
                ->flatten()
                ->countBy()
                ->sortDesc()
                ->map(fn ($n, $code) => ['code' => (string) $code, 'occurrences' => $n])
                ->values()
                ->all(),
            'topOccurrences' => $rows->sortBy([['vehicles', 'desc'], ['firefighters', 'desc'], ['start', 'asc'], ['key', 'asc']])
                ->take(10)
                ->map(fn (MadeiraOccurrence $o) => $this->occurrence($o))
                ->values()
                ->all(),
        ];
    }

    /** The source spells some parishes differently; the most frequent wins. */
    private function mostCommon(Collection $names): ?string
    {
        return $names->filter()->countBy()->sortDesc()->keys()->first();
    }

    private function occurrence(MadeiraOccurrence $o): array
    {
        return [
            'id' => $o->key,
            'copies' => $o->copies,
            'start' => $this->iso($o->start),
            'natureCode' => $o->natureCode,
            'nature' => $o->nature,
            'firefighters' => $o->firefighters,
            'vehicles' => $o->vehicles,
            'parishCode' => $o->parishCode,
            'parish' => $o->parish,
            'municipalityCode' => $o->municipalityCode,
            'municipality' => $o->municipality,
            'entities' => $o->entities ?? [],
            'updatedAt' => $this->iso($o->sourceUpdatedAt),
        ];
    }

    private function iso(?Carbon $time): ?string
    {
        return $time?->copy()->utc()->format('Y-m-d\TH:i:s\Z');
    }
}
