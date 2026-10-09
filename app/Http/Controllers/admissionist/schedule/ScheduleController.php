<?php

namespace App\Http\Controllers\admissionist\schedule;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Services\Catalog\ActiveDoctorServiceResolver;
use App\Models\Service;
use App\Models\Site;
use App\Models\Specialty;
use App\Services\Scheduling\ConcreteScheduleConflict;
use App\Services\Scheduling\DoctorAvailabilityService;
use App\Services\Scheduling\ScheduleOverlapDetector;
use App\Support\Scheduling\AppointmentOccupancy;
use App\Support\Scheduling\AvailabilityQuery;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ScheduleController extends Controller
{
    public function list(Request $request)
    {

        $inicioRango = $request->start ? Carbon::parse($request->start) : Carbon::now()->startOfMonth();
        $finRango = $request->end ? Carbon::parse($request->end) : Carbon::now()->addMonth()->endOfMonth();

        $appointment = Appointment::with(['patient', 'doctor', 'service.specialty'])
            ->visibleToAgendaUser((int) $request->user()->id)
            // Compared as dates: binding datetimes against a date column silently dropped
            // every appointment falling on the first day of the requested range.
            ->whereBetween('fecha_cita', [$inicioRango->toDateString(), $finRango->toDateString()])
            ->whereNotIn('estado_cita', ['NO_ASISTIO', 'CANCELADO', 'RETIRO', 'ATENDIDO', 'REEVALUACION']);

        if ($request->specialty_id) {
            $appointment->whereHas('service', function ($query) use ($request) {
                $query->where('specialty_id', $request->specialty_id);
            });
        }

        if ($request->doctor_id) {
            $appointment->where('doctor_id', $request->doctor_id);
        }

        $appointment = $appointment->get();

        $colors = [
            1 => '#118da6', 2 => '#0d6efd', 3 => '#ffc107', 4 => '#021209',
            5 => '#ce14cb', 6 => '#dc3545', 7 => '#110569', 8 => '#ffc107',
        ];

        $events = $appointment->map(function ($schedule) use ($colors) {
            $color = $colors[$schedule->service->specialty->id] ?? '#198754';

            // Same duration policy as the availability engine, so the calendar cannot drift.
            $fin = Carbon::parse($schedule->fecha_cita . ' ' . $schedule->hora_cita)
                ->addMinutes(AppointmentOccupancy::minutesFor($schedule->duracion_cita));

            return [
                'id' => $schedule->id,
                'title' => $schedule->patient->nombre . ' - ' . $schedule->service->nombre,
                'start' => $schedule->fecha_cita . 'T' . $schedule->hora_cita,
                'end' => $schedule->fecha_cita . 'T' . $fin->format('H:i:s'),
                'color' => $color,
                'backgroundColor' => $color,
                'borderColor' => $color,
                'textColor' => '#ffffff',

                'tipo' => 'ocupado', // NUEVO
                'patient_id' => $schedule->patient_id,
                'documento_paciente' => $schedule->patient->numero_identidad,
                'nombre_paciente' => $schedule->patient->nombre . ' ' . $schedule->patient->apellido_paterno . ' ' . $schedule->patient->apellido_materno,
                'specialty_id' => $schedule->service->specialty->id,
                'nombre_especialidad' => $schedule->service->specialty->nombre,
                'doctor_id' => $schedule->doctor_id,
                'nombre_doctor' => $schedule->doctor->nombre,
                'service_id' => $schedule->service_id,
                'nombre_servicio' => $schedule->service->nombre,
                'fecha_cita' => $schedule->fecha_cita,
                'hora_cita' => $schedule->hora_cita,
                'total_pagado' => $schedule->total_pagado,
                'saldo_pendiente' => $schedule->saldo_pendiente,
                'estado_pagado' => $schedule->estado_pagado,
                'estado_cita' => $schedule->estado_cita,
                'observaciones' => $schedule->observaciones ?? 'SIN OBSERVACIONES',
                'motivo_consulta' => $schedule->motivo_consulta ?? 'SIN MOTIVO',
            ];
        })->values();

        if ($request->doctor_id) {
            $eventosDisponibles = $this->generarEventosDisponibles(
                $request->doctor_id,
                $inicioRango->copy()->max(Carbon::today()), // nunca generar disponibilidad en el pasado
                $finRango
            );

            $events = $events->concat($eventosDisponibles);
        }

        return response()->json($events->values());
    }

    /**
     * Free slots for the calendar, resolved by the availability engine.
     *
     * The slot rules used to be duplicated here. Delegating inherits the corrections of
     * MVP-2A: an appointment stored without `duracion_cita` still blocks, a slot never runs
     * past `hora_fin`, overlap is detected by range, the set of consuming states is defined
     * once, and a site filter tolerates legacy rows without one. The event shape is unchanged.
     */
    private function generarEventosDisponibles(int $doctorId, Carbon $desde, Carbon $hasta)
    {
        $availability = app(DoctorAvailabilityService::class);
        $eventos = collect();
        $fecha = $desde->copy();

        while ($fecha->lte($hasta)) {
            $fechaStr = $fecha->toDateString();

            $slots = $availability
                ->forDay(new AvailabilityQuery($doctorId, $fecha))
                ->availableSlots();

            foreach ($slots as $slot) {
                $inicio = $slot->range()->start();

                $eventos->push([
                    'id' => 'libre-' . $fechaStr . '-' . $inicio->format('Hi'),
                    'title' => 'Disponible',
                    'start' => $fechaStr . 'T' . $inicio->format('H:i:s'),
                    'end' => $fechaStr . 'T' . $slot->range()->end()->format('H:i:s'),
                    'color' => '#28a745',
                    'backgroundColor' => '#28a745',
                    'borderColor' => '#28a745',
                    'textColor' => '#ffffff',

                    'tipo' => 'disponible',
                    'doctor_id' => $doctorId,
                    'fecha_cita' => $fechaStr,
                    'hora_cita' => $inicio->format('H:i'),
                ]);
            }

            $fecha->addDay();
        }

        return $eventos;
    }


    //PARA ACTUALIZAR LA AGENDA O CITA SELECCIONADA DEL CINTILLO DEL CALENDAR
    public function update(Request $request)
    {
        //dd($request->all());
        $validator = Validator::make($request->all(), [
            'doctor_id_edit' => 'required',
            'service_id_edit' => 'required',
            'fecha_cita_edit' => 'required|date',
            'hora_cita_edit' => 'required',
            'estado_cita' => 'required',
            'motivo_consulta_edit' => 'nullable|string',
            'observaciones_edit' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 0,
                'error' => $validator->errors()->toArray()
            ]);
        }

        $schedule = Appointment::visibleToAgendaUser((int) $request->user()->id)
            ->whereKey($request->appointment_id)->firstOrFail();
        \App\Support\Scheduling\AppointmentClosingGuard::assertLegacyUpdate($schedule, $request->estado_cita);

        $doctorService = app(ActiveDoctorServiceResolver::class)->resolveAssignment((int) $request->service_id_edit, (int) $request->doctor_id_edit);
        $service = Service::find($doctorService->service_id); //buscamos el servicio por id
        //dd($service);
        $exito = $schedule->update([
            'doctor_id' => $request->doctor_id_edit,
            'service_id' => $service->id,
            'fecha_cita' => $request->fecha_cita_edit,
            'hora_cita' => $request->hora_cita_edit,
            'estado_cita' => $request->estado_cita,
            'motivo_consulta' => $request->motivo_consulta_edit,
            'observaciones' => $request->observaciones_edit
        ]);

        if ($exito) {
            return response()->json([
                'code' => 1,
                'msg' => "Cita actualizada correctamente"
            ]);
        } else {
            return response()->json([
                'code' => 0,
                'msg' => "Cita no actualizada"
            ]);
        }
    }


    /********************************************************************************************************
     * CRUD DE HORARIOS MEDICOS  Y SU CALENDARIO WEB                                                        *
     ********************************************************************************************************/
    public function doctor_schedules(Request $request)
    {
        // The calendar always sends the visible window. This used to look only at the
        // server's current month with a LIKE, so navigating to any other month or year
        // returned nothing.
        $request->validate([
            'start' => 'nullable|date',
            'end' => 'nullable|date|after_or_equal:start',
        ]);

        $inicioRango = $request->start ? Carbon::parse($request->start) : Carbon::now()->startOfMonth();
        $finRango = $request->end ? Carbon::parse($request->end) : Carbon::now()->endOfMonth();

        $doctor_schedules = DoctorSchedule::where('estado', 'ACTIVO')
            ->whereBetween('fecha_cita', [$inicioRango->toDateString(), $finRango->toDateString()]);

        //PARA FILTRAR CITAS POR MEDICO
        if ($request->doctor_id) {
            $doctor_schedules->where('doctor_id', $request->doctor_id);
        }

        $doctor_schedules = $doctor_schedules->get();

        $events = $doctor_schedules->map(function ($schedule) {

            $colors = [
                1 => '#118da6',
                2 => '#0d6efd',
                3 => '#ffc107',
                4 => '#021209',
                5 => '#ce14cb',
                6 => '#dc3545',
                7 => '#110569',
                8 => '#ffc107',
            ];

            $color = $colors[$schedule->doctor->id] ?? '#198754';

            return [
                //eventos calendarios
                'id' => $schedule->id,
                'title' => $schedule->doctor->nombre, // Un título más descriptivo para el calendario
                'start' => $schedule->fecha_cita . "T" . $schedule->hora_inicio, // hora inicio   '2026-09-16T10:00:00',
                'end' => $schedule->fecha_cita . "T" . $schedule->hora_fin,      //hora fin
                'color' => $color,
                'backgroundColor' => $color,
                'borderColor' => $color,
                'textColor' => '#ffffff',

                //eventos comunes
                'doctor_schedule_id_edit' => $schedule->id,
                'doctor_id_edit' => $schedule->doctor_id,
                'hora_inicio_edit' => $schedule->hora_inicio,
                'hora_fin_edit' => $schedule->hora_fin,
                'duracion_edit_cita' => $schedule->duracion_cita,
                'fecha_cita_edit' => $schedule->fecha_cita
            ];
        });

        return response()->json($events);
    }


    public function index()
    {
        $doctor_schedules = DoctorSchedule::where('estado', 'ACTIVO')->get();

        // Los bloques y su sede se cargan de una vez: la lista los recorre por médico y
        // resolverlos dentro del bucle costaba una consulta por fila.
        $doctors = Doctor::where('estado', 'ACTIVO')
            ->with(['schedules' => fn ($query) => $query->where('estado', 'ACTIVO')->with('site:id,nombre')])
            ->get();
        $specialties = Specialty::where('estado', 'ACTIVO')->get();

        return view('admissionist.schedule.index', [
            'doctor_schedules' => $doctor_schedules,
            'doctors' => $doctors,
            'specialties' => $specialties,
            'sites' => Site::activo()->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    //PARA GUARDAR LOS DATOS DEL HORARIO DEL DOCTOR
    public function store(Request $request)
    {
        $request->merge(['scope' => $request->input('scope', 'single')]);

        $validator = Validator::make($request->all(), [
            'doctor_id'      => 'required|exists:doctors,id',
            'scope' => 'required|in:single,selected,weekly,dates',
            'fecha_cita' => 'required_unless:scope,weekly,dates|nullable|date',
            // single and concrete dates omit weekdays; selected and weekly still require at least one day.
            'weekdays' => 'exclude_if:scope,single|exclude_if:scope,dates|required|array',
            'weekdays.*' => 'integer|between:1,7|distinct',
            'dates' => 'exclude_unless:scope,dates|required|array',
            'dates.*' => 'date_format:Y-m-d|distinct',
            'hora_inicio'    => 'required|date_format:H:i',
            'hora_fin'       => 'required|date_format:H:i|after:hora_inicio',
            'duracion_cita'  => 'required|integer|in:10,15,20,30,45,60',
            'site_id'        => 'nullable|exists:sites,id',
        ], [
            'weekdays.required' => 'Selecciona al menos un día de la semana.',
            'dates.required' => 'Selecciona al menos una fecha.',
            'dates.*.date_format' => 'La fecha no es válida.',
            'dates.*.distinct' => 'No repitas la misma fecha.',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();

            return response()->json([
                'code'  => 0,
                'error' => $errors->toArray()
            ], ($errors->has('weekdays') || $errors->has('dates') || $errors->has('dates.*')) ? 422 : 200);
        }

        if ($request->scope === 'dates') {
            return $this->storeConcreteDates($request);
        }

        $scope = $request->scope;
        $weekdays = collect($request->input('weekdays', []))->map(fn ($day) => (int) $day)->unique()->sort()->values();

        if ($scope === 'single') {
            $date = Carbon::parse($request->fecha_cita);
            $targets = collect([['date' => $date->toDateString(), 'weekday' => $date->dayOfWeekIso]]);
        } elseif ($scope === 'selected') {
            $weekStart = Carbon::parse($request->fecha_cita)->startOfWeek();
            $targets = $weekdays->map(fn (int $weekday) => [
                'date' => $weekStart->copy()->addDays($weekday - 1)->toDateString(),
                'weekday' => $weekday,
            ]);
        } else {
            // The real inherited recurrence contract is fecha_cita NULL + ISO weekday.
            $targets = $weekdays->map(fn (int $weekday) => ['date' => null, 'weekday' => $weekday]);
        }

        $created = DB::transaction(function () use ($request, $targets) {
            return $targets->map(function (array $target) use ($request) {
                return DoctorSchedule::create([
                    'doctor_id' => $request->doctor_id,
                    'site_id' => $request->site_id ?: null,
                    'dia_semana' => $target['weekday'],
                    'fecha_cita' => $target['date'],
                    'hora_inicio' => $request->hora_inicio,
                    'hora_fin' => $request->hora_fin,
                    'duracion_cita' => $request->duracion_cita,
                    'estado' => 'ACTIVO',
                ]);
            });
        });

        //RESPUESTA DE CONSUMO
        if ($created->isNotEmpty()) {
            return response()->json([
                'code' => 1,
                'msg' => $created->count() === 1
                    ? 'Horario del médico guardado correctamente.'
                    : $created->count().' horarios del médico guardados correctamente.',
                'created_count' => $created->count(),
                'schedule_ids' => $created->pluck('id')->all(),
            ], 200);
        } else {
            return response()->json([
                'code' => 0,
                'msg' => "No se registro el horario"
            ]);
        }
    }

    private function storeConcreteDates(Request $request)
    {
        $targets = collect($request->input('dates'))->map(function ($date) {
            $parsed = Carbon::createFromFormat('!Y-m-d', $date)->startOfDay();

            return [
                'date' => $parsed->toDateString(),
                'weekday' => (int) $parsed->dayOfWeekIso,
            ];
        })->values();

        $detector = app(ScheduleOverlapDetector::class);

        try {
            $created = DB::transaction(function () use ($request, $targets, $detector) {
                $conflicts = [];

                foreach ($targets as $target) {
                    $hits = $detector->overlappingWith(
                        (int) $request->doctor_id,
                        Carbon::parse($target['date']),
                        $request->hora_inicio,
                        $request->hora_fin
                    );

                    if ($hits->isNotEmpty()) {
                        $conflicts[] = $target['date'];
                    }
                }

                if ($conflicts !== []) {
                    throw new ConcreteScheduleConflict($conflicts);
                }

                return $targets->map(function (array $target) use ($request) {
                    return DoctorSchedule::create([
                        'doctor_id' => $request->doctor_id,
                        'site_id' => $request->site_id ?: null,
                        'dia_semana' => $target['weekday'],
                        'fecha_cita' => $target['date'],
                        'hora_inicio' => $request->hora_inicio,
                        'hora_fin' => $request->hora_fin,
                        'duracion_cita' => $request->duracion_cita,
                        'estado' => 'ACTIVO',
                    ]);
                });
            });
        } catch (ConcreteScheduleConflict $exception) {
            return response()->json([
                'code' => 0,
                'message' => $this->concreteConflictMessage($exception->dates()),
                'conflict_dates' => $exception->dates(),
            ], 422);
        }

        $dates = $created->map(function (DoctorSchedule $row) {
            return substr((string) $row->fecha_cita, 0, 10);
        })->all();

        return response()->json([
            'code' => 1,
            'msg' => 'Se programaron '.$created->count().' horarios correctamente.',
            'message' => 'Se programaron '.$created->count().' horarios correctamente.',
            'created' => $created->count(),
            'created_count' => $created->count(),
            'dates' => $dates,
            'schedule_ids' => $created->pluck('id')->all(),
        ]);
    }

    private function concreteConflictMessage(array $dates): string
    {
        $lines = collect($dates)->map(function ($date) {
            return Carbon::parse($date)->format('d/m');
        })->implode("\n- ");

        return "No se pudo aplicar el horario porque existen cruces.\nFechas con conflicto:\n- ".$lines;
    }

    //PARA ACTUALIZAR LOS DATOS DEL HORARIO DEL DOCTOR
    public function updateDoctorSchedule(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'doctor_schedule_id_edit' => 'required|exists:doctor_schedules,id',
            'doctor_id_edit' => 'required|exists:doctors,id',
            'dia_semana_edit' => 'nullable|integer|between:1,7',
            'fecha_cita_edit' => 'nullable|date',
            'hora_inicio_edit' => 'required|date_format:H:i',
            'hora_fin_edit' => 'required|date_format:H:i|after:hora_inicio_edit',
            'duracion_edit_cita' => 'required|integer|in:10,15,20,30,45,60',
            'site_id_edit' => 'nullable|exists:sites,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'  => 0,
                'error' => $validator->errors()->toArray()
            ]);
        }

        $doctor_schedule = DoctorSchedule::find($request->doctor_schedule_id_edit);
        if (!$doctor_schedule) {
            return response()->json([
                'code' => 2,
                'msg' => 'Horariono encontrado no encontrado'
            ]);
        }

        $isRecurring = $doctor_schedule->fecha_cita === null;
        $newDate = $request->fecha_cita_edit
            ? Carbon::parse($request->fecha_cita_edit)
            : null;

        if (! $isRecurring && $newDate === null) {
            return response()->json([
                'code' => 0,
                'error' => ['fecha_cita_edit' => ['La fecha es obligatoria para un horario puntual.']],
            ]);
        }

        $cambios = [
            'doctor_id'     => $request->doctor_id_edit,
            'dia_semana'    => $isRecurring
                ? (int) ($request->dia_semana_edit ?: $doctor_schedule->dia_semana)
                : $newDate->dayOfWeekIso,
            // Editing a weekly block updates that recurring row. It never fabricates a
            // dated exception, because the current schema cannot express an override.
            'fecha_cita' => $isRecurring ? null : $newDate->toDateString(),
            'hora_inicio'   => $request->hora_inicio_edit,
            'hora_fin'      => $request->hora_fin_edit,
            'duracion_cita' => $request->duracion_edit_cita
        ];

        // Solo se toca la sede si el formulario la envía: un formulario que no la incluye no
        // debe borrar la sede ya registrada en el bloque.
        if ($request->has('site_id_edit')) {
            $cambios['site_id'] = $request->site_id_edit ?: null;
        }

        $exito = $doctor_schedule->update($cambios);

        if ($exito) {
            return response()->json([
                'code' => 1,
                'msg' => "Horario actualizado correctamente"
            ]);
        } else {
            return response()->json([
                'code' => 0,
                'msg' => "Horario no actualizado"
            ]);
        }
    }

    //PARA DESACTIVAR EL HOARIO DEL DOCTOR
    public function deleteDoctorSchedule(Request $request)
    {
        $data = $request->validate([
            'id' => 'required|integer|exists:doctor_schedules,id',
        ]);

        $doctor_schedule = DoctorSchedule::findOrFail($data['id']);
        $exito = $doctor_schedule->update([
            'estado' => 'INACTIVO'
        ]);

        if ($exito) {
            return response()->json([
                'code' => 1,
                'msg' => "Horario inactivado"
            ]);
        } else {
            return response()->json([
                'code' => 0,
                'msg' => "Horario no se inactivo"
            ]);
        }
    }
}
