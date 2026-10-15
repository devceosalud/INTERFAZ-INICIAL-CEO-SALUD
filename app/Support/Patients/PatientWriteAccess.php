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
}
