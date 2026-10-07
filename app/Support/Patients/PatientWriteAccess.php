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
 * COMERCIAL forma parte de la regla. En la base local conocida ese rol
 * todavía no está creado: hay que reconciliarlo antes de desplegar.
 */
final class PatientWriteAccess
{
    public const ROLES = ['ADMISION', 'RECEPCION', 'COMERCIAL'];

    public static function middleware(): string
    {
        return 'role:'.implode('|', self::ROLES);
    }

    public static function allows(?User $user): bool
    {
        return $user !== null && $user->hasAnyRole(self::ROLES);
    }
}
