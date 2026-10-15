<?php
namespace Tests\Feature\Scheduling;
use App\Models\Site;
use App\Support\Scheduling\MagdalenaSiteSetup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;

class MagdalenaSiteSetupTest extends TestCase
{
    use RefreshDatabase,BuildsAgendaLifecycleData;
    private $admin;
    protected function setUp():void {parent::setUp();Storage::fake('local');$this->admin=$this->createUserWithRole('ADMINISTRADOR');}
    private function service():MagdalenaSiteSetup{return app(MagdalenaSiteSetup::class);}
    private function apply(string $code='MAGDALENA'):array{return $this->service()->apply($code,$this->service()->plan($code)['plan_hash'],$this->admin->id,'QA-APROBADO');}
    private function path(array $r):string{return 'private/magdalena-site-receipts/'.$r['id'].'.json';}
    private function snapshot():array {
        $out=[];foreach(['appointments','doctor_schedules','patients','payments','vouchers','model_has_roles','role_has_permissions','model_has_permissions'] as $t)
            $out[$t]=DB::table($t)->get()->toJson();
        return $out;
    }
    private function fails(callable $fn,string $message):void {
        try{$fn();$this->fail('Debió detenerse sin modificar el catálogo.');}catch(\RuntimeException|\InvalidArgumentException $e){$this->assertStringContainsString($message,$e->getMessage());}
    }
    public function test_preview_is_read_only_and_does_not_create_a_site_or_audit_file():void {
        $before=$this->snapshot();
        unset($this->app[\Illuminate\Console\OutputStyle::class]);
        $buffer=new \Symfony\Component\Console\Output\BufferedOutput;
        $this->assertSame(0,Artisan::call('agenda:magdalena-site',['--code'=>'MAGDALENA'],$buffer));
        $raw=$buffer->fetch();$p=json_decode($raw,true);$this->assertIsArray($p,'RAW='.json_encode($raw));
        $this->assertTrue($p['read_only']);$this->assertSame('CREATE',$p['action']);$this->assertNull($p['target_id']);
        $this->assertSame([],$p['blockers']);$this->assertDatabaseCount('sites',0);
        $this->assertSame($before,$this->snapshot());$this->assertCount(0,Storage::disk('local')->allFiles());
    }
    public function test_create_and_repeat_reuse_a_real_id_without_grants_or_duplicate_sites():void {
        $before=$this->snapshot();$r=$this->apply();$site=Site::findOrFail($r['site_id']);$attrs=$site->getAttributes();
        $this->assertTrue($r['created']);$this->assertSame(MagdalenaSiteSetup::NAME,$site->nombre);$this->assertSame('MAGDALENA',$site->codigo);
        $this->assertGreaterThan(0,$site->id);$this->assertTrue(Storage::disk('local')->exists($this->path($r)));
        $again=$this->apply();$this->assertFalse($again['created']);$this->assertSame($site->id,$again['site_id']);
        $this->assertDatabaseCount('sites',1);$this->assertSame($attrs,$site->fresh()->getAttributes());$this->assertSame($before,$this->snapshot());
    }
    public function test_historical_patients_appointments_and_null_site_schedules_are_unchanged():void {
        $catalog=$this->createAppointmentCatalog();$this->lifecycleAppointment($this->admin,$catalog);
        $before=$this->snapshot();$this->apply();$this->assertSame($before,$this->snapshot());
        $this->assertSame(1,DB::table('appointments')->whereNull('site_id')->count());
        $this->assertSame(1,DB::table('doctor_schedules')->whereNull('site_id')->count());
    }
    public function test_unused_new_site_can_be_reversed_and_rollback_retry_is_idempotent():void {
        $r=$this->apply();$back=$this->service()->rollback($r['id'],$r['after']['state_hash'],$this->admin->id,'QA-REVERSA');
        $this->assertSame('ROLLED_BACK',$back['status']);$this->assertDatabaseCount('sites',0);
        $retry=$this->service()->rollback($r['id'],$r['after']['state_hash'],$this->admin->id,'QA-REVERSA');
        $this->assertSame('ROLLED_BACK',$retry['status']);$this->assertTrue(Storage::disk('local')->exists($this->path($r)));
    }
    public function test_existing_magdalena_keeps_its_code_id_and_timestamps_and_cannot_be_deleted():void {
        $site=Site::create(['codigo'=>'MAG-ANTERIOR','nombre'=>'  MAGDALENA   DEL MAR  ','estado'=>'ACTIVO']);$before=$site->fresh()->getAttributes();
        $r=$this->apply();$this->assertFalse($r['created']);$this->assertSame($before,$site->fresh()->getAttributes());
        $this->fails(fn()=>$this->service()->rollback($r['id'],$r['after']['state_hash'],$this->admin->id,'QA-REVERSA'),'preexistente');
        $this->assertDatabaseHas('sites',['id'=>$site->id,'codigo'=>'MAG-ANTERIOR']);
    }
    public function test_stale_preview_does_not_write_or_backfill():void {
        $p=$this->service()->plan('MAGDALENA');$this->createAppointmentCatalog();$before=$this->snapshot();
        $this->fails(fn()=>$this->service()->apply('MAGDALENA',$p['plan_hash'],$this->admin->id,'QA-APROBADO'),'cambiaron');
        $this->assertDatabaseCount('sites',0);$this->assertSame($before,$this->snapshot());
    }
    public function test_code_collision_and_inactive_existing_site_block_without_reactivation():void {
        foreach([['codigo'=>'MAGDALENA','nombre'=>'Otra sede','estado'=>'ACTIVO'],['codigo'=>'MAGDALENA','nombre'=>MagdalenaSiteSetup::NAME,'estado'=>'INACTIVO']] as $attrs){
            $site=Site::create($attrs);$before=$site->fresh()->getAttributes();$p=$this->service()->plan('MAGDALENA');
            $this->assertContains('SEDE_EXISTENTE_INCOMPATIBLE',$p['blockers']);
            $this->fails(fn()=>$this->service()->apply('MAGDALENA',$p['plan_hash'],$this->admin->id,'QA-APROBADO'),'incompatibilidades');
            $this->assertSame($before,$site->fresh()->getAttributes());$site->delete(); // Isolated fixture only.
        }
    }
    public function test_multiple_magdalena_candidates_or_other_active_site_require_review():void {
        Site::create(['codigo'=>'MAG1','nombre'=>MagdalenaSiteSetup::NAME,'estado'=>'ACTIVO']);
        Site::create(['codigo'=>'MAG2','nombre'=>MagdalenaSiteSetup::NAME,'estado'=>'ACTIVO']);
        $this->assertContains('MAGDALENA_AMBIGUA',$this->service()->plan('MAGDALENA')['blockers']);
        $this->assertDatabaseCount('sites',2);
    }
    public function test_other_active_site_is_not_silently_declared_magdalena():void {
        Site::create(['codigo'=>'OTRA','nombre'=>'Otra sede','estado'=>'ACTIVO']);$p=$this->service()->plan('MAGDALENA');
        $this->assertContains('OTRA_SEDE_OPERATIVA_REQUIERE_REVISION',$p['blockers']);
        $this->fails(fn()=>$this->service()->apply('MAGDALENA',$p['plan_hash'],$this->admin->id,'QA-APROBADO'),'incompatibilidades');
        $this->assertDatabaseCount('sites',1);
    }
    public function test_non_admin_and_legacy_empty_guard_cannot_apply():void {
        $other=$this->createUserWithRole('ADMISION');$p=$this->service()->plan('MAGDALENA');
        $this->fails(fn()=>$this->service()->apply('MAGDALENA',$p['plan_hash'],$other->id,'QA-APROBADO'),'ADMINISTRADOR');
        DB::table('roles')->where('name','ADMINISTRADOR')->update(['guard_name'=>'']);
        $this->fails(fn()=>$this->service()->apply('MAGDALENA',$p['plan_hash'],$this->admin->id,'QA-APROBADO'),'guard web');
        $this->assertDatabaseCount('sites',0);
    }
    public function test_inactive_administrator_cannot_apply_or_reverse_catalogue_setup():void {
        $this->admin->forceFill(['is_active'=>false])->save();$before=$this->snapshot();
        $this->fails(fn()=>$this->apply(),'activo');$this->assertDatabaseCount('sites',0);$this->assertSame($before,$this->snapshot());
        $this->admin->forceFill(['is_active'=>true])->save();$r=$this->apply();
        $this->admin->forceFill(['is_active'=>false])->save();
        $this->fails(fn()=>$this->service()->rollback($r['id'],$r['after']['state_hash'],$this->admin->id,'QA-REVERSA'),'activo');
        $this->assertDatabaseCount('sites',1);
        $this->assertSame('APPLIED',json_decode(Storage::disk('local')->get($this->path($r)),true)['status']);
    }
    public function test_tampered_receipt_cannot_delete_a_site():void {
        $r=$this->apply();$data=json_decode(Storage::disk('local')->get($this->path($r)),true);$data['site_id']=999;
        Storage::disk('local')->put($this->path($r),json_encode($data));
        $this->fails(fn()=>$this->service()->rollback($r['id'],$r['after']['state_hash'],$this->admin->id,'QA-REVERSA'),'modificado');
        $this->assertDatabaseHas('sites',['id'=>$r['site_id']]);
    }
    public function test_related_schedule_prevents_rollback_without_removing_any_records():void {
        $r=$this->apply();$catalog=$this->createAppointmentCatalog();$catalog['schedule']->update(['site_id'=>$r['site_id']]);$before=$this->snapshot();
        $this->fails(fn()=>$this->service()->rollback($r['id'],$r['after']['state_hash'],$this->admin->id,'QA-REVERSA'),'cambiaron');
        $this->assertDatabaseCount('sites',1);$this->assertSame($before,$this->snapshot());
    }
    public function test_altered_site_is_preserved_instead_of_forced_rollback():void {
        $r=$this->apply();Site::find($r['site_id'])->update(['nombre'=>'Cambio explícito ficticio']);
        $this->fails(fn()=>$this->service()->rollback($r['id'],$r['after']['state_hash'],$this->admin->id,'QA-REVERSA'),'cambiaron');
        $this->assertDatabaseCount('sites',1);
    }
    public function test_invalid_code_and_missing_approval_fail_without_writes():void {
        $this->fails(fn()=>$this->service()->plan('1'),'3–32');
        $this->fails(fn()=>$this->service()->apply('MAGDALENA','',1,'QA'),'huella');
        $this->assertDatabaseCount('sites',0);
        $this->assertSame(1,Artisan::call('agenda:magdalena-site',['--code'=>'MAGDALENA','--apply'=>true]));
        $this->assertDatabaseCount('sites',0);
    }

    public function test_orphan_site_reference_blocks_creation_instead_of_accidentally_attributing_history():void {
        $catalog=$this->createAppointmentCatalog();
        if(DB::connection()->getDriverName()==='sqlite')DB::statement('PRAGMA defer_foreign_keys=ON');
        else DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try{DB::table('doctor_schedules')->where('id',$catalog['schedule']->id)->update(['site_id'=>999]);}
        finally{if(DB::connection()->getDriverName()!=='sqlite')DB::statement('SET FOREIGN_KEY_CHECKS=1');}
        $before=$this->snapshot();$p=$this->service()->plan('MAGDALENA');
        $this->assertContains('REFERENCIA_SEDE_HUERFANA',$p['blockers']);
        $this->fails(fn()=>$this->service()->apply('MAGDALENA',$p['plan_hash'],$this->admin->id,'QA-APROBADO'),'incompatibilidades');
        $this->assertDatabaseCount('sites',0);$this->assertSame($before,$this->snapshot());
    }
    public function test_money_recorded_after_setup_prevents_rollback_even_when_site_is_unused():void {
        $catalog=$this->createAppointmentCatalog();$a=$this->lifecycleAppointment($this->admin,$catalog);$r=$this->apply();
        $box=\App\Models\Cashier::create(['nombre'=>'Caja sede ficticia','estado'=>'ACTIVO']);
        $shift=\App\Models\CashierShift::create(['cashier_id'=>$box->id,'user_id'=>$this->admin->id,'monto_apertura'=>0,'abierto_en'=>now(),'estado'=>'ABIERTO']);
        \App\Models\VoucherSerie::create(['cashier_id'=>$box->id,'tipo_comprobante'=>'TICKET','serie'=>'QASM','correlativo_actual'=>0,'estado'=>'ACTIVO']);
        $ticket=app(\App\Services\Billing\AppointmentTicketService::class)->create($a,$shift,$this->admin->id);
        foreach(['20.00','30.00'] as $money)$ticket->payments()->create(['monto'=>$money,'metodo_pago'=>'EFECTIVO','user_id'=>$this->admin->id,'cashier_shift_id'=>$shift->id]);
        $before=$this->snapshot();
        $this->assertSame(2,$this->service()->plan('MAGDALENA')['money_counts']['payments']);
        $this->fails(fn()=>$this->service()->rollback($r['id'],$r['after']['state_hash'],$this->admin->id,'QA-REVERSA'),'cambiaron');
        $this->assertDatabaseCount('payments',2);$this->assertDatabaseCount('sites',1);$this->assertSame($before,$this->snapshot());
    }
}
