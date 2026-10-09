<?php

namespace App\Http\Controllers\Scheduling;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Patients\OperationalPatientController;
use App\Models\Appointment;
use App\Models\Patient;
use App\Services\Scheduling\AppointmentHistory;
use App\Support\Patients\PatientWriteAccess;
use App\Support\Scheduling\SchedulingCapability as Capability;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Partial updates only: never creates appointments or changes their economics. */
class AgendaDetailsController extends Controller
{
    public function patient(Request $request, int $patientId)
    {
        abort_unless(PatientWriteAccess::allows($request->user()), 403);
        $data = $request->validate([
            'appointment_id' => 'sometimes|nullable|integer',
            'telefono' => ['sometimes', 'nullable', 'string', 'max:32', 'regex:/\A\+?[0-9][0-9 -]{5,30}\z/'],
            'telefono_secundario' => ['sometimes', 'nullable', 'string', 'max:32', 'regex:/\A\+?[0-9][0-9 -]{5,30}\z/'],
            'channel_id' => ['sometimes', 'nullable', 'integer', Rule::exists('channels', 'id')->where('estado', 'ACTIVO')],
            'interaction_medium_id' => ['sometimes', 'nullable', 'integer', Rule::exists('interaction_media', 'id')->where('estado', 'ACTIVO')],
        ]);
        $appointment = null;
        if (!empty($data['appointment_id'])) {
            $appointment = Appointment::visibleToAgendaUser($request->user()->id)
                ->whereKey($data['appointment_id'])->where('patient_id', $patientId)->firstOr(fn () => abort(404));
        }
        unset($data['appointment_id']);
        // Empty form controls are not an instruction to erase existing patient data.
        $data = array_filter($data, fn ($value) => $value !== null && $value !== '');
        $patient = DB::transaction(function () use ($patientId, $data, $request, $appointment) {
            $patient = Patient::whereKey($patientId)->lockForUpdate()->firstOrFail();
            $patient->update($data);
            if ($data && $appointment) {
                app(AppointmentHistory::class)->record($appointment, 'CONTACTO_PACIENTE_ACTUALIZADO', $request->user()->id,
                    ['metadata' => ['fields' => array_keys($data)]]);
            } elseif ($data) {
                \Illuminate\Support\Facades\Log::info('agenda.patient_contact_updated',
                    ['actor_user_id' => $request->user()->id, 'patient_id' => $patientId, 'fields' => array_keys($data)]);
            }
            return $patient;
        });
        return response()->json(['message' => 'Datos del paciente guardados.',
            'patient' => OperationalPatientController::patientPayload($patient)]);
    }

    public function notes(Request $request, int $appointmentId)
    {
        return DB::transaction(function () use ($request, $appointmentId) {
            $appointment = Appointment::visibleToAgendaUser($request->user()->id)
                ->whereKey($appointmentId)->lockForUpdate()->firstOr(fn () => abort(404));
            abort_unless($request->user()->can(Capability::UPDATE)
                || ($request->user()->can(Capability::CREATE)
                    && (int) ($appointment->responsible_user_id ?? $appointment->user_id) === (int) $request->user()->id), 403);
            $data = $request->validate([
                'motivo_consulta' => 'sometimes|nullable|string|max:2000',
                'observaciones' => 'sometimes|nullable|string|max:2000',
            ]);
            $data = array_filter($data, fn ($value) => $value !== null && $value !== '');
            if ($data) {
                $appointment->update($data + ['updated_by_user_id' => $request->user()->id]);
                app(AppointmentHistory::class)->record($appointment, 'DATOS_OPERATIVOS_ACTUALIZADOS', $request->user()->id,
                    ['metadata' => ['fields' => array_keys($data)]]);
            }
            return response()->json(['message' => 'Motivo y observación guardados.']
                + $appointment->only(['motivo_consulta', 'observaciones']));
        });
    }
}
