<?php
namespace App\Services\Patients;

use App\Models\Patient;
use App\Http\Controllers\Patients\OperationalPatientController;

/** Stable master/responsible snapshot, independent of timestamp resolution. */
final class PatientRecordRevision
{
    public static function for(Patient $patient): string
    {
        $patient->unsetRelation('responsibles');
        return hash('sha256', json_encode(OperationalPatientController::patientPayload($patient), JSON_UNESCAPED_UNICODE));
    }
}
