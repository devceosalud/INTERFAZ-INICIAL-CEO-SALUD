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
 * Esta política no concede capacidades de citas, pagos ni exoneraciones.
 */
final class PatientWriteAccess
{
    public const ROLES = ['ADMINISTRADOR', 'ADMISION', 'RECEPCION', 'COMERCIAL'];

    public static function middleware(): string
    {
        return 'role:'.implode('|', self::ROLES);
    }

    public static function allows(?User $user): bool
    {
        return $user !== null && $user->hasAnyRole(self::ROLES);
    }
}
