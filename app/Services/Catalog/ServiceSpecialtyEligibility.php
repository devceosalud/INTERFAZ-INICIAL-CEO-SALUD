<?php

namespace App\Services\Catalog;

use App\Models\{Doctor, DoctorService, Service};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
/** Primary specialty, explicitly shared service, or a specifically audited professional exception. */
final class ServiceSpecialtyEligibility
{
    public function compatible(Doctor $doctor, Service $service, bool $lock = false): bool
    {
        if (!$doctor->specialty_id || !$service->specialty_id) {
            return false;
        }
        if ((int) $doctor->specialty_id === (int) $service->specialty_id) {
            return true;
        }
        $shared = DB::table('service_specialties')->where('service_id', $service->id)->where('specialty_id', $doctor->specialty_id)->where('estado', 'ACTIVO');
        return $lock ? $shared->lockForUpdate()->first() !== null : $shared->exists();
    }
    public function allowed(DoctorService $assignment, bool $lock = false): bool
    {
        $assignmentQuery = DoctorService::whereKey($assignment->id);
        if ($lock) {
            $assignmentQuery->lockForUpdate();
        }
        $assignment = $assignmentQuery->first() ?? $assignment;
        $doctor = Doctor::find($assignment->doctor_id);
        $serviceQuery = Service::whereKey($assignment->service_id);
        if ($lock) {
            $serviceQuery->lockForUpdate();
        }
        $service = $serviceQuery->first();
        if (!$doctor || !$service || $doctor->estado !== 'ACTIVO' || $service->estado !== 'ACTIVO') {
            return false;
        }
        if ($this->compatible($doctor, $service, $lock)) {
            return true;
        }
        return $assignment->specialty_exception_approved_by_user_id && trim((string) $assignment->specialty_exception_reason) !== '' && (int) $assignment->specialty_exception_doctor_specialty_id === (int) $doctor->specialty_id && (int) $assignment->specialty_exception_service_specialty_id === (int) $service->specialty_id;
    }
    public function assertAllowed(DoctorService $assignment, bool $lock = false): void
    {
        if (!$this->allowed($assignment, $lock)) {
            throw ValidationException::withMessages(['service_id' => 'Servicio incompatible con la especialidad del profesional. Requiere reconciliación o autorización administrativa explícita.']);
        }
    }
    public function scope(Builder $query): Builder
    {
        return $query->whereExists(function ($q) {
            $q->selectRaw('1')->from('doctors as eligible_doctor')->join('services as eligible_service', fn($join) => $join->on('eligible_service.id', '=', 'doctor_services.service_id'))->whereColumn('eligible_doctor.id', 'doctor_services.doctor_id')->where(function ($allowed) {
                $allowed->whereColumn('eligible_doctor.specialty_id', 'eligible_service.specialty_id')->orWhereExists(fn($shared) => $shared->selectRaw('1')->from('service_specialties')->whereColumn('service_specialties.service_id', 'eligible_service.id')->whereColumn('service_specialties.specialty_id', 'eligible_doctor.specialty_id')->where('service_specialties.estado', 'ACTIVO'))->orWhere(fn($exception) => $exception->whereNotNull('doctor_services.specialty_exception_approved_by_user_id')->whereNotNull('doctor_services.specialty_exception_reason')->where('doctor_services.specialty_exception_reason', '<>', '')->whereColumn('doctor_services.specialty_exception_doctor_specialty_id', 'eligible_doctor.specialty_id')->whereColumn('doctor_services.specialty_exception_service_specialty_id', 'eligible_service.specialty_id'));
            });
        });
    }
}
