<?php

namespace App\Http\Controllers\Scheduling;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Site;
use App\Models\Specialty;
use App\Services\Scheduling\DoctorScheduleImpactService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DoctorScheduleWorkspaceController extends Controller
{
    private const COLORS = [
        '#176B87', '#2D7A5E', '#765AA5', '#9B5E32', '#4B6FAE',
        '#8A4F68', '#2B7D7B', '#6C6F35', '#5A6B7A', '#7A5B3A',
    ];

    public function index(Request $request): View
    {
        return view('admissionist.schedule.index', [
            'doctors' => Doctor::query()
                ->where('estado', 'ACTIVO')
                ->with('specialty:id,nombre')
                ->orderBy('nombre')
                ->get(['id', 'specialty_id', 'nombre']),
            'specialties' => Specialty::query()
                ->where('estado', 'ACTIVO')
                ->orderBy('nombre')
                ->get(['id', 'nombre']),
            'sites' => Site::activo()->orderBy('nombre')->get(['id', 'nombre']),
            'canManageSchedules' => $request->user()->hasAnyRole(['ADMISION', 'COMERCIAL']),
            'today' => Carbon::today()->toDateString(),
        ]);
    }

    public function feed(Request $request): JsonResponse
    {
        $data = $request->validate([
            'start' => 'nullable|required_with:end|date',
            'end' => 'nullable|required_with:start|date|after_or_equal:start',
            'site_id' => 'nullable|integer|exists:sites,id',
            'specialty_id' => 'nullable|integer|exists:specialties,id',
            // The inherited endpoint returned an empty list for an unknown doctor filter.
            'doctor_id' => 'nullable|integer',
        ]);

        $start = isset($data['start'])
            ? Carbon::parse($data['start'])->startOfDay()
            : Carbon::today()->startOfMonth();
        $end = isset($data['end'])
            ? Carbon::parse($data['end'])->startOfDay()
            : Carbon::today()->endOfMonth()->startOfDay();

        $blocks = DoctorSchedule::query()
            ->where('estado', 'ACTIVO')
            ->with(['doctor:id,specialty_id,nombre', 'doctor.specialty:id,nombre', 'site:id,nombre'])
            ->where(function (Builder $query) use ($start, $end) {
                $query->whereBetween('fecha_cita', [$start->toDateString(), $end->toDateString()])
                    ->orWhereNull('fecha_cita');
            })
            ->when(isset($data['doctor_id']), fn (Builder $query) => $query->where('doctor_id', $data['doctor_id']))
            ->when(isset($data['specialty_id']), function (Builder $query) use ($data) {
                $query->whereHas('doctor', fn (Builder $doctor) => $doctor->where('specialty_id', $data['specialty_id']));
            })
            ->when(isset($data['site_id']), function (Builder $query) use ($data) {
                $query->where(function (Builder $site) use ($data) {
                    $site->where('site_id', $data['site_id'])->orWhereNull('site_id');
                });
            })
            ->orderBy('doctor_id')
            ->orderBy('hora_inicio')
            ->get();

        $dates = collect(CarbonPeriod::create($start, $end))->map(fn (Carbon $date) => $date->copy());

        $events = $blocks->flatMap(function (DoctorSchedule $block) use ($dates, $start, $end) {
            $occurrences = $block->fecha_cita
                ? collect([Carbon::parse($block->fecha_cita)])
                : $dates->filter(fn (Carbon $date) => $date->dayOfWeekIso === (int) $block->dia_semana);

            return $occurrences
                ->filter(fn (Carbon $date) => $date->betweenIncluded($start, $end))
                ->map(fn (Carbon $date) => $this->event($block, $date));
        })->values();

        return response()->json($events);
    }

    public function impact(
        Request $request,
        DoctorSchedule $doctorSchedule,
        DoctorScheduleImpactService $impact
    ): JsonResponse {
        $data = $request->validate([
            'action' => 'required|in:update,delete',
            'occurrence_date' => 'nullable|date',
            'range_start' => 'nullable|date',
            'range_end' => 'nullable|date|after_or_equal:range_start',
            'new_date' => 'nullable|date',
            'new_weekday' => 'nullable|integer|between:1,7',
            'new_start' => 'nullable|date_format:H:i,H:i:s',
            'new_end' => 'nullable|date_format:H:i,H:i:s|after:new_start',
        ]);

        $result = $impact->inspect($doctorSchedule, $data, (int) $request->user()->id);

        return response()->json([
            'count' => $result->count(),
            'truncated' => $result->count() > 10,
            'appointments' => $result->take(10)->values(),
            'message' => $result->isEmpty()
                ? 'No se detectaron citas existentes afectadas.'
                : 'Este cambio deja '.$result->count().' cita(s) existente(s) fuera del horario.',
        ]);
    }

    private function event(DoctorSchedule $block, Carbon $date): array
    {
        $color = self::COLORS[((int) $block->doctor_id - 1) % count(self::COLORS)];
        $start = $date->toDateString().'T'.substr((string) $block->hora_inicio, 0, 8);
        $end = $date->toDateString().'T'.substr((string) $block->hora_fin, 0, 8);
        $recurring = $block->fecha_cita === null;

        return [
            'id' => $block->id.'-'.$date->toDateString(),
            'title' => $block->doctor->nombre,
            'start' => $start,
            'end' => $end,
            'color' => $color,
            'backgroundColor' => $color,
            'borderColor' => $color,
            'textColor' => '#ffffff',
            // Keep the inherited feed keys while the new workspace consumes extendedProps.
            'doctor_schedule_id_edit' => (int) $block->id,
            'doctor_id_edit' => (int) $block->doctor_id,
            'hora_inicio_edit' => substr((string) $block->hora_inicio, 0, 5),
            'hora_fin_edit' => substr((string) $block->hora_fin, 0, 5),
            'duracion_edit_cita' => (int) $block->duracion_cita,
            'fecha_cita_edit' => $date->toDateString(),
            'extendedProps' => [
                'schedule_id' => (int) $block->id,
                'doctor_id' => (int) $block->doctor_id,
                'doctor_name' => (string) $block->doctor->nombre,
                'doctor_initial' => $this->doctorInitials((string) $block->doctor->nombre),
                'doctor_color' => $color,
                'specialty_id' => (int) $block->doctor->specialty_id,
                'specialty_name' => (string) optional($block->doctor->specialty)->nombre,
                'site_id' => $block->site_id ? (int) $block->site_id : null,
                'site_name' => $block->site ? (string) $block->site->nombre : 'Sin sede (heredado)',
                'occurrence_date' => $date->toDateString(),
                'stored_date' => $block->fecha_cita ? substr((string) $block->fecha_cita, 0, 10) : null,
                'weekday' => (int) $block->dia_semana,
                'start_time' => substr((string) $block->hora_inicio, 0, 5),
                'end_time' => substr((string) $block->hora_fin, 0, 5),
                'appointment_duration' => (int) $block->duracion_cita,
                'recurrence' => $recurring ? 'WEEKLY' : 'DATE',
                'recurrence_label' => $recurring
                    ? 'Patrón semanal · '.DoctorSchedule::DIAS[(int) $block->dia_semana]
                    : 'Solo '. $date->translatedFormat('d M Y'),
            ],
        ];
    }

    private function doctorInitials(string $name): string
    {
        $withoutTitle = preg_replace('/^dr(?:a)?\.?\s+/iu', '', trim($name)) ?: trim($name);
        $words = preg_split('/\s+/u', $withoutTitle, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return collect($words)
            ->take(2)
            ->map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('');
    }
}
