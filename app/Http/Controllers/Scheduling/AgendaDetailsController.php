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
            if ($appointment) {
                \App\Models\Doctor::whereKey($appointment->doctor_id)->lockForUpdate()->firstOrFail();
                $appointment=Appointment::visibleToAgendaUser($request->user()->id)->whereKey($appointment->id)
                    ->where('patient_id',$patientId)->lockForUpdate()->firstOr(fn()=>abort(409,'La cita cambió. Actualiza antes de corregir la ficha.'));
            }
            $patient = Patient::whereKey($patientId)->lockForUpdate()->firstOrFail();
            PatientWriteAccess::assertRecord($request->user(),$patient,$appointment);
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
        $actor=$request->user();
        abort_unless($actor->is_active && $actor->can(Capability::VIEW),403);
        $original=Appointment::visibleToAgendaUser($actor->id)->whereKey($appointmentId)->firstOr(fn()=>abort(404));
        abort_unless(\App\Services\Scheduling\AppointmentManagement::canEditDetails($original,$actor),403,PatientWriteAccess::OWNERSHIP_MESSAGE);
        if ($request->hasAny(['has_diabetic_wound','is_postoperative'])) abort_unless($actor->can(Capability::UPDATE),403);
        $allowed=['motivo_consulta','observaciones','has_diabetic_wound','is_postoperative','attendance_mode','confirmed_attendance_change','request_key','expected_revision','_token'];
        abort_if(($request->hasAny(['request_key','expected_revision','attendance_mode']) && array_diff(array_keys($request->all()),$allowed)) || $request->has('discount'),422,'Esta operación solo actualiza datos operativos autorizados; no modifica dinero ni horarios.');
        foreach(['motivo_consulta','observaciones'] as $field) if(is_string($request->input($field)))
            $request->merge([$field=>\App\Support\Scheduling\OperationalText::trim($request->input($field))]);
        $data=$request->validate([
            'motivo_consulta'=>'sometimes|nullable|string|max:2000','observaciones'=>'sometimes|nullable|string|max:2000',
            'has_diabetic_wound'=>'sometimes|boolean','is_postoperative'=>'sometimes|boolean',
            'attendance_mode'=>'sometimes|in:PRESENCIAL,TELECONSULTA','confirmed_attendance_change'=>'sometimes|accepted',
            'request_key'=>'required_with:attendance_mode,expected_revision|uuid',
            'expected_revision'=>'required_with:attendance_mode,request_key|string|size:64|regex:/^[a-f0-9]+$/',
        ]);
        if(array_key_exists('attendance_mode',$data)) abort_unless($actor->hasAnyRole(['ADMINISTRADOR','ADMISION']),403,'Tu rol no está autorizado para cambiar la modalidad de atención.');
        return DB::transaction(function () use ($data,$original,$actor,$appointmentId) {
            $op=null;
            if(isset($data['request_key'])) {
                $hash=hash('sha256',json_encode(['APPOINTMENT_DETAILS',$appointmentId,$data]));
                DB::table('appointment_operations')->upsert([['actor_user_id'=>$actor->id,'request_key'=>$data['request_key'],
                    'payload_hash'=>$hash,'created_at'=>now(),'updated_at'=>now()]],['actor_user_id','request_key'],['request_key']);
                $op=DB::table('appointment_operations')->where('actor_user_id',$actor->id)->where('request_key',$data['request_key'])->lockForUpdate()->first();
                abort_unless(hash_equals($op->payload_hash,$hash),409,'La solicitud ya corresponde a otra operación.');
            }
            \App\Models\Doctor::whereKey($original->doctor_id)->lockForUpdate()->firstOrFail();
            $appointment=Appointment::visibleToAgendaUser($actor->id)->whereKey($appointmentId)->lockForUpdate()->firstOr(fn()=>abort(404));
            abort_if((int)$appointment->doctor_id!==(int)$original->doctor_id,409,'La cita cambió de profesional.');
            abort_unless(\App\Services\Scheduling\AppointmentManagement::canEditDetails($appointment,$actor),403,PatientWriteAccess::OWNERSHIP_MESSAGE);
            if(!$op?->appointment_id) {
                if(isset($data['expected_revision'])) abort_unless(hash_equals(\App\Services\Scheduling\AppointmentManagement::revision($appointment),$data['expected_revision']),409,'La cita cambió en otra sesión. Actualiza antes de guardar; se conservan tus cambios.');
                $changes=array_intersect_key($data,array_flip(['motivo_consulta','observaciones','has_diabetic_wound','is_postoperative']));
                $changes=array_filter($changes,fn($value)=>$value!==null && $value!=='');
                $modeChanged=isset($data['attendance_mode']) && $data['attendance_mode']!==($appointment->attendance_mode??'PRESENCIAL');
                if($modeChanged) {
                    abort_unless(\App\Services\Scheduling\AppointmentManagement::canChangeMode($appointment,$actor),403,'No puedes cambiar la modalidad después de iniciar atención o sin autorización.');
                    abort_unless(!empty($data['confirmed_attendance_change']),422,'Confirma el cambio de modalidad.');
                    $from=$appointment->attendance_mode??'PRESENCIAL';$changes['attendance_mode']=$data['attendance_mode'];
                }
                if($changes) {
                    $appointment->update($changes+['updated_by_user_id'=>$actor->id]);
                    app(AppointmentHistory::class)->record($appointment,'DATOS_OPERATIVOS_ACTUALIZADOS',$actor->id,['metadata'=>['fields'=>array_keys($changes),'operation_id'=>$op?->id]]);
                    if($modeChanged) app(AppointmentHistory::class)->record($appointment,'MODALIDAD_ATENCION_ACTUALIZADA',$actor->id,
                        ['metadata'=>['from'=>$from,'to'=>$data['attendance_mode'],'operation_id'=>$op?->id]]);
                }
                if($op) DB::table('appointment_operations')->where('id',$op->id)->update(['appointment_id'=>$appointmentId,'updated_at'=>now()]);
            }
            return response()->json(['message'=>'Datos de la cita guardados.','management'=>\App\Services\Scheduling\AppointmentManagement::payload($appointment->fresh(),$actor)]
                +$appointment->only(['motivo_consulta','observaciones','is_postoperative','has_diabetic_wound','attendance_mode']));
        },3);
    }
}
