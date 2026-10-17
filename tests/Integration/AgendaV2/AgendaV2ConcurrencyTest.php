<?php
namespace Tests\Integration\AgendaV2;

use App\Models\{Appointment,Cashier,CashierShift,Payment,VoucherSerie};
use App\Services\Scheduling\OperationalProcedureService;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Symfony\Component\Process\Process;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;

class AgendaV2ConcurrencyTest extends TestCase
{
    use BuildsAgendaLifecycleData;
    private $actor,$admin,$catalog;
    protected function setUp():void
    {
        parent::setUp();
        if(DB::getDriverName()!=='mysql')$this->markTestSkipped('Requires the owned v2 MariaDB runtime.');
        $this->artisan('migrate:fresh',['--force'=>true])->assertExitCode(0);
        config(['scheduling.enabled'=>true]);
        $this->actor=$this->agendaReader('ADMISION');$this->admin=$this->agendaReader('ADMINISTRADOR');
        foreach([C::CREATE,C::SUBMIT_PAYMENT,C::UPDATE] as $cap)$this->actor->givePermissionTo(Permission::findOrCreate($cap,'web'));
        $this->catalog=$this->createAppointmentCatalog();
        $c=Cashier::create(['nombre'=>'Caja concurrente v2 ficticia','estado'=>'ACTIVO']);
        CashierShift::create(['cashier_id'=>$c->id,'user_id'=>$this->actor->id,'monto_apertura'=>0,'abierto_en'=>now(),'estado'=>'ABIERTO']);
        VoucherSerie::create(['cashier_id'=>$c->id,'tipo_comprobante'=>'TICKET','serie'=>'VRAC','correlativo_actual'=>0,'estado'=>'ACTIVO']);
        $this->actingAs($this->actor);
    }
    private function race(array $requests):array
    {
        $dir=storage_path('app/qa-v2/races/'.Str::uuid());mkdir($dir,0777,true);$jobs=[];
        foreach($requests as $i=>$r){$out=$dir.'/'.$i.'.json';$in=$dir.'/'.$i.'.input.json';
            file_put_contents($in,json_encode($r+['actor'=>$this->actor->id,'release'=>$dir.'/release']));
            $p=new Process([PHP_BINARY,base_path('tests/Support/agenda-v2-request.php'),$in,$out],base_path(),null,null,30);$p->start();$jobs[]=[$p,$out];}
        try{
            $deadline=microtime(true)+15;
            do{$ready=true;foreach($jobs as [$p,$out])$ready=$ready&&file_exists($out.'.ready');if($ready)break;usleep(10000);}while(microtime(true)<$deadline);
            $this->assertTrue($ready,'V2 workers did not reach the synchronization barrier.');touch($dir.'/release');$statuses=[];
            foreach($jobs as [$p,$out]){$p->wait();$this->assertTrue($p->isSuccessful(),$p->getErrorOutput());
                $r=json_decode(file_get_contents($out),true,512,JSON_THROW_ON_ERROR);$statuses[]=$r['status'];}
            sort($statuses);return $statuses;
        }finally{foreach($jobs as [$p])if($p->isRunning())$p->stop();}
    }
    private function registration(int $patient):array
    {
        return ['path'=>'/scheduling-mvp/agenda/registrations','payload'=>['request_key'=>(string)Str::uuid(),
            'mode'=>'RESERVE','booking_type'=>'REGULAR','motivo_consulta'=>'Motivo ficticio para prueba de registro','patient_id'=>$patient,'doctor_id'=>$this->catalog['doctor']->id,
            'service_id'=>$this->catalog['service']->id,'fecha_cita'=>$this->catalog['schedule']->fecha_cita,
            'hora_cita'=>'08:00','duracion_cita'=>30,'payment'=>['amount'=>'30','method'=>'EFECTIVO']]];
    }
    public function test_simultaneous_same_uuid_creates_one_confirmed_appointment_payment_and_ticket():void
    {
        $r=$this->registration($this->createPatient($this->actor)->id);
        $this->assertSame([201,201],$this->race([$r,$r]));
        $this->assertDatabaseCount('appointments',1);$this->assertDatabaseCount('payments',1);$this->assertDatabaseCount('vouchers',1);
        $this->assertSame('CONFIRMADA',Appointment::sole()->estado_agenda);$this->assertEquals(30,Payment::sum('monto'));
    }
    public function test_simultaneous_paid_bookings_of_same_slot_have_one_winner_and_no_orphan_payment():void
    {
        $a=$this->registration($this->createPatient($this->actor)->id);
        $b=$this->registration($this->createPatient($this->actor,['numero_identidad'=>'79008881','historia_clinica'=>'RACE-V2-B'])->id);
        $this->assertSame([201,409],$this->race([$a,$b]));
        $this->assertDatabaseCount('appointments',1);$this->assertDatabaseCount('payments',1);$this->assertDatabaseCount('vouchers',1);
        $this->assertDatabaseCount('appointment_operations',1);$this->assertEquals(30,Payment::sum('monto'));
    }
    public function test_simultaneous_procedure_updates_reject_stale_version_without_losing_a_completion():void
    {
        $a=$this->lifecycleAppointment($this->actor,$this->catalog,['fecha_cita'=>now('America/Lima')->toDateString()]);
        app(OperationalProcedureService::class)->mutate($a->id,$this->admin,'selection',[
            'request_key'=>(string)Str::uuid(),'expected_version'=>0,'procedures'=>['radiography','laboratory']]);
        $r=['path'=>'/scheduling-mvp/admission/appointments/'.$a->id.'/procedures/completion',
            'payload'=>['request_key'=>(string)Str::uuid(),'expected_version'=>1,'procedure'=>'radiography','completed'=>true]];
        $b=array_replace($r,['payload'=>array_replace($r['payload'],['request_key'=>(string)Str::uuid(),'procedure'=>'laboratory'])]);
        $this->assertSame([200,409],$this->race([$r,$b]));
        $this->assertSame(1,DB::table('appointment_requested_procedures')->whereNotNull('completed_at')->count());
        $this->assertDatabaseCount('appointment_procedure_events',2);$this->assertDatabaseHas('appointment_operational_reviews',['status'=>'NOT_READY','version'=>2]);
    }
    public function test_simultaneous_repeated_completion_has_one_audit_event_and_one_queue_row():void
    {
        $a=$this->lifecycleAppointment($this->actor,$this->catalog,['fecha_cita'=>now('America/Lima')->toDateString()]);
        app(OperationalProcedureService::class)->mutate($a->id,$this->admin,'selection',[
            'request_key'=>(string)Str::uuid(),'expected_version'=>0,'procedures'=>['radiography']]);
        $r=['path'=>'/scheduling-mvp/admission/appointments/'.$a->id.'/procedures/completion',
            'payload'=>['request_key'=>(string)Str::uuid(),'expected_version'=>1,'procedure'=>'radiography','completed'=>true]];
        $this->assertSame([200,200],$this->race([$r,$r]));
        $this->assertDatabaseCount('appointment_procedure_events',2);$this->assertDatabaseCount('appointment_operational_reviews',1);
        $this->assertDatabaseHas('appointment_operational_reviews',['status'=>'READY','version'=>2]);
    }
    public function test_simultaneous_reversals_revert_once_and_keep_original_events():void
    {
        $a=$this->lifecycleAppointment($this->actor,$this->catalog);
        app(\App\Services\Scheduling\AdmissionCareService::class)->transition($a->id,$this->actor,
            ['state'=>'EN_ESPERA','expected_state'=>'PROGRAMADO','request_key'=>(string)Str::uuid()]);
        $event=$a->events()->orderByDesc('id')->first();
        $r=['path'=>'/scheduling-mvp/admission/appointments/'.$a->id.'/care-reversal','payload'=>[
            'request_key'=>(string)Str::uuid(),'expected_event_id'=>$event->id,'expected_state'=>'EN_ESPERA','motivo'=>'Corrección ficticia']];
        $b=array_replace($r,['payload'=>array_replace($r['payload'],['request_key'=>(string)Str::uuid()])]);
        $this->assertSame([200,409],$this->race([$r,$b]));
        $this->assertSame('PROGRAMADO',$a->fresh()->estado_cita);$this->assertSame(1,$a->events()->where('event_type','ADMISION_ESTADO_REVERTIDO')->count());
        $this->assertDatabaseCount('payments',0);$this->assertDatabaseCount('vouchers',0);
    }
}
