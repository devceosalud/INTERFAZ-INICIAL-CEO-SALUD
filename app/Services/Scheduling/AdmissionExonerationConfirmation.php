<?php
namespace App\Services\Scheduling;

use App\Models\{Appointment,Doctor,User};
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Support\Facades\DB;

/** Explicit confirmation of an already authorized exemption. Does not record payment. */
final class AdmissionExonerationConfirmation
{
    public static function eligible(Appointment $a,User $u): bool
    {
        return $u->is_active && $u->can(C::MVP_ACCESS) && $u->can(C::VIEW) && $u->can(C::CREATE)
            && ($a->tipo_agendamiento!=='ADICIONAL' || $u->can(C::CREATE_ADDITIONAL))
            && $a->estado_agenda==='PENDIENTE_CONFIRMACION' && $a->es_exonerado
            && trim($a->autorizado_por??'')!=='' && trim($a->motivo_exoneracion??'')!=='';
    }
    public function confirm(int $id,User $u,array $data): Appointment
    {
        abort_unless($u->fresh()?->is_active && $u->can(C::CREATE) && $u->can(C::VIEW),403);
        $original=Appointment::visibleToAgendaUser($u->id)->findOrFail($id);
        return DB::transaction(function()use($original,$id,$u,$data){
            Doctor::whereKey($original->doctor_id)->lockForUpdate()->firstOrFail();
            $a=Appointment::visibleToAgendaUser($u->id)->whereKey($id)->lockForUpdate()->firstOrFail();
            // The existing writer verifies UUID replay, revision, availability and economics.
            $retry=DB::table('appointment_operations')->where('actor_user_id',$u->id)->where('request_key',$data['request_key'])->exists();
            abort_unless($retry || self::eligible($a,$u),422,'Se requiere una reserva con exoneración autorizada y motivo registrados.');
            return app(ReservationPaymentService::class)->submit($id,$u,['request_key'=>$data['request_key'],
                'expected_revision'=>$data['revision'],'confirm'=>true,'payment'=>['amount'=>0]]);
        },3);
    }
}
