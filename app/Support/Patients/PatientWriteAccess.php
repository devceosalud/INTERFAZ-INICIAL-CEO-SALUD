<?php

namespace App\Support\Patients;

use App\Models\User;

/**
 * Quién puede completar o corregir la ficha maestra.
 *
 * No existe una permission reutilizable (patient.create, patient.update,
 * patient.manage ni equivalente). La regla de negocio queda centralizada
 * en estos roles hasta que el proyecto defina esa capability.
 *
 * ADMINISTRADOR también puede crear y completar fichas operativas.
 * Otros perfiles MVP con acceso, lectura, creación y edición efectivos pueden
 * escribir fichas. Esta política no concede capacidades ni excepciones financieras.
 */
final class PatientWriteAccess
{
    public const ROLES = ['ADMINISTRADOR', 'ADMISION', 'RECEPCION', 'COMERCIAL'];

    public static function middleware(): string
    {
        return 'mvp-operational:write';
    }

    public static function allows(?User $user): bool
    {
        return \App\Support\Scheduling\MvpOperationalAccess::writesPatients($user);
    }

    public const OWNERSHIP_MESSAGE = 'Solo el usuario que creó este registro puede modificar sus datos. Puedes consultarlo y continuar las acciones operativas autorizadas.';

    /** user_id is immutable creation provenance; commercial assignment is not ownership. */
    public static function owns(?User $user, $record): bool
    {
        if (!$user || !$user->is_active) return false;
        if ($user->hasRole('ADMINISTRADOR') || !$user->hasAnyRole(['ADMISION','COMERCIAL'])) return true;
        return (int)$record->user_id > 0 && (int)$record->user_id === (int)$user->id;
    }

    public static function allowsRecord(?User $user, \App\Models\Patient $patient, ?\App\Models\Appointment $appointment=null): bool
    {
        return self::allows($user) && self::owns($user,$patient) && (!$appointment || self::owns($user,$appointment));
    }

    public static function assertRecord(?User $user, \App\Models\Patient $patient, ?\App\Models\Appointment $appointment=null): void
    {
        abort_unless(self::allowsRecord($user,$patient,$appointment),403,self::OWNERSHIP_MESSAGE);
    }
}
