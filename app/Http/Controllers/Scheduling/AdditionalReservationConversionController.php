<?php
namespace App\Http\Controllers\Scheduling;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Services\Scheduling\AdditionalReservationConversion;
use App\Services\Billing\AppointmentEconomicPosition;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Http\Request;
class AdditionalReservationConversionController extends Controller
{
    public function preview(Request $r,int $appointmentId,AdditionalReservationConversion $service){
        abort_unless($r->user()->can(C::VIEW)&&$r->user()->can(C::CREATE),403);
        $a=Appointment::visibleToAgendaUser($r->user()->id)->whereKey($appointmentId)->firstOr(fn()=>abort(404));
        return response()->json($service->offer($a,$r->user()))->header('Cache-Control','private, no-store');
    }
    public function store(Request $r,int $appointmentId,AdditionalReservationConversion $service){
        abort_unless($r->user()->can(C::VIEW)&&$r->user()->can(C::CREATE)&&$r->user()->can(C::UPDATE)&&$r->user()->can(C::CREATE_ADDITIONAL),403);
        abort_if(array_diff(array_keys($r->all()),['request_key','expected_version','confirmed','_token']),422,'Esta acción no registra importes ni modifica el paciente.');
        $data=$r->validate(['request_key'=>'required|uuid','expected_version'=>'required|string|regex:/\A[a-f0-9]{64}\z/','confirmed'=>'required|accepted']);
        $a=$service->convert($appointmentId,$r->user(),$data);
        return response()->json(['appointment'=>['appointment_id'=>$a->id,'estado_agenda'=>$a->estado_agenda,'tipo_agendamiento'=>$a->tipo_agendamiento],
            'economy'=>app(AppointmentEconomicPosition::class)->publicPosition($a)])->header('Cache-Control','private, no-store');
    }
}
