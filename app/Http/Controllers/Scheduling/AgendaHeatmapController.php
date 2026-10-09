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
        $events = $request->validated()['events'];
        $hasGeometry = Schema::hasColumn('agenda_click_events', 'geometry');
        if (collect($events)->contains(fn ($e) => $e['layout_version'] == 3) && !$hasGeometry) {
            return response()->json(['message' => 'Geometría v3 no instalada.'], 503);
        }
        $rows = collect($events)->map(function ($event) use ($role, $hasGeometry) {
            $geometry = $event['geometry'] ?? null;
            if ($geometry) {
                $context = \Illuminate\Support\Arr::except($geometry, ['left', 'top', 'width', 'height']);
                sort($context['expanded']); ksort($context);
                $event['geometry_key'] = hash('sha256', json_encode([$event['viewport_width'], $event['viewport_height'], $event['view_mode'], $context]));
                $event['geometry'] = json_encode($geometry);
            } elseif ($hasGeometry) {
                $event['geometry'] = null; $event['geometry_key'] = null;
            }
            return $event + ['actor_role' => $role, 'recorded_at' => now('UTC')];
        })->all();
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
            'precision' => ['sometimes', Rule::in(['approximate', 'legacy', 'captured'])],
            'profile' => ['nullable', 'regex:/\A[a-f0-9]{64}\z/'],
        ]);
        if (Carbon::parse($filters['from'])->diffInDays(Carbon::parse($filters['to'])) > 92) {
            throw ValidationException::withMessages(['to' => 'El rango máximo es de 93 días.']);
        }
        if (!Schema::hasColumn('agenda_click_events', 'layout_version')) {
            return response()->json(['message' => 'Aplique la migration de telemetría antes de consultar el visor.'], 503);
        }
        $base = DB::table('agenda_click_events')->where('screen', $filters['screen'] ?? 'agenda')
            ->where('recorded_at', '>=', Carbon::parse($filters['from'], config('scheduling.operational_timezone'))->startOfDay()->utc())
            ->where('recorded_at', '<', Carbon::parse($filters['to'], config('scheduling.operational_timezone'))->addDay()->startOfDay()->utc())
            ->when(!empty($filters['view_mode']), fn ($q) => $q->where('view_mode', $filters['view_mode']))
            ->when(!empty($filters['zone']), fn ($q) => $q->where('zone', $filters['zone']))
            ->when(!empty($filters['element']), fn ($q) => $q->where('element', $filters['element']));
        if (($filters['precision'] ?? '') === 'captured') {
            return $this->capturedData($base, $filters);
        }
        // Same SQL on MySQL and SQLite, with x/y=1 clamped to the last cell.
        $sqlite = DB::connection()->getDriverName() === 'sqlite';
        $bx = 'CASE WHEN x >= 1 THEN 39 ELSE '.($sqlite ? 'CAST(x * 40 AS INTEGER)' : 'FLOOR(x * 40)').' END';
        $by = 'CASE WHEN y >= 1 THEN 39 ELSE '.($sqlite ? 'CAST(y * 40 AS INTEGER)' : 'FLOOR(y * 40)').' END';
        $zone = config('scheduling.operational_timezone', 'America/Lima');
        $points = DB::table('agenda_click_events')
            ->where('screen', $filters['screen'] ?? 'agenda')->where('layout_version', ($filters['precision'] ?? '') === 'legacy' ? 1 : 2)
            ->where('recorded_at', '>=', Carbon::parse($filters['from'], $zone)->startOfDay()->utc())
            ->where('recorded_at', '<', Carbon::parse($filters['to'], $zone)->addDay()->startOfDay()->utc())
            ->when(!empty($filters['view_mode']), fn ($q) => $q->where('view_mode', $filters['view_mode']))
            ->when(!empty($filters['element']), fn ($q) => $q->where('element', $filters['element']))
            ->when(!empty($filters['zone']), fn ($q) => $q->where('zone', $filters['zone']))
            ->selectRaw('zone, '.$bx.' AS bucket_x, '.$by.' AS bucket_y, COUNT(*) AS clicks')
            ->groupByRaw('zone, '.$bx.', '.$by)->orderBy('zone')->orderBy('bucket_y')->orderBy('bucket_x')->get();

        $legacy = ($filters['precision'] ?? '') === 'legacy';
        return response()->json(['screen' => $filters['screen'] ?? 'agenda', 'layout_version' => $legacy ? 1 : 2, 'grid_size' => 40, 'precision' => 'approximate', 'total' => (int) $points->sum('clicks'),
            'points' => $points->map(fn ($point) => [
                'zone' => $legacy ? 'screen' : $point->zone,
                'bucket_x' => (int) $point->bucket_x, 'bucket_y' => (int) $point->bucket_y, 'clicks' => (int) $point->clicks,
            ]),
        ]);
    }

    private function capturedData($base, array $filters)
    {
        if (!Schema::hasColumn('agenda_click_events', 'geometry')) {
            return response()->json(['screen' => $filters['screen'] ?? 'agenda', 'profiles' => [], 'points' => [], 'total' => 0, 'layout_version' => 3]);
        }
        $base->where('layout_version', 3)->whereNotNull('geometry_key');
        $profiles = (clone $base)->selectRaw('geometry_key, viewport_width, viewport_height, view_mode, COUNT(*) AS clicks, MAX(id) AS latest')
            ->groupBy('geometry_key', 'viewport_width', 'viewport_height', 'view_mode')->orderByDesc('latest')->limit(30)->get();
        $key = $filters['profile'] ?? $profiles->first()?->geometry_key;
        $selected = (clone $base)->where('geometry_key', $key);
        $context = $key ? json_decode((string) (clone $selected)->value('geometry'), true) : null;
        $rows = $key ? (clone $selected)->orderBy('id')->limit(2000)->get(['zone', 'x', 'y', 'geometry']) : collect();
        $points = $rows->map(function ($row) {
            $g = json_decode($row->geometry, true);
            return ['zone' => $row->zone, 'x' => $g['left'] + (float) $row->x * $g['width'],
                'y' => $g['top'] + (float) $row->y * $g['height'], 'clicks' => 1,
                'zone_rect' => \Illuminate\Support\Arr::only($g, ['left', 'top', 'width', 'height'])];
        });
        return response()->json(['screen' => $filters['screen'] ?? 'agenda', 'layout_version' => 3, 'precision' => 'captured',
            'profile' => $key, 'context' => $context, 'profiles' => $profiles->map(fn ($p) => ['key' => $p->geometry_key,
                'width' => (int) $p->viewport_width, 'height' => (int) $p->viewport_height, 'view' => $p->view_mode, 'clicks' => (int) $p->clicks]),
            'points' => $points, 'shown' => $points->count(), 'total' => $key ? (clone $selected)->count() : 0]);
    }

    public function preview(Request $request)
    {
        abort_unless($request->user()?->hasRole('ADMINISTRADOR'), 403);
        // Fixed display fixtures, no queries of doctors, patients, appointments or user catalogs.
        $data = [
            'sites' => collect([(object) ['id' => 1, 'nombre' => 'Sede de demostración']]),
            'specialties' => collect([(object) ['id' => 1, 'nombre' => 'Especialidad de demostración']]),
            'doctors' => collect(range(1, 9))->map(fn ($id) => (object) ['id' => $id, 'nombre' => 'Médico de demostración '.$id, 'specialty_id' => 1]),
            'channels' => collect([(object) ['id' => 1, 'nombre' => 'Canal de demostración']]),
            'interactionMedia' => collect([(object) ['id' => 1, 'nombre' => 'WhatsApp']]),
            'commercialUsers' => collect(), 'autoOwner' => false, 'previewMode' => true,
            'today' => '2026-10-09', 'views' => ['dia', 'semana', 'mes'],
            'canCreateAppointments' => true, 'canAssignResponsible' => false, 'canWritePatients' => true,
            'canRescheduleAppointments' => true, 'canCreateAdditional' => true,
            'canSubmitPayment' => true, 'canAuthorize' => false, 'canWaive' => false,
            'canWithdraw' => true, 'canCancel' => true, 'canNoShow' => true,
            'legend' => \App\Support\Scheduling\AgendaLegend::ordered(),
        ];
        return response()->view('scheduling.agenda.heatmap-preview', $data)
            ->header('Content-Security-Policy', "default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self'; connect-src 'none'; form-action 'none'; frame-ancestors 'self'; base-uri 'none'");
    }
}
