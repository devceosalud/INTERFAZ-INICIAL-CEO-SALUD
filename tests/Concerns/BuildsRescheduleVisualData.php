<?php
namespace Tests\Concerns;

use App\Models\{Cashier,CashierShift,DoctorSchedule,VoucherSerie};
use App\Services\Scheduling\ReservationPaymentService;
use App\Support\Scheduling\SchedulingCapability as C;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/** Synthetic cohort shared by HTTP acceptance tests and local browser QA. */
trait BuildsRescheduleVisualData
{
    use BuildsAgendaLifecycleData;

    protected function rescheduleVisualFixture(): array
    {
        Carbon::setTestNow(Carbon::parse('2026-10-21 09:00','America/Lima'));
        config(['scheduling.enabled'=>true,'app.debug'=>false]);
        $actor=$this->agendaReader('ADMISION');
        foreach([C::CREATE,C::RESCHEDULE,C::SUBMIT_PAYMENT] as $cap)$actor->givePermissionTo(Permission::findOrCreate($cap,'web'));
        $catalog=$this->createAppointmentCatalog();
        $catalog['doctor']->update(['nombre'=>'MEDICO FICTICIO REPROGRAMACIONES']);
        $catalog['schedule']->update(['fecha_cita'=>'2026-10-21','dia_semana'=>3,'hora_inicio'=>'12:00','hora_fin'=>'14:00','duracion_cita'=>20]);
        $catalog['doctorService']->update(['precio_primera_consulta'=>120]);
        foreach(['2026-10-24','2026-10-26'] as $date)DoctorSchedule::create(['doctor_id'=>$catalog['doctor']->id,'fecha_cita'=>$date,
            'dia_semana'=>Carbon::parse($date)->dayOfWeekIso,'hora_inicio'=>'12:00','hora_fin'=>'14:00','duracion_cita'=>20,'estado'=>'ACTIVO']);
        $a=$this->lifecycleAppointment($actor,$catalog,['fecha_cita'=>'2026-10-21','hora_cita'=>'12:20','duracion_cita'=>20,
            'estado_agenda'=>'CONFIRMADA','tipo_agendamiento'=>'REGULAR','precio_programado'=>120,'catalog_price'=>120,'saldo_pendiente'=>120]);
        $cashier=Cashier::create(['nombre'=>'CAJA FICTICIA REPROGRAMACIONES','estado'=>'ACTIVO']);
        CashierShift::create(['cashier_id'=>$cashier->id,'user_id'=>$actor->id,'monto_apertura'=>0,'abierto_en'=>now(),'estado'=>'ABIERTO']);
        VoucherSerie::create(['cashier_id'=>$cashier->id,'tipo_comprobante'=>'TICKET','serie'=>'RV01','correlativo_actual'=>0,'estado'=>'ACTIVO']);
        app(ReservationPaymentService::class)->submit($a->id,$actor,['request_key'=>(string)Str::uuid(),'payment'=>['amount'=>'60','method'=>'EFECTIVO']]);
        return ['actor'=>$actor,'catalog'=>$catalog,'a'=>$a->fresh()];
    }
}
