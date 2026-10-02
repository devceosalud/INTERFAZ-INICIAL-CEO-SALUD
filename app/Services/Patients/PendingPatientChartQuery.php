<?php

namespace App\Services\Patients;

use App\Models\Appointment;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

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
    public static function apply(Builder $query, ?CarbonInterface $now = null): Builder
    {
        $now = self::clock($now);

        return self::missingBlocking($query, $now)
            ->whereExists(function ($appointments) use ($now): void {
                self::futureRelevant($appointments, $now);
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
    public static function withRelevantAppointment(Builder $query, ?CarbonInterface $now = null): Builder
    {
        $now = self::clock($now);
        $day = $now->toDateString();
        $time = $now->format('H:i:s');

        return $query->selectRaw(
            '(SELECT upcoming.id FROM appointments AS upcoming
                WHERE upcoming.patient_id = patients.id
                  AND upcoming.estado_cita NOT IN (?, ?)
                  AND (
                    upcoming.fecha_cita > ?
                    OR (upcoming.fecha_cita = ? AND upcoming.hora_cita > ?)
                  )
                ORDER BY upcoming.fecha_cita ASC, upcoming.hora_cita ASC, upcoming.id ASC
                LIMIT 1) AS relevant_appointment_id',
            ['CANCELADO', 'NO_ASISTIO', $day, $day, $time]
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

    private static function futureRelevant(object $appointments, CarbonInterface $now): void
    {
        $day = $now->toDateString();
        $time = $now->format('H:i:s');

        $appointments->selectRaw('1')
            ->from('appointments')
            ->whereColumn('appointments.patient_id', 'patients.id')
            ->whereNotIn('appointments.estado_cita', ['CANCELADO', 'NO_ASISTIO'])
            ->where(function ($future) use ($day, $time): void {
                $future->where('appointments.fecha_cita', '>', $day)
                    ->orWhere(function ($laterToday) use ($day, $time): void {
                        $laterToday->where('appointments.fecha_cita', $day)
                            ->where('appointments.hora_cita', '>', $time);
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
