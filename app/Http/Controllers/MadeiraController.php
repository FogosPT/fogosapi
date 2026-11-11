<?php

namespace App\Http\Controllers;

use App\Models\MadeiraEvent;
use App\Models\MadeiraEventSnapshot;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;

/**
 * SRPC-RAM (Madeira) adverse-weather events, ingested by ProcessMadeiraEvents.
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
        ];
    }

    private function iso(?Carbon $time): ?string
    {
        return $time?->copy()->utc()->format('Y-m-d\TH:i:s\Z');
    }
}
