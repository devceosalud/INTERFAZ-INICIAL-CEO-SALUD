<?php

namespace App\Services\Catalog;

use App\Models\DoctorService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/** Resolves current assignments only; booked prices remain Appointment snapshots. */
class ActiveDoctorServiceResolver
{
    public const MISSING = 'El servicio seleccionado no tiene una asignación activa para este médico.';
    public const AMBIGUOUS = 'Este médico tiene más de una asignación activa para el servicio seleccionado. Deje solo una antes de agendar.';

    public function query(): Builder
    {
        return DoctorService::query()->where('estado', 'ACTIVO')
            ->whereHas('doctor', fn ($q) => $q->where('estado', 'ACTIVO'))
            ->whereHas('service', fn ($q) => $q->where('estado', 'ACTIVO'));
    }

    public function uniqueAssignment(Collection $matches): DoctorService
    {
        if ($matches->count() !== 1) {
            throw ValidationException::withMessages(['service_id' => $matches->isEmpty() ? self::MISSING : self::AMBIGUOUS]);
        }

        return $matches->first();
    }

    public function resolve(int $doctorId, int $serviceId): DoctorService
    {
        return $this->uniqueAssignment($this->query()->where('doctor_id', $doctorId)
            ->where('service_id', $serviceId)->limit(2)->get());
    }

    public function resolveAssignment(int $assignmentId, ?int $doctorId = null): DoctorService
    {
        $row = $this->query()->whereKey($assignmentId)->first();
        if (!$row || ($doctorId !== null && (int) $row->doctor_id !== $doctorId)) {
            throw ValidationException::withMessages(['service_id' => self::MISSING]);
        }

        return $this->resolve((int) $row->doctor_id, (int) $row->service_id);
    }

    /** Check the full matching set before pagination can hide a duplicate. */
    public function assertUniquePairs(Collection $rows): void
    {
        foreach ($rows->groupBy(fn ($row) => $row->doctor_id.'|'.$row->service_id) as $matches) {
            $this->uniqueAssignment($matches);
        }
    }
}
