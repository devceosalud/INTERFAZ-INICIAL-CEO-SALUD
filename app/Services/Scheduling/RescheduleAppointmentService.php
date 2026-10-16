<?php

namespace App\Services\Scheduling;

use App\Exceptions\Scheduling\AppointmentConfigurationException;
use App\Exceptions\Scheduling\AppointmentSlotUnavailableException;
use App\Exceptions\Scheduling\AppointmentClassificationConfirmationRequired;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Support\Scheduling\AppointmentAgendaLifecycle;
use Illuminate\Support\Facades\DB;

class RescheduleAppointmentService
{
    public function __construct(private AppointmentSlotValidator $slots)
    {
    }

    public function reschedule(int $id, int $actorId, string $date, string $time, string $expectedDate, string $expectedTime, ?string $confirmedType = null): Appointment
    {
        // Hidden and absent IDs take the same path, before inspecting target fields.
        $original = Appointment::query()->visibleToAgendaUser($actorId)->find($id);
        abort_if($original === null, 404);

        $sourceIndication=\App\Models\AppointmentReevaluationIndication::where('bound_appointment_id',$id)->first();
        return DB::transaction(function () use ($original, $id, $actorId, $date, $time, $expectedDate, $expectedTime, $confirmedType,$sourceIndication) {
            Doctor::whereIn('id',array_unique(array_filter([$original->doctor_id,$sourceIndication?->doctor_id])))->orderBy('id')->lockForUpdate()->get();
            $doctor = Doctor::query()->lockForUpdate()->find($original->doctor_id);
            $appointment = Appointment::query()->visibleToAgendaUser($actorId)->lockForUpdate()->find($id);
            abort_if($appointment === null, 404);
            if (substr((string) $appointment->fecha_cita, 0, 10) !== $expectedDate
                || substr((string) $appointment->hora_cita, 0, 5) !== $expectedTime) {
                throw new AppointmentSlotUnavailableException('La cita cambió de fecha u hora. Actualiza la Agenda antes de reprogramar.');
            }
            if (!$doctor || $doctor->estado !== 'ACTIVO' || (int) $appointment->doctor_id !== (int) $doctor->id) {
                throw new AppointmentConfigurationException('El médico no está disponible para reprogramación.');
            }
            if (!in_array($appointment->estado_cita, ['PROGRAMADO', 'CONFIRMADO'], true) || (int) $appointment->duracion_cita < 1) {
                throw new AppointmentConfigurationException('Solo se reprograman citas programadas o confirmadas con duración definida.');
            }
            $siteId = $appointment->site_id ? (int) $appointment->site_id : null;
            $duration=(int)$appointment->duracion_cita;
            if((int)$appointment->duration_slots===2) $duration=app(DoubleAppointmentDuration::class)->resolve((int)$doctor->id,$date,$time,$siteId)['minutes'];
            $additional = $appointment->estado_agenda === AppointmentAgendaLifecycle::CONFIRMED
                && $appointment->tipo_agendamiento === AppointmentAgendaLifecycle::ADDITIONAL;
            $changes = ['fecha_cita' => $date, 'hora_cita' => $time, 'updated_by_user_id' => $actorId];
            if((int)$appointment->duration_slots===2) $changes['duracion_cita']=$duration;
            if ($appointment->estado_agenda === AppointmentAgendaLifecycle::PENDING_CONFIRMATION) {
                if ($confirmedType === AppointmentAgendaLifecycle::OFF_HOURS) {
                    throw new AppointmentSlotUnavailableException('La reserva privada se conserva como REGULAR; confirma primero el flujo de atención excepcional.');
                }
                $this->slots->assertValid((int) $doctor->id, $date, $time, $duration, $siteId, $id, false, true);
            } elseif ($additional) {
                $this->slots->assertValid((int) $doctor->id, $date, $time, $duration, $siteId, $id, true, true);
            } else {
                $targetType = $this->slots->destinationType((int) $doctor->id, $date, $time, $duration, $siteId);
                $this->slots->assertUnoccupied((int) $doctor->id, $date, $time, $duration, $id);
                $originalType = $appointment->tipo_agendamiento === AppointmentAgendaLifecycle::OFF_HOURS
                    ? AppointmentAgendaLifecycle::OFF_HOURS : AppointmentAgendaLifecycle::REGULAR;
                if ($targetType !== $originalType) {
                    if ($confirmedType !== $targetType) {
                        throw new AppointmentClassificationConfirmationRequired($targetType);
                    }
                    $changes['tipo_agendamiento'] = $targetType;
                } elseif ($confirmedType !== null && $confirmedType !== $targetType) {
                    throw new AppointmentSlotUnavailableException('El destino cambió de clasificación. Revisa el horario antes de continuar.');
                }
            }
            $indication=\App\Models\AppointmentReevaluationIndication::where('bound_appointment_id',$id)->lockForUpdate()->first();
            if($indication) {
                $assignment=app(\App\Services\Catalog\ActiveDoctorServiceResolver::class)->resolve((int)$appointment->doctor_id,(int)$appointment->service_id);
                $discount=$appointment->discount_type?['type'=>$appointment->discount_type,'value'=>$appointment->discount_value,'reason'=>$appointment->discount_reason]:[];
                // A free same-day rate has no ordinary discount; keep the original policy for subsequent dates.
                if($date===$indication->attended_on->toDateString()) $discount=[];
                $referral=$appointment->referral_origin?['origin'=>$appointment->referral_origin,'attested'=>true]:[];
                $quote=app(AppointmentPriceResolver::class)->resolve($assignment,(int)$appointment->patient_id,$actorId,$date,
                    $appointment->additional_rate_id,$discount,(int)$indication->id,$referral,$id,true);
                $position=app(\App\Services\Billing\AppointmentEconomicPosition::class)->forAppointment($appointment);
                if($quote['price_cents']!==$position['price_cents']) {
                    $hasVoucher=\App\Models\VoucherItem::whereIn('item_type',['cita',Appointment::class])->where('item_id',$id)->exists();
                    if($position['effective_cents']>0 || $position['paid_cents']>0 || $hasVoucher) {
                        throw \Illuminate\Validation\ValidationException::withMessages(['fecha_cita'=>'El cambio de fecha requiere un ajuste económico auditado porque existe dinero o un ticket. No se modificó la cita; solicita revisión.']);
                    }
                    if($appointment->estado_agenda===AppointmentAgendaLifecycle::CONFIRMED && $quote['price_cents']>0 && !$appointment->es_exonerado) {
                        throw \Illuminate\Validation\ValidationException::withMessages(['fecha_cita'=>'El nuevo precio requiere adelanto antes de conservar la confirmación. Guarda una reserva mediante el flujo autorizado; no se cambió la cita.']);
                    }
                }
                $changes+=['precio_programado'=>$quote['precio_programado'],'catalog_price'=>$quote['catalog_price'],
                    'saldo_pendiente'=>$appointment->es_exonerado?0:\App\Support\Billing\Money::decimal(max(0,$quote['price_cents']-$position['effective_cents'])),
                    'pricing_rule'=>$quote['pricing_rule'],'price_source'=>$quote['price_source'],'discount_amount'=>\App\Support\Billing\Money::decimal($quote['discount']['amount_cents'])];
                app(AppointmentHistory::class)->record($appointment,'PRECIO_REPROGRAMACION_REEVALUACION',$actorId,
                    ['metadata'=>['indication_id'=>$indication->id,'from_price'=>$appointment->precio_programado,'to_price'=>$quote['precio_programado'],'pricing_rule'=>$quote['pricing_rule']]]);
            }
            $former = $appointment->only(['fecha_cita', 'hora_cita', 'tipo_agendamiento','duracion_cita','duration_slots']);
            $appointment->update($changes);
            app(AppointmentHistory::class)->record($appointment, 'REPROGRAMADA', $actorId,
                ['metadata' => ['from' => $former, 'to' => $changes]]);

            return $appointment;
        }, 3);
    }
}
