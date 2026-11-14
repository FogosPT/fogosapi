<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ServesWarningHistory;
use App\Models\Warning;
use App\Models\WarningAgif;
use App\Models\WeatherStation;
use App\Models\WeatherWarning;
use App\Models\WeatherWarningEvent;
use App\Http\Concerns\CacheableResponse;
use App\Support\IpmaArea;
use App\Support\LocationFilter;
use App\Support\WeatherWarningCollapse;
use App\Tools\BlueskyTool;
use App\Tools\FacebookTool;
use App\Tools\NotificationTool;
use App\Tools\TelegramTool;
use App\Tools\TwitterTool;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;


class WarningsController extends Controller
{
    use CacheableResponse;
    use ServesWarningHistory;

    /**
     * Active IPMA warnings, optionally only for some areas: `area` (IPMA
     * codes) and/or `district` / `concelho` / `freguesia` (see LocationFilter),
     * resolved to areas through IpmaArea. Lists use `|` or `,`.
     */
    public function ipma(Request $request)
    {
        $areas = $this->requestedAreas($request);

        $warnings = WeatherWarning::whereNull('supersededAt')->where('endTime', '>=', Carbon::now())
            ->when($areas !== null, fn ($q) => $q->whereIn('district', $areas))
            ->get();

        return new JsonResponse(WeatherWarningCollapse::collapse($warnings));
    }

    /** The 25 IPMA warning areas and the locations each one covers. */
    public function areas(): JsonResponse
    {
        return $this->cacheable(new JsonResponse(['success' => true, 'data' => IpmaArea::all()]), 86400);
    }

    /** Event log of the IPMA warnings; see ServesWarningHistory. */
    public function history(Request $request): JsonResponse
    {
        $areas = $this->requestedAreas($request);
        if ($areas !== null) {
            // An empty list must still match nothing, not everything.
            $request->query->set('area', implode(',', $areas ?: ['-']));
        }

        return $this->warningHistory($request, WeatherWarningEvent::class);
    }

    /**
     * Area codes asked for, or null when the request has no area / location
     * filter. 422 for an unknown area code or location.
     *
     * @return array<int, string>|null
     */
    private function requestedAreas(Request $request): ?array
    {
        $codes = [];
        $raw = $request->query('area');
        if (is_string($raw) && trim($raw) !== '') {
            $codes = array_values(array_filter(array_map(
                fn ($c) => strtoupper(trim($c)),
                preg_split('/[|,]/', $raw)
            )));
            $unknown = array_values(array_diff($codes, IpmaArea::codes()));
            if ($unknown) {
                throw new HttpResponseException(new JsonResponse([
                    'success' => false,
                    'error' => 'invalid_area',
                    'message' => 'Unknown area: ' . implode(', ', $unknown),
                ], 422));
            }
        }

        $locations = LocationFilter::fromRequest($request);

        if (!$codes && $locations->isEmpty()) {
            return null;
        }

        return array_values(array_unique(array_merge($codes, IpmaArea::forLocations(
            $locations->names('district'),
            $locations->names('concelho'),
            $locations->names('freguesia'),
        ))));
    }

    public function add(Request $request)
    {
        $status = $request->get('status');

        $warning = new Warning();
        $warning->text = $status;
        $warning->save();

        NotificationTool::sendWarningNotification($status);

        $text = "ALERTA: \r\n" . $status;
        TwitterTool::tweet($text);
        TelegramTool::publish($text);

        $message = "ALERTA: %0A" . $status;
        FacebookTool::publish($message);
    }

    public function addAgif(Request $request)
    {
        $status = $request->get('status');

        $warning = new WarningAgif();
        $warning->text = $status;
        $warning->save();

        NotificationTool::sendAllNotification($status);

        TwitterTool::tweet($status);
        TelegramTool::publish($status);
        FacebookTool::publish($status);
        BlueskyTool::publish($status);
    }
}
