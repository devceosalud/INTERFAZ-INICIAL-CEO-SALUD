<?php

namespace App\Http\Controllers\Scheduling;

use App\Http\Controllers\Controller;
use App\Http\Requests\Scheduling\StoreAgendaClickEventsRequest;
use App\Support\Scheduling\AgendaClickTelemetry;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AgendaHeatmapController extends Controller
{
    public function store(StoreAgendaClickEventsRequest $request)
    {
        if (!Schema::hasTable('agenda_click_events') || !Schema::hasColumn('agenda_click_events', 'layout_version')) {
            return response()->json(['message' => 'Telemetría no instalada.'], 503);
        }
        $roles = $request->user()->getRoleNames()->intersect(['ADMINISTRADOR', 'ADMISION', 'RECEPCION', 'COMERCIAL', 'MEDICO'])->sort()->values();
        $role = $roles->first() ?? 'OTRO';
        $rows = collect($request->validated()['events'])->map(fn ($event) => $event + [
            'actor_role' => $role, 'recorded_at' => now('UTC'),
        ])->all();
        // Ignore retries of the same anonymous event. No actor/session/patient identifiers.
        DB::table('agenda_click_events')->insertOrIgnore($rows);

        return response()->noContent();
    }

    public function index(Request $request)
    {
        abort_unless($request->user()?->hasRole('ADMINISTRADOR'), 403);
        return view('scheduling.agenda.heatmap', [
            'installed' => Schema::hasColumn('agenda_click_events', 'layout_version'),
            'modules' => AgendaClickTelemetry::MODULES,
            'elements' => AgendaClickTelemetry::ELEMENTS,
            'timezone' => config('scheduling.operational_timezone', 'America/Lima'),
        ]);
    }

    public function data(Request $request)
    {
        abort_unless($request->user()?->hasRole('ADMINISTRADOR'), 403);
        $filters = $request->validate([
            'screen' => ['sometimes', Rule::in(array_keys(AgendaClickTelemetry::MODULES))],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'view_mode' => ['nullable', Rule::in(['dia', 'semana', 'mes', 'horarios', 'lista', 'ficha'])],
            'zone' => ['nullable', Rule::in(['toolbar', 'doctors', 'mini', 'booking', 'grid', 'dialog', 'calendar', 'sidebar', 'list', 'record'])],
            'element' => ['nullable', Rule::in(AgendaClickTelemetry::ELEMENTS)],
        ]);
        if (Carbon::parse($filters['from'])->diffInDays(Carbon::parse($filters['to'])) > 92) {
            throw ValidationException::withMessages(['to' => 'El rango máximo es de 93 días.']);
        }
        if (!Schema::hasColumn('agenda_click_events', 'layout_version')) {
            return response()->json(['message' => 'Aplique la migration de telemetría antes de consultar el visor.'], 503);
        }
        // Same SQL on MySQL and SQLite, with x/y=1 clamped to the last cell.
        $sqlite = DB::connection()->getDriverName() === 'sqlite';
        $bx = 'CASE WHEN x >= 1 THEN 39 ELSE '.($sqlite ? 'CAST(x * 40 AS INTEGER)' : 'FLOOR(x * 40)').' END';
        $by = 'CASE WHEN y >= 1 THEN 39 ELSE '.($sqlite ? 'CAST(y * 40 AS INTEGER)' : 'FLOOR(y * 40)').' END';
        $zone = config('scheduling.operational_timezone', 'America/Lima');
        $points = DB::table('agenda_click_events')
            ->where('screen', $filters['screen'] ?? 'agenda')->where('layout_version', 2)
            ->where('recorded_at', '>=', Carbon::parse($filters['from'], $zone)->startOfDay()->utc())
            ->where('recorded_at', '<', Carbon::parse($filters['to'], $zone)->addDay()->startOfDay()->utc())
            ->when(!empty($filters['view_mode']), fn ($q) => $q->where('view_mode', $filters['view_mode']))
            ->when(!empty($filters['element']), fn ($q) => $q->where('element', $filters['element']))
            ->when(!empty($filters['zone']), fn ($q) => $q->where('zone', $filters['zone']))
            ->selectRaw('zone, '.$bx.' AS bucket_x, '.$by.' AS bucket_y, COUNT(*) AS clicks')
            ->groupByRaw('zone, '.$bx.', '.$by)->orderBy('zone')->orderBy('bucket_y')->orderBy('bucket_x')->get();

        return response()->json(['screen' => $filters['screen'] ?? 'agenda', 'layout_version' => 2, 'grid_size' => 40, 'total' => (int) $points->sum('clicks'),
            'points' => $points->map(fn ($point) => [
                'zone' => $point->zone,
                'bucket_x' => (int) $point->bucket_x, 'bucket_y' => (int) $point->bucket_y, 'clicks' => (int) $point->clicks,
            ]),
        ]);
    }
}
