<?php
namespace Tests\Feature\Scheduling;

use App\Support\Scheduling\MvpPermissionBootstrap as B;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\BuildsBaselineData;
use Tests\TestCase;

class MvpPermissionBootstrapTest extends TestCase
{
    use RefreshDatabase, BuildsBaselineData;
    protected function setUp(): void { parent::setUp(); Storage::fake('local'); }
    private function catalogue(): void { foreach (B::REQUIRED as $p) { Permission::findOrCreate($p, 'web'); } }
    private function snapshot(): array
    {
        $s=[]; foreach (['users','roles','permissions','role_has_permissions','model_has_roles','model_has_permissions',
            'patients','appointments','payments','vouchers','cashier_shifts'] as $t) { $s[$t]=DB::table($t)->get()->toArray(); } return $s;
    }
    public function test_default_command_is_read_only_and_lists_all_existing_web_roles(): void
    {
        $this->withoutMockingConsoleOutput(); $this->catalogue();
        foreach (['ADMINISTRADOR','ADMISION','COMERCIAL','RECEPCION','CAJA','FACTURACION','OTRO_VIGENTE'] as $r) { Role::findOrCreate($r,'web'); }
        Role::findOrCreate('INTEGRACION','api'); $before=$this->snapshot();
        $this->assertSame(0,Artisan::call('agenda:mvp-permissions'));
        $plan=json_decode(Artisan::output(),true); $this->assertTrue($plan['read_only']);
        $this->assertCount(7,$plan['roles']); $this->assertEquals($before,$this->snapshot());
        $this->assertSame([],Storage::disk('local')->allFiles());
    }
    public function test_apply_is_additive_idempotent_preserves_users_money_and_existing_extraordinary_grants(): void
    {
        $this->catalogue(); $actor=$this->createUserWithRole('CAJA'); $role=$actor->roles()->first();
        $extra=Permission::findOrCreate(C::APPROVE_ZERO_COST,'web');$role->givePermissionTo($extra);
        $direct=Permission::findOrCreate('qa.direct','web');$actor->givePermissionTo($direct);
        $other=Role::findOrCreate('API_PROFILE','api'); $before=$this->snapshot(); $b=app(B::class);
        $plan=$b->plan();$receipt=$b->apply([],$plan['plan_hash'],'QA-MVP');
        foreach(B::REQUIRED as $p){$this->assertTrue($actor->fresh()->can($p));}
        $this->assertTrue($actor->fresh()->can(C::APPROVE_ZERO_COST)); $this->assertTrue($actor->fresh()->can('qa.direct'));
        $this->assertSame(0,$other->permissions()->count()); $this->assertSame(['CAJA'],$actor->fresh()->getRoleNames()->all());
        foreach(['users','roles','permissions','model_has_roles','model_has_permissions','patients','appointments','payments','vouchers','cashier_shifts'] as $t){$this->assertEquals($before[$t],$this->snapshot()[$t]);}
        $after=$this->snapshot(); $again=$b->apply([],$b->plan()['plan_hash'],'QA-RETRY');
        $this->assertEquals($after,$this->snapshot()); $this->assertSame([],$again['before']['roles'][0]['additions']);
        Storage::disk('local')->assertExists('private/mvp-permission-receipts/'.$receipt['id'].'.json');
    }
    public function test_normal_setup_never_assigns_exception_capabilities(): void
    {
        $this->catalogue();$u=$this->createUserWithRole('RECEPCION');$b=app(B::class);$b->apply([],$b->plan()['plan_hash'],'QA-BASE');
        foreach([C::APPROVE_ZERO_COST,C::REQUEST_ZERO_COST,C::OVERRIDE_DOWN_PAYMENT,C::WITHDRAW,C::CANCEL,C::OVERBOOK,C::CREATE_ADDITIONAL] as $p){$this->assertFalse($u->fresh()->can($p));}
    }
    public function test_changed_role_plan_missing_definition_or_guard_is_rejected_without_grants(): void
    {
        $this->catalogue();$r=Role::findOrCreate('CAJA','web');$b=app(B::class);$plan=$b->plan();
        Role::findOrCreate('NUEVO','web');$before=$this->snapshot();
        try{$b->apply([],$plan['plan_hash'],'QA-STALE');$this->fail('Stale plan accepted');}catch(\RuntimeException $e){$this->assertStringContainsString('plan cambió',$e->getMessage());}
        $this->assertEquals($before,$this->snapshot());
        Permission::where('name',C::SUBMIT_PAYMENT)->delete();$before=$this->snapshot();
        try{$b->apply([],$b->plan()['plan_hash'],'QA-MISSING');$this->fail('Missing accepted');}catch(\RuntimeException $e){$this->assertStringContainsString('Faltan permisos',$e->getMessage());}
        $this->assertEquals($before,$this->snapshot());
        try{$b->plan(['NO_EXISTE']);$this->fail('Unknown accepted');}catch(\InvalidArgumentException $e){$this->assertSame(0,$r->permissions()->count());}
    }
    public function test_receipt_rollback_only_removes_added_grants_and_refuses_intervening_role_changes(): void
    {
        $this->catalogue();$r=Role::findOrCreate('CAJA','web');$r->givePermissionTo(C::VIEW);$b=app(B::class);
        $receipt=$b->apply([],$b->plan()['plan_hash'],'QA-APPLY');
        $extra=Permission::findOrCreate('qa.later','web');$r->givePermissionTo($extra);$before=$this->snapshot();
        try{$b->rollback($receipt['id'],$b->plan()['plan_hash'],'QA-STALE');$this->fail('Later changes revoked');}catch(\RuntimeException $e){$this->assertEquals($before,$this->snapshot());}
        $r->revokePermissionTo($extra);$b->rollback($receipt['id'],$b->plan()['plan_hash'],'QA-ROLLBACK');
        $this->assertSame([C::VIEW],$r->permissions()->pluck('name')->all());
        $before=$this->snapshot();$b->rollback($receipt['id'],$b->plan()['plan_hash'],'QA-REPEAT');$this->assertEquals($before,$this->snapshot());
    }
    public function test_failed_private_receipt_storage_rolls_back_every_grant(): void
    {
        $this->catalogue();$r=Role::findOrCreate('CAJA','web');$b=app(B::class);$plan=$b->plan();$before=$this->snapshot();
        $disk=\Mockery::mock(\Illuminate\Filesystem\FilesystemAdapter::class);
        $disk->shouldReceive('put')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->once()->andReturn($disk);
        try{$b->apply([],$plan['plan_hash'],'QA-STORAGE-FAIL');$this->fail('Grant committed without receipt');}
        catch(\RuntimeException $e){$this->assertStringContainsString('recibo privado',$e->getMessage());}
        $this->assertEquals($before,$this->snapshot());$this->assertSame(0,$r->permissions()->count());
    }
    public function test_cli_requires_review_hash_reference_and_never_implicitly_applies(): void
    {
        $this->withoutMockingConsoleOutput();$this->catalogue();Role::findOrCreate('CAJA','web');$before=$this->snapshot();
        $this->assertSame(1,Artisan::call('agenda:mvp-permissions',['--apply'=>true]));$this->assertEquals($before,$this->snapshot());
        $plan=app(B::class)->plan();$this->assertSame(0,Artisan::call('agenda:mvp-permissions',['--apply'=>true,'--confirm'=>$plan['plan_hash'],'--reference'=>'QA-CLI']));
    }
}
