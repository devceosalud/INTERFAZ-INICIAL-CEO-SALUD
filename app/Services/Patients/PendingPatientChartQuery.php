<?php

namespace App\Services\Patients;

use App\Models\Appointment;
use App\Support\Scheduling\AppointmentVisibility;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Derived operational queue for patients who still have a visit ahead of them.
 * "Ahead" uses the clinic wall clock in config scheduling.operational_timezone.
 * Stored fecha_cita and hora_cita stay as written; they are not converted.
 *
 * Completeness is calculated from the patient row and the existing minor rule.
 * Email and the other recommended fields never keep a row in the queue.
 */
class PendingPatientChartQuery
{
    public const BLOCKING_TEXT = [
        'telefono' => 'Teléfono',
        'direccion' => 'Dirección',
        'estado_civil' => 'Estado civil',
    ];

    public const RECOMMENDED_TEXT = [
        'email' => 'correo',
        'ocupacion' => 'ocupación',
        'grado_instruccion' => 'grado de instrucción',
        'familiar_contacto' => 'familiar de contacto',
    ];

    /**
     * Patients with a future, still-relevant appointment and a blocking gap.
     *
     * @param Builder<\App\Models\Patient> $query
     * @return Builder<\App\Models\Patient>
     */
    public static function apply(Builder $query, int $actorId, ?CarbonInterface $now = null): Builder
    {
        $now = self::clock($now);

        return self::missingBlocking($query, $now)
            ->whereExists(function (QueryBuilder $appointments) use ($now, $actorId): void {
                $appointments->selectRaw('1')->from('appointments');
                self::futureRelevant($appointments, $now, $actorId);
            });
    }

    /**
     * Blocking gaps only. Used when Admisión looks up a document, including
     * patients who do not have a future appointment.
     *
     * @param Builder<\App\Models\Patient> $query
     * @return Builder<\App\Models\Patient>
     */
    public static function missingBlocking(Builder $query, ?CarbonInterface $now = null): Builder
    {
        $adultOn = self::clock($now)->copy()->subYears(18)->toDateString();

        return $query->where(function (Builder $pending) use ($adultOn): void {
            $pending->where(function (Builder $missing): void {
                $missing->whereNull('patients.fecha_nacimiento')
                    ->orWhereNull('patients.channel_id');

                foreach (array_keys(self::BLOCKING_TEXT) as $column) {
                    $missing->orWhereRaw("trim(coalesce(patients.{$column}, '')) = ''");
                }
            })->orWhere(function (Builder $minor) use ($adultOn): void {
                $minor->whereNotNull('patients.fecha_nacimiento')
                    ->where('patients.fecha_nacimiento', '>', $adultOn)
                    ->whereNotExists(function ($responsibles): void {
                        $responsibles->selectRaw('1')
                            ->from('responsibles')
                            ->whereColumn('responsibles.patient_id', 'patients.id');
                    });
            });
        });
    }

    /**
     * @param Builder<\App\Models\Patient> $query
     * @return Builder<\App\Models\Patient>
     */
    public static function withRelevantAppointment(Builder $query, int $actorId, ?CarbonInterface $now = null): Builder
    {
        $now = self::clock($now);
        $upcoming = DB::table('appointments as upcoming')->select('upcoming.id');
        self::futureRelevant($upcoming, $now, $actorId, 'upcoming');

        return $query->selectSub(
            $upcoming->orderBy('upcoming.fecha_cita')->orderBy('upcoming.hora_cita')
                ->orderBy('upcoming.id')->limit(1),
            'relevant_appointment_id'
        );
    }

    /**
     * @return list<string>
     */
    public static function blockingLabels(object $patient, bool $hasResponsible): array
    {
        $labels = [];

        if (blank($patient->fecha_nacimiento ?? null)) {
            $labels[] = 'Fecha de nacimiento';
        }

        foreach (self::BLOCKING_TEXT as $column => $label) {
            if (self::blankText($patient->{$column} ?? null)) {
                $labels[] = $label;
            }
        }

        if (($patient->channel_id ?? null) === null) {
            $labels[] = 'Canal de captación';
        }

        $birthDate = $patient->fecha_nacimiento ?? null;
        if (!blank($birthDate) && Carbon::parse($birthDate)->age < 18 && !$hasResponsible) {
            $labels[] = 'Responsable';
        }

        return $labels;
    }

    /**
     * @return list<string>
     */
    public static function recommendedLabels(object $patient): array
    {
        $labels = [];

        foreach (self::RECOMMENDED_TEXT as $column => $label) {
            if (self::blankText($patient->{$column} ?? null)) {
                $labels[] = $label;
            }
        }

        return $labels;
    }

    public static function visitLabel(?Appointment $appointment): string
    {
        if ($appointment === null) {
            return '—';
        }

        $date = Carbon::parse($appointment->fecha_cita)->format('d/m/Y');
        $time = Carbon::parse($appointment->hora_cita)->format('H:i');

        return 'Próxima '.$date.' '.$time;
    }

    private static function futureRelevant(QueryBuilder $appointments, CarbonInterface $now, int $actorId, string $table = 'appointments'): void
    {
        $day = $now->toDateString();
        $time = $now->format('H:i:s');

        AppointmentVisibility::apply($appointments, $actorId, $table)
            ->whereColumn($table.'.patient_id', 'patients.id')
            ->whereNotIn($table.'.estado_cita', ['CANCELADO', 'NO_ASISTIO', 'RETIRO'])
            ->where(function (QueryBuilder $future) use ($day, $time, $table): void {
                $future->where($table.'.fecha_cita', '>', $day)
                    ->orWhere(function (QueryBuilder $laterToday) use ($day, $time, $table): void {
                        $laterToday->where($table.'.fecha_cita', $day)
                            ->where($table.'.hora_cita', '>', $time);
                    });
            });
    }

    private static function clock(?CarbonInterface $now): Carbon
    {
        $zone = (string) config('scheduling.operational_timezone', 'America/Lima');

        if ($now instanceof CarbonInterface) {
            return Carbon::instance($now)->timezone($zone);
        }

        return Carbon::now($zone);
    }

    private static function blankText(mixed $value): bool
    {
        return trim((string) ($value ?? '')) === '';
    }
}
