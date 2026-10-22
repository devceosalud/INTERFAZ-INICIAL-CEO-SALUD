<?php
namespace Tests\Integration\MvpEconomyDirect;

use App\Models\{AppointmentEvent,CashierShift,Payment};
use App\Services\Scheduling\{AppointmentEconomicEdit,AppointmentManagement};
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Support\Facades\{Artisan,DB};
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\Concerns\BuildsRescheduleVisualData;
use Tests\TestCase;

/** Real independent DB sessions. Never enabled on an arbitrary mysql connection. */
class EconomicEditConcurrencyTest extends TestCase
{
    use BuildsRescheduleVisualData;
    private $actor,$a,$catalog;
    protected function setUp():void
    {
        parent::setUp();
        if(getenv('AGENDA_V2_QA_RUNTIME')!=='agenda-v2-11.8.9')$this->markTestSkipped('Guarded disposable MariaDB only.');
        Artisan::call('migrate:fresh',['--force'=>true]);
        foreach($this->rescheduleVisualFixture() as $key=>$value)$this->{$key}=$value;
        $this->actor->givePermissionTo(C::EDIT_PAYMENT);$this->actor->syncRoles([Role::findOrCreate('ADMINISTRADOR','web')]);
        $this->actingAs($this->actor);
    }
    protected function tearDown():void {\Carbon\Carbon::setTestNow();parent::tearDown();}
    private function reviewed(string $amount='70'):array
    {
        $d=['kind'=>'PAYMENT','payment_id'=>Payment::firstOrFail()->id,'error_type'=>'amount','amount'=>$amount,
            'reason'=>'Carrera con dinero FICTICIO','expected_revision'=>AppointmentManagement::revision($this->a->fresh())];
        $review=app(AppointmentEconomicEdit::class)->review($this->a->id,$this->actor,$d);
        return $d+array_intersect_key($review,array_flip(['snapshot_hash','review_token','expires_at']))+['confirmed'=>true,'request_key'=>(string)Str::uuid()];
    }
    private function race(array $payload,string $first='EDIT',string $second='EDIT',bool $ordered=false,?array $other=null,?array $fiscal=null):array
    {
        $dir=storage_path('app/qa-direct-race');if(!is_dir($dir))mkdir($dir,0700,true);$run=$dir.'/'.Str::uuid();$release=$run.'.release';$locked=$ordered?$run.'.locked':null;
        $workers=[];$jobs=[];
        foreach([$first,$second] as $i=>$action){
            $job=$run.'-'.$i.'.json';$jobs[]=$job;
            $spec=['actor_id'=>$this->actor->id,'appointment_id'=>$this->a->id,'shift_id'=>CashierShift::firstOrFail()->id,
                'payload'=>$i===1?($other??$payload):$payload,'action'=>$action,'release'=>$release,'locked'=>$locked,'leader'=>$ordered&&$i===0];
            if($fiscal){$spec+=['old_read'=>$run.'.old-read','edit_done'=>$run.'.edit-done'];if($i===0)$spec=array_replace($spec,$fiscal);}
            file_put_contents($job,json_encode($spec));
            $env=getenv();$env['AGENDA_V2_QA_RUNTIME']='agenda-v2-11.8.9';
            $pipes=[];$process=proc_open([PHP_BINARY,base_path('tests/Support/direct-economic-worker.php'),$job],[1=>['pipe','w'],2=>['pipe','w']],$pipes,base_path(),$env);
            $this->assertIsResource($process);$workers[]=[$process,$pipes];
        }
        $deadline=microtime(true)+20;
        while(!file_exists($jobs[0].'.ready')||!file_exists($jobs[1].'.ready')){if(microtime(true)>$deadline)$this->fail('Worker readiness timeout.');usleep(10000);}
        touch($release);$results=[];
        foreach($workers as [$process,$pipes]){
            $stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($process);
            $this->assertSame(0,$code,$stderr);$results[]=json_decode($stdout,true,512,JSON_THROW_ON_ERROR);
        }
        return $results;
    }
    public function test_same_request_racing_never_duplicates_payment_or_audit():void
    {
        $d=$this->reviewed();$results=$this->race($d);$this->assertContains(200,array_column($results,'status'));
        foreach($results as $r)$this->assertContains($r['status'],[200,409]);
        app(AppointmentEconomicEdit::class)->save($this->a->id,$this->actor,$d);
        $this->assertDatabaseCount('payments',1);$this->assertEquals(70,Payment::sum('monto'));$this->assertSame(1,AppointmentEvent::where('event_type',AppointmentEconomicEdit::EVENT)->count());
    }
    public function test_different_reviews_racing_apply_exactly_one_change():void
    {
        $results=$this->race($this->reviewed(),'EDIT','EDIT',false,$this->reviewed('80'));
        $statuses=array_column($results,'status');sort($statuses);$this->assertSame([200,409],$statuses);
        $this->assertContains((float)Payment::sum('monto'),[70.0,80.0]);$this->assertSame(1,AppointmentEvent::where('event_type',AppointmentEconomicEdit::EVENT)->count());
    }
    public function test_close_wins_and_edit_cannot_mutate_closed_cash():void
    {
        $results=$this->race($this->reviewed(),'CLOSE','EDIT',true);
        $this->assertSame(200,$results[0]['status']);$this->assertSame(409,$results[1]['status']);
        $shift=CashierShift::firstOrFail();$this->assertSame('CERRADO',$shift->estado);$this->assertEquals(60,$shift->monto_sistema);$this->assertEquals(60,Payment::sum('monto'));
        $this->assertSame(0,AppointmentEvent::where('event_type',AppointmentEconomicEdit::EVENT)->count());
    }
    public function test_edit_wins_then_closure_freezes_corrected_cash_total():void
    {
        $results=$this->race($this->reviewed(),'EDIT','CLOSE',true);
        $this->assertSame([200,200],array_column($results,'status'));$shift=CashierShift::firstOrFail();
        $this->assertSame('CERRADO',$shift->estado);$this->assertEquals(70,$shift->monto_sistema);$this->assertEquals(70,Payment::sum('monto'));
        $this->assertSame(1,AppointmentEvent::where('event_type',AppointmentEconomicEdit::EVENT)->count());
    }
    public static function fiscalKinds():array{return [['PAYMENT'],['PRICING']];}
    /** @dataProvider fiscalKinds */
    public function test_fiscal_guard_rejects_economy_changed_between_cart_read_and_lock(string $kind):void
    {
        $payload=$this->reviewed();
        if($kind==='PRICING'){
            $this->actor->givePermissionTo(C::EDIT_PRICING);
            $d=['kind'=>'PRICING','pricing_action'=>'DISCOUNT','expected_revision'=>AppointmentManagement::revision($this->a->fresh()),
                'reason'=>'Descuento FICTICIO concurrente','discount'=>['type'=>'FIXED','value'=>'20','reason'=>'Beneficio FICTICIO']];
            $r=app(AppointmentEconomicEdit::class)->review($this->a->id,$this->actor,$d);
            $payload=$d+array_intersect_key($r,array_flip(['snapshot_hash','review_token','expires_at']))+['confirmed'=>true,'request_key'=>(string)Str::uuid()];
        }
        $cashierActor=$this->agendaReader('RECEPCION');$cashier=\App\Models\Cashier::create(['nombre'=>'OTRA CAJA FISCAL FICTICIA','estado'=>'ACTIVO']);
        $shift=CashierShift::create(['cashier_id'=>$cashier->id,'user_id'=>$cashierActor->id,'monto_apertura'=>0,'abierto_en'=>now(),'estado'=>'ABIERTO']);
        $voucher=\App\Models\Voucher::firstOrFail();
        $results=$this->race($payload,'FISCAL_GUARD','EDIT',false,null,['actor_id'=>$cashierActor->id,'shift_id'=>$shift->id,
            'ticket_id'=>$voucher->id,'patient_id'=>$this->a->patient_id,'cart'=>[['item_type'=>'cita','item_id'=>$this->a->id,'precio'=>120,'cantidad'=>1,'afectacion_igv'=>'10']]]);
        $this->assertSame([422,200],array_column($results,'status'));
        $this->assertEquals($kind==='PAYMENT'?70:60,Payment::sum('monto'));
        $this->assertEquals($kind==='PAYMENT'?120:100,$this->a->fresh()->precio_programado);
        $this->assertSame(1,AppointmentEvent::where('event_type',AppointmentEconomicEdit::EVENT)->count());
    }
}
