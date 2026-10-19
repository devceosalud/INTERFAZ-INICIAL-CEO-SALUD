<?php

namespace App\Http\Controllers\Scheduling;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Site;
use App\Services\Billing\AppointmentEconomicPosition;
use App\Services\Scheduling\DoctorAvailabilityService;
use App\Support\Scheduling\AgendaQuery;
use App\Support\Scheduling\ManagementCsv;
use App\Support\Scheduling\ManagementXlsx;
use App\Support\Scheduling\SchedulingCapability;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exportaciones de lectura para gerencia. No reutilizar endpoints públicos
 * de disponibilidad para publicar datos identificables o económicos.
 */
class ManagementExportController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeExport($request);

        $today = Carbon::today(config('scheduling.operational_timezone', 'America/Lima'));

        return response()->view('scheduling.agenda.management-report', [
            'from' => $today->copy()->subDays(6)->toDateString(),
            'to' => $today->toDateString(),
            'doctors' => Doctor::orderBy('nombre')->get(['id', 'nombre']),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function appointments(Request $request, AppointmentEconomicPosition $economics): StreamedResponse
    {
        $filters = $this->filters($request);
        $this->audit($request, 'citas', $filters);

        return $this->download('citas_pacientes_economia', $filters, function ($handle) use ($filters, $economics) {
            ManagementCsv::begin($handle, [
                'ID cita', 'Número de cita', 'Fecha cita', 'Hora cita', 'Duración minutos',
                'Sede', 'Profesional', 'CMP', 'Especialidad', 'Servicio',
                'Estado agenda', 'Tipo agendamiento', 'Estado atención', 'Estado pago mostrado',
                'ID paciente', 'Historia clínica', 'Tipo documento paciente', 'Documento paciente',
                'Apellidos y nombres paciente', 'Teléfono paciente', 'Correo paciente',
                'Fecha nacimiento', 'Canal captación', 'Medio interacción',
                'Motivo consulta', 'Observaciones', 'Postoperatorio', 'Pie diabético con herida',
                'Usuario registró', 'Responsable actual', 'Última modificación por',
                'Fecha registro', 'Creada en', 'Actualizada en',
                'Precio programado S/', 'Precio catálogo S/', 'Tipo descuento',
                'Valor descuento', 'Monto descuento S/', 'Motivo descuento',
                'Exonerada', 'Autorizada por', 'Motivo exoneración',
                'Pago acreditado o snapshot S/', 'Fuente económica', 'Crédito aplicado S/',
                'Crédito utilizado S/', 'Devolución reservada S/', 'Saldo calculado S/',
                'Asegurada económicamente según regla', 'Asignación requiere revisión',
                'Método de pago legado', 'Número operación legado', 'Fecha exportación',
            ]);

            $this->appointmentQuery($filters)
                ->with([
                    'patient.channel:id,nombre', 'patient.interactionMedium:id,nombre',
                    'doctor.specialty:id,nombre', 'service:id,nombre', 'site:id,nombre',
                    'user:id,name', 'responsibleUser:id,name', 'updatedByUser:id,name',
                ])
                ->chunkById(120, function ($appointments) use ($handle, $economics) {
                    // Reutilizar la proyección de pagos; total_pagado de citas NO es la fuente universal.
                    $positions = $economics->forAppointments($appointments, []);
                    foreach ($appointments as $a) {
                        $p = $positions[$a->id] ?? [];
                        $patient = $a->patient;
                        $name = $patient ? trim(implode(' ', array_filter([
                            $patient->apellido_paterno, $patient->apellido_materno, $patient->nombre,
                        ]))) : '';
                        ManagementCsv::row($handle, [
                            $a->id, ManagementCsv::identifier($a->numero_cita), $a->fecha_cita, $a->hora_cita, $a->duracion_cita,
                            $a->site?->nombre, $a->doctor?->nombre, $a->doctor?->cmp,
                            $a->doctor?->specialty?->nombre, $a->service?->nombre,
                            $a->estado_agenda, $a->tipo_agendamiento, $a->estado_cita, $a->estado_pagado,
                            $a->patient_id, ManagementCsv::identifier($patient?->historia_clinica), $patient?->tipo_identificacion,
                            ManagementCsv::identifier($patient?->numero_identidad), $name, $patient?->telefono, $patient?->email,
                            $patient?->fecha_nacimiento, $patient?->channel?->nombre, $patient?->interactionMedium?->nombre,
                            $a->motivo_consulta, $a->observaciones, $a->is_postoperative ? 'SÍ' : 'NO',
                            $a->has_diabetic_wound ? 'SÍ' : 'NO',
                            $a->user?->name, $a->responsibleUser?->name, $a->updatedByUser?->name,
                            $a->fecha_registro, $a->created_at?->format('Y-m-d H:i:s'),
                            $a->updated_at?->format('Y-m-d H:i:s'),
                            ManagementCsv::decimal($a->precio_programado), ManagementCsv::decimal($a->catalog_price), $a->discount_type,
                            $a->discount_value, ManagementCsv::decimal($a->discount_amount), $a->discount_reason,
                            $a->es_exonerado ? 'SÍ' : 'NO', $a->autorizado_por, $a->motivo_exoneracion,
                            ManagementCsv::soles($p['paid_cents'] ?? 0),
                            $p['authority'] ?? '', ManagementCsv::soles($p['credit_cents'] ?? 0),
                            ManagementCsv::soles($p['outgoing_credit_cents'] ?? 0),
                            ManagementCsv::soles($p['refund_reserved_cents'] ?? 0),
                            ManagementCsv::soles($p['balance_cents'] ?? 0),
                            !empty($p['secured']) ? 'SÍ' : 'NO',
                            !empty($p['allocation_required']) ? 'SÍ' : 'NO',
                            $a->metodo_pago, ManagementCsv::identifier($a->numero_operacion),
                            now(config('scheduling.operational_timezone', 'America/Lima'))->format('Y-m-d H:i:s'),
                        ]);
                    }
                });
        });
    }

    public function payments(Request $request, AppointmentEconomicPosition $economics): StreamedResponse
    {
        $filters = $this->filters($request);
        $this->audit($request, 'pagos', $filters);

        return $this->download('pagos_citas', $filters, function ($handle) use ($filters, $economics) {
            ManagementCsv::begin($handle, [
                'ID cita', 'Número de cita', 'Fecha cita', 'Profesional', 'ID paciente',
                'Paciente', 'ID pago', 'Fecha pago', 'Método pago',
                'Importe pago real S/', 'ID voucher', 'Tipo comprobante',
                'Serie', 'Correlativo', 'Estado voucher', 'Fuente económica',
                'Asignación requiere revisión', 'Nota de conciliación',
            ]);

            $this->appointmentQuery($filters)
                ->with(['patient:id,nombre,apellido_paterno,apellido_materno', 'doctor:id,nombre'])
                ->chunkById(120, function ($appointments) use ($handle, $economics) {
                    $positions = $economics->forAppointments($appointments);
                    $ids = [];
                    foreach ($positions as $p) {
                        foreach ($p['payment_details'] ?? [] as $detail) {
                            $ids[] = $detail['id'];
                        }
                    }
                    $payments = \App\Models\Payment::query()
                        ->with('voucher:id,tipo_comprobante,serie,correlativo,estado')
                        ->whereIn('id', array_unique($ids))->get()->keyBy('id');

                    foreach ($appointments as $a) {
                        $p = $positions[$a->id] ?? [];
                        $name = $a->patient ? trim(implode(' ', array_filter([
                            $a->patient->apellido_paterno, $a->patient->apellido_materno,
                            $a->patient->nombre,
                        ]))) : '';
                        $details = $p['payment_details'] ?? [];
                        if ($details === []) {
                            // Do not report an old financial snapshot as an actual payment.
                            $note = !empty($p['allocation_required'])
                                ? 'Comprobante compartido o atribución ambigua; conciliar manualmente'
                                : (($p['authority'] ?? '') === 'LEGACY_SNAPSHOT'
                                    ? 'Snapshot heredado; sin transacción individual acreditada'
                                    : 'Sin pago individual atribuible');
                            ManagementCsv::row($handle, [
                                $a->id, $a->numero_cita, $a->fecha_cita, $a->doctor?->nombre,
                                $a->patient_id, $name, '', '', '', '', '', '', '', '', '',
                                $p['authority'] ?? '', !empty($p['allocation_required']) ? 'SÍ' : 'NO', $note,
                            ]);
                            continue;
                        }
                        foreach ($details as $detail) {
                            $payment = $payments->get($detail['id']);
                            ManagementCsv::row($handle, [
                                $a->id, $a->numero_cita, $a->fecha_cita, $a->doctor?->nombre,
                                $a->patient_id, $name, $detail['id'], $detail['recorded_at'],
                                $detail['method'], str_replace('.', ',', (string) $detail['amount']),
                                $payment?->voucher_id, $payment?->voucher?->tipo_comprobante,
                                $payment?->voucher?->serie, $payment?->voucher?->correlativo,
                                $payment?->voucher?->estado,
                                $p['authority'] ?? '', !empty($p['allocation_required']) ? 'SÍ' : 'NO',
                                'Pago individual atribuido según proyección económica',
                            ]);
                        }
                    }
                });
        });
    }

    public function slots(Request $request, DoctorAvailabilityService $availability): StreamedResponse
    {
        $filters = $this->filters($request);
        $this->audit($request, 'intervalos', $filters);

        return $this->download('intervalos_disponibilidad', $filters, function ($handle) use ($filters, $availability) {
            ManagementCsv::begin($handle, [
                'Fecha', 'ID profesional', 'Profesional', 'Especialidad', 'ID sede intervalo',
                'Sede intervalo', 'Hora inicio', 'Hora fin', 'Minutos intervalo',
                'Estado intervalo', 'Estado atención asociado', 'Total slots del día',
                'Slots ocupados del día', 'Slots libres del día', 'Porcentaje ocupación del día',
                'Observación',
            ]);
            $sites = Site::pluck('nombre', 'id');
            $from = Carbon::parse($filters['from']);
            $to = Carbon::parse($filters['to']);

            $doctors = Doctor::query()->with('specialty:id,nombre')
                ->when(!$filters['doctor_id'], fn ($q) => $q->where('estado', 'ACTIVO'))
                ->when($filters['doctor_id'], fn ($q) => $q->whereKey($filters['doctor_id']))
                ->orderBy('id');

            $doctors->chunkById(8, function ($group) use ($handle, $filters, $sites, $from, $to, $availability) {
                $agenda = new AgendaQuery($group->pluck('id')->all(), $from, $to, $filters['site_id']);
                $days = $availability->forRange($agenda);
                foreach ($group as $doctor) {
                    foreach ($agenda->dates() as $date) {
                        $day = $days->get($doctor->id.'|'.$date->toDateString());
                        $slots = $day?->slots() ?? collect();
                        $total = $slots->count();
                        $occupied = $slots->reject->isAvailable()->count();
                        $free = $total - $occupied;
                        $percent = $total ? number_format($occupied * 100 / $total, 2, ',', '') : '';
                        if (!$total) {
                            ManagementCsv::row($handle, [
                                $date->toDateString(), $doctor->id, $doctor->nombre,
                                $doctor->specialty?->nombre, $filters['site_id'],
                                $sites->get($filters['site_id']), '', '', '', 'SIN_HORARIO',
                                '', 0, 0, 0, '', 'No existen intervalos en el horario filtrado',
                            ]);
                            continue;
                        }
                        foreach ($slots as $slot) {
                            ManagementCsv::row($handle, [
                                $date->toDateString(), $doctor->id, $doctor->nombre,
                                $doctor->specialty?->nombre, $slot->siteId(),
                                $sites->get($slot->siteId()), $slot->range()->start()->format('H:i'),
                                $slot->range()->end()->format('H:i'), $slot->range()->minutes(),
                                $slot->status(), $slot->appointmentState(), $total, $occupied,
                                $free, $percent, 'Ocupación operativa; no implica pago acreditado',
                            ]);
                        }
                    }
                }
            });
        });
    }

    private function authorizeExport(Request $request): void
    {
        $user = $request->user();
        abort_unless($user && $user->is_active && $user->hasRole('ADMINISTRADOR')
            && $user->can(SchedulingCapability::VIEW_AUDIT), 403);
    }

    private function filters(Request $request): array
    {
        $this->authorizeExport($request);
        $data = $request->validate([
            'from' => 'required|date_format:Y-m-d',
            'to' => 'required|date_format:Y-m-d|after_or_equal:from',
            'doctor_id' => 'nullable|integer|exists:doctors,id',
            'site_id' => 'prohibited',
            'format' => 'sometimes|in:csv,xlsx',
        ]);
        if (Carbon::parse($data['from'])->diffInDays(Carbon::parse($data['to'])) > 30) {
            throw ValidationException::withMessages(['to' => 'El rango máximo es de 31 días, inclusive.']);
        }
        return [
            'from' => $data['from'], 'to' => $data['to'],
            'doctor_id' => isset($data['doctor_id']) ? (int) $data['doctor_id'] : null,
            'site_id' => null,
        ];
    }

    private function appointmentQuery(array $filters)
    {
        // Preserve the current visibility contract, including private pending reservations.
        return Appointment::query()
            ->visibleToAgendaUser((int) auth()->id())
            ->whereBetween('fecha_cita', [$filters['from'], $filters['to']])
            ->when($filters['doctor_id'], fn ($q) => $q->where('doctor_id', $filters['doctor_id']))
            ->when($filters['site_id'], fn ($q) => $q->where('site_id', $filters['site_id']))
            ->orderBy('id');
    }

    private function download(string $kind, array $filters, callable $write): StreamedResponse
    {
        // The same audited report produces either the legacy CSV or an Office XLSX
        // with real column filters, a frozen heading, numeric amounts and text-safe IDs.
        $format = request()->query('format', 'csv');
        $xlsx = $format === 'xlsx';
        $extension = $xlsx ? 'xlsx' : 'csv';
        $filename = 'ceosalud_'.$kind.'_'.$filters['from'].'_'.$filters['to'].'.'.$extension;

        return response()->streamDownload(function () use ($write, $xlsx) {
            if (!$xlsx) {
                $output = fopen('php://output', 'wb');
                try {
                    $write($output);
                } finally {
                    fclose($output);
                }
                return;
            }

            // The CSV projection is reused exactly to avoid duplicating financial,
            // visibility and scheduling calculations. Streams may spill to system temp.
            $intermediate = fopen('php://temp/maxmemory:2097152', 'w+b');
            try {
                $write($intermediate);
                $output = fopen('php://output', 'wb');
                try {
                    ManagementXlsx::fromCsv($intermediate, $output, 'CEO Salud');
                } finally {
                    fclose($output);
                }
            } finally {
                fclose($intermediate);
            }
        }, $filename, [
            'Content-Type' => $xlsx ? ManagementXlsx::contentType() : 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function audit(Request $request, string $kind, array $filters): void
    {
        Log::notice('management_report.download', [
            'format' => $request->query('format', 'csv'),
            'actor_id' => $request->user()->id,
            'report' => $kind,
            'from' => $filters['from'], 'to' => $filters['to'],
            'doctor_id' => $filters['doctor_id'], 'site_id' => $filters['site_id'],
        ]);
    }
}
