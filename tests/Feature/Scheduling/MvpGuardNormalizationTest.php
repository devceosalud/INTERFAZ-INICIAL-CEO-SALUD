<?php
namespace Tests\Feature\Scheduling;

use App\Support\Scheduling\MvpGuardNormalization as N;
use App\Support\Scheduling\MvpPermissionBootstrap as B;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Exceptions\GuardDoesNotMatch;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\Concerns\BuildsLegacyMvpGuards;
use Tests\TestCase;

class MvpGuardNormalizationTest extends TestCase
{
    use RefreshDatabase, BuildsAgendaLifecycleData, BuildsLegacyMvpGuards;
    protected function setUp(): void
    {
        parent::setUp(); Storage::fake('local');
        // Reproduce the pre-RC minimal legacy catalogue, excluding later additive definitions.
        \Spatie\Permission\Models\Role::where('name',\App\Support\Scheduling\ClinicalCapability::ROLE)->delete();
        Permission::whereIn('name',array_merge(['doctor.schedule.manage','appointment.documents.view','appointment.documents.manage',C::EDIT_PAYMENT,C::EDIT_PRICING],
            \App\Support\Scheduling\ClinicalCapability::all()))->delete();
        $this->buildLegacyMvpGuards();
    }
    private function snapshot(): array
    {
        $state = [];
        foreach (['users','roles','permissions','role_has_permissions','model_has_roles','model_has_permissions',
            'patients','appointments','payments','vouchers','voucher_items','appointment_operations','cashiers','cashier_shifts'] as $table) {
            $state[$table] = DB::table($table)->get()->toArray();
        }
        return $state;
    }
    private function normalize(array $ids = []): array
    {
        $n = app(N::class); $p = $n->plan($this->legacyRoleNames(),$ids);
        return $n->apply($this->legacyRoleNames(),$ids,$p['plan_hash'],'QA-GUARDS');
    }
    public function test_fixture_reproduces_partial_spatie_access_and_bootstrap_refuses_silent_exclusion(): void
    {
        $this->assertSame(5,DB::table('roles')->where('guard_name','')->count());
        $this->assertSame('web',$this->legacyRoles['COMERCIAL']->guard_name);
        $actor = $this->legacyActors['ADMISION']->fresh();
        $this->assertTrue($actor->hasRole('ADMISION'));
        $this->assertTrue($actor->can(C::VIEW));
        try { Role::findByName('ADMISION','web'); $this->fail('Empty role found as web'); }
        catch (RoleDoesNotExist $e) { $this->assertNotEmpty($e->getMessage()); }
        try { $actor->assignRole($this->legacyRoles['CAJA']); $this->fail('Invalid role assigned'); }
        catch (GuardDoesNotMatch $e) { $this->assertNotEmpty($e->getMessage()); }
        $before = $this->snapshot(); $b = app(B::class); $plan = $b->plan($this->legacyRoleNames());
        $this->assertCount(1,$plan['roles']); $this->assertCount(5,$plan['guard_issues']);
        try { $b->apply($this->legacyRoleNames(),$plan['plan_hash'],'QA-UNSAFE'); $this->fail('Legacy roles silently excluded'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('Normaliza',$e->getMessage()); }
        $this->assertEquals($before,$this->snapshot());
    }
    public function test_existing_web_extraordinary_grants_are_effective_before_and_after_five_role_only_repair(): void
    {
        config(['scheduling.enabled'=>true,'app.debug'=>false]);
        foreach ([C::WITHDRAW,C::CREATE_ADDITIONAL] as $cap) {
            $p=Permission::findOrCreate($cap,'web');
            foreach(['ADMISION','COMERCIAL'] as $name){
                DB::table('role_has_permissions')->insert(['role_id'=>$this->legacyRoles[$name]->id,'permission_id'=>$p->id]);
            }
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $permissions=DB::table('permissions')->orderBy('id')->get()->toArray();
        $links=DB::table('role_has_permissions')->orderBy('role_id')->orderBy('permission_id')->get()->toArray();
        $catalog=$this->createAppointmentCatalog();$i=0;
        foreach(['before','after'] as $phase){
            if($phase==='after'){
                $names=array_values(array_diff($this->legacyRoleNames(),['COMERCIAL']));
                $n=app(N::class);$plan=$n->plan($names);
                $this->assertSame([],$plan['selected_permission_ids']);$this->assertSame([],$plan['blockers']);
                $this->assertCount(5,$plan['changes']);
                $n->apply($names,[],$plan['plan_hash'],'QA-FIVE-ROLE-ONLY');
            }
            foreach(['ADMISION','COMERCIAL'] as $name){
                $u=$this->legacyActors[$name]->fresh();
                $this->assertTrue($u->can(C::WITHDRAW),$phase.' '.$name);
                $this->assertTrue($u->can(C::CREATE_ADDITIONAL),$phase.' '.$name);
                $this->assertFalse($u->can(C::SUBMIT_PAYMENT));
                $this->assertFalse($u->can(C::APPROVE_ZERO_COST));
                $patient=$this->createPatient($u,['numero_identidad'=>(string)(70001900+$i),'historia_clinica'=>'QA-GUARD-EXTRA-'.$i++]);
                $a=$this->lifecycleAppointment($u,$catalog,['estado_agenda'=>'LEGADO','estado_cita'=>'CONFIRMADO'],$patient);
                $d=['request_key'=>(string)\Illuminate\Support\Str::uuid(),'motivo'=>'FICTICIO GUARDS','requested_action'=>'PENDIENTE','was_present'=>true];
                $this->actingAs($u)->postJson('/scheduling-mvp/agenda/appointments/'.$a->id.'/withdraw',$d)->assertOk();
                $this->postJson('/scheduling-mvp/agenda/appointments/'.$a->id.'/withdraw',$d)->assertOk();
                $this->assertSame('RETIRO',$a->fresh()->estado_cita);
                $this->assertSame(1,$a->events()->where('event_type','RETIRO')->count());
            }
        }
        $this->assertEquals($permissions,DB::table('permissions')->orderBy('id')->get()->toArray());
        $this->assertEquals($links,DB::table('role_has_permissions')->orderBy('role_id')->orderBy('permission_id')->get()->toArray());
        $this->assertSame('web',$this->legacyRoles['COMERCIAL']->fresh()->guard_name);
        $this->assertDatabaseCount('payments',0);$this->assertDatabaseCount('appointment_refund_requests',0);
    }

    public function test_audit_only_lists_guards_and_incompatible_links_without_personal_data_or_writes(): void
    {
        $this->withoutMockingConsoleOutput(); $before=$this->snapshot();
        $this->assertSame(0,Artisan::call('agenda:mvp-guards'));
        $plan=json_decode(Artisan::output(),true);
        $this->assertTrue($plan['read_only']); $this->assertCount(6,$plan['catalogue_roles']);
        $this->assertCount(6,$plan['catalogue_permissions']); $this->assertNotEmpty($plan['existing_link_mismatches']);
        $this->assertSame([],$plan['changes']); $this->assertEquals($before,$this->snapshot());
        $this->assertStringNotContainsString('model_id',Artisan::output());
        $this->assertSame([],Storage::disk('local')->allFiles());
    }
    public function test_normalization_preserves_ids_pivots_users_history_and_money_and_is_idempotent(): void
    {
        $actor=$this->legacyActors['ADMINISTRADOR'];$catalog=$this->createAppointmentCatalog();
        $history=$this->lifecycleAppointment($actor,$catalog,['estado_agenda'=>'LEGADO','estado_cita'=>'ATENDIDO',
            'precio_programado'=>173.25,'total_pagado'=>73.25,'saldo_pendiente'=>100]);
        $historyAttributes=$history->fresh()->getAttributes();
        $direct=Permission::findOrCreate('qa.direct','web');$actor->givePermissionTo($direct);
        $before=$this->snapshot();$n=app(N::class);$receipt=$this->normalize();
        $this->assertCount(5,$receipt['before']['changes']);
        $this->assertSame(6,DB::table('roles')->where('guard_name','web')->count());
        foreach ($before as $table=>$rows) { if($table!=='roles'){$this->assertEquals($rows,$this->snapshot()[$table],$table);} }
        $this->assertSame('ADMISION',Role::findByName('ADMISION','web')->name);
        $this->assertFalse($actor->fresh()->can(C::SUBMIT_PAYMENT));
        $this->assertTrue($actor->fresh()->can('qa.direct'));
        $this->assertEquals($historyAttributes,$history->fresh()->getAttributes());
        $after=$this->snapshot();$again=$this->normalize();
        $this->assertSame([],$again['before']['changes']);$this->assertEquals($after,$this->snapshot());
        Storage::disk('local')->assertExists('private/mvp-guard-receipts/'.$receipt['id'].'.json');
        $n->rollback($receipt['id'],$receipt['after']['state_hash'],'QA-ROLLBACK');
        $this->assertEquals($before,$this->snapshot());
        $n->rollback($receipt['id'],$receipt['after']['state_hash'],'QA-ROLLBACK-RETRY');
        $this->assertEquals($before,$this->snapshot());
    }
    public function test_blank_permission_requires_explicit_id_and_existing_authority_activation_is_visible(): void
    {
        $p=Permission::create(['name'=>C::APPROVE_ZERO_COST,'guard_name'=>'']);
        $r=$this->legacyRoles['ADMISION'];$u=$this->legacyActors['ADMISION'];
        DB::table('role_has_permissions')->insert(['role_id'=>$r->id,'permission_id'=>$p->id]);
        DB::table('model_has_permissions')->insert(['permission_id'=>$p->id,'model_type'=>$u->getMorphClass(),'model_id'=>$u->id]);
        $this->assertFalse($u->fresh()->can(C::APPROVE_ZERO_COST));
        $n=app(N::class);$plan=$n->plan($this->legacyRoleNames());$before=$this->snapshot();
        $this->assertNotEmpty($plan['blockers']);
        try{$n->apply($this->legacyRoleNames(),[],$plan['plan_hash'],'QA-BLOCK');$this->fail('Permission silently repaired');}
        catch(\RuntimeException $e){$this->assertEquals($before,$this->snapshot());}
        $selected=$n->plan($this->legacyRoleNames(),[$p->id]);
        $row=collect($selected['catalogue_permissions'])->firstWhere('id',$p->id);
        $this->assertSame(1,$row['role_links']);$this->assertSame(1,$row['direct_links']);
        $this->normalize([$p->id]);
        $this->assertSame('web',$p->fresh()->guard_name);$this->assertTrue($u->fresh()->can(C::APPROVE_ZERO_COST));
        $this->assertEquals($before['role_has_permissions'],$this->snapshot()['role_has_permissions']);
        $this->assertEquals($before['model_has_permissions'],$this->snapshot()['model_has_permissions']);
        $this->assertFalse($this->legacyActors['CAJA']->fresh()->can(C::APPROVE_ZERO_COST));
    }
    public function test_all_required_permission_guards_empty_are_not_recreated_and_multi_role_bindings_survive(): void
    {
        DB::table('permissions')->update(['guard_name'=>'']); // Isolated fixture only.
        $u=$this->legacyActors['ADMINISTRADOR'];
        DB::table('model_has_roles')->insert(['role_id'=>$this->legacyRoles['COMERCIAL']->id,'model_type'=>$u->getMorphClass(),'model_id'=>$u->id]);
        $before=$this->snapshot();$n=app(N::class);$ids=DB::table('permissions')->pluck('id')->all();
        $this->assertNotEmpty($n->plan($this->legacyRoleNames())['blockers']);
        $this->normalize($ids);
        $this->assertSame(6,DB::table('permissions')->where('guard_name','web')->count());
        $this->assertEquals($before['model_has_roles'],$this->snapshot()['model_has_roles']);
        $this->assertEquals($before['role_has_permissions'],$this->snapshot()['role_has_permissions']);
        $this->assertSame(['ADMINISTRADOR','COMERCIAL'],$u->fresh()->getRoleNames()->all());
        $b=app(B::class);$b->apply($this->legacyRoleNames(),$b->plan($this->legacyRoleNames())['plan_hash'],'QA-NORMALIZED-GRANTS');
        foreach($this->legacyActors as $actor){foreach(B::REQUIRED as $p){$this->assertTrue($actor->fresh()->can($p));}}
        $this->assertFalse($u->fresh()->can(C::APPROVE_ZERO_COST));
    }
    public function test_mixed_guard_shared_permission_and_non_user_bindings_are_blocked(): void
    {
        $p=Permission::create(['name'=>'qa.shared','guard_name'=>'']);
        $api=Role::create(['name'=>'API_ONLY','guard_name'=>'api']);
        foreach([$this->legacyRoles['ADMISION'],$api] as $r){DB::table('role_has_permissions')->insert(['role_id'=>$r->id,'permission_id'=>$p->id]);}
        $n=app(N::class);$plan=$n->plan($this->legacyRoleNames(),[$p->id]);$before=$this->snapshot();
        $this->assertNotEmpty($plan['blockers']);
        try{$n->apply($this->legacyRoleNames(),[$p->id],$plan['plan_hash'],'QA-MIXED');$this->fail('Foreign guard changed');}
        catch(\RuntimeException $e){$this->assertEquals($before,$this->snapshot());}
        DB::table('model_has_roles')->insert(['role_id'=>$this->legacyRoles['CAJA']->id,'model_type'=>'UnknownModel','model_id'=>999]);
        $plan=$n->plan(['CAJA']);$this->assertSame('unsupported_assigned_model',$plan['blockers'][0]['type']);
        $this->assertNotEmpty($n->plan(['API_ONLY'])['blockers']);
    }
    public function test_duplicate_role_or_permission_web_names_cannot_be_merged_or_rebound(): void
    {
        Role::create(['name'=>'ADMISION','guard_name'=>'web']);
        $p=Permission::create(['name'=>C::VIEW,'guard_name'=>'']);
        $n=app(N::class);$plan=$n->plan($this->legacyRoleNames(),[$p->id]);$before=$this->snapshot();
        $this->assertContains('role_missing_or_ambiguous',array_column($plan['blockers'],'type'));
        $this->assertContains('web_name_collision',array_column($plan['blockers'],'type'));
        try{$n->apply($this->legacyRoleNames(),[$p->id],$plan['plan_hash'],'QA-COLLISION');$this->fail('Collision accepted');}
        catch(\RuntimeException $e){$this->assertEquals($before,$this->snapshot());}
    }
    public function test_changed_pivot_or_catalogue_invalidates_the_review_hash(): void
    {
        $n=app(N::class);$plan=$n->plan($this->legacyRoleNames());
        $p=Permission::findOrCreate('qa.later','web');
        DB::table('role_has_permissions')->insert(['role_id'=>$this->legacyRoles['ADMISION']->id,'permission_id'=>$p->id]);
        $before=$this->snapshot();
        try{$n->apply($this->legacyRoleNames(),[],$plan['plan_hash'],'QA-STALE');$this->fail('Stale plan accepted');}
        catch(\RuntimeException $e){$this->assertStringContainsString('plan cambió',$e->getMessage());}
        $this->assertEquals($before,$this->snapshot());
    }
    public function test_rollback_requires_permission_grants_to_be_reversed_first(): void
    {
        $receipt=$this->normalize();$n=app(N::class);$b=app(B::class);
        $grant=$b->apply($this->legacyRoleNames(),$b->plan($this->legacyRoleNames())['plan_hash'],'QA-GRANTS');
        $after=$this->snapshot();
        try{$n->rollback($receipt['id'],$receipt['after']['state_hash'],'QA-UNSAFE-ROLLBACK');$this->fail('Later grants ignored');}
        catch(\RuntimeException $e){$this->assertStringContainsString('relaciones cambiaron',$e->getMessage());}
        $this->assertEquals($after,$this->snapshot());
        $b->rollback($grant['id'],$grant['after']['plan_hash'],'QA-GRANTS-ROLLBACK');
        $n->rollback($receipt['id'],$receipt['after']['state_hash'],'QA-GUARDS-ROLLBACK');
        $this->assertSame(5,DB::table('roles')->where('guard_name','')->count());
        $this->assertSame('web',$this->legacyRoles['COMERCIAL']->fresh()->guard_name);
    }
    public function test_private_receipt_failure_rolls_back_all_guard_changes(): void
    {
        $n=app(N::class);$plan=$n->plan($this->legacyRoleNames());$before=$this->snapshot();
        $disk=\Mockery::mock(\Illuminate\Filesystem\FilesystemAdapter::class);$disk->shouldReceive('put')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->once()->andReturn($disk);
        try{$n->apply($this->legacyRoleNames(),[],$plan['plan_hash'],'QA-IO-FAIL');$this->fail('Repair committed without receipt');}
        catch(\RuntimeException $e){$this->assertStringContainsString('recibo privado',$e->getMessage());}
        $this->assertEquals($before,$this->snapshot());
    }
    public function test_cli_requires_explicit_targets_hash_reference_and_supported_configuration(): void
    {
        $this->withoutMockingConsoleOutput();$before=$this->snapshot();$n=app(N::class);
        $this->assertSame(1,Artisan::call('agenda:mvp-guards',['--apply'=>true]));
        $this->assertSame(1,Artisan::call('agenda:mvp-guards',['--permission-id'=>['1bad']]));
        $this->assertSame(1,Artisan::call('agenda:mvp-guards',['--apply'=>true,'--confirm'=>$n->plan()['plan_hash'],'--reference'=>'QA-EMPTY']));
        $this->assertEquals($before,$this->snapshot());
        config(['permission.teams'=>true]);
        $this->assertSame(1,Artisan::call('agenda:mvp-guards'));
        $this->assertEquals($before,$this->snapshot());
    }
}
