<?php
namespace Tests\Feature\Scheduling;

use App\Models\User;
use App\Support\Scheduling\{ClinicalCapability as K,ClinicalAccess,AdmissionRolePolicy,MvpPermissionBootstrap,SchedulingCapability as C};
use App\Http\Controllers\admin\master\role\RoleController;
use Illuminate\Support\Facades\{DB,Hash};
use Illuminate\Support\Str;
use Spatie\Permission\Models\{Role,Permission};

class MedicalRoleCompatibilityTest extends \Tests\Support\AgendaV2TestCase
{
    private function doctor(string $role='MEDICO 2',bool $agenda=true):User {
        foreach(K::rolePermissions()as $p)Permission::findOrCreate($p,'web');
        Role::findOrCreate($role,'web')->syncPermissions($agenda?K::rolePermissions():K::all());
        $u=User::forceCreate(['id'=>13,'name'=>'ENDOCRINOLOGO FICTICIO QA','email'=>'endocrino@demo.ceosalud.invalid','password'=>Hash::make('FicticioSoloQA!'),'is_active'=>true,'doctor_id'=>$this->catalog['doctor']->id]);$u->assignRole($role);
        $this->a->forceFill(['estado_agenda'=>'CONFIRMADA','estado_cita'=>'PROGRAMADO'])->save();$this->actingAs($u);return $u;
    }
    private function care(string $state,string $expected,?string $key=null){return $this->postJson(route('scheduling.mvp.admission.care',$this->a->id),['state'=>$state,'expected_state'=>$expected,'request_key'=>$key??(string)Str::uuid()]);}
    private function arrive():void {$this->actingAs($this->admission);$this->postJson(route('scheduling.mvp.admission.arrival',$this->a->id),['expected_state'=>'PROGRAMADO','confirmed'=>true,'request_key'=>(string)Str::uuid()])->assertOk();}
    public function test_medico_two_with_agenda_read_grants_can_complete_own_care_and_audited_procedures():void {
        $u=$this->doctor();$this->assertSame(13,$u->id);$this->get(route('scheduling.mvp.admission'))->assertOk();
        $this->care('EN_ATENCION','PROGRAMADO')->assertUnprocessable();$this->arrive();$this->actingAs($u);$k=(string)Str::uuid();
        $this->care('EN_ATENCION','EN_ESPERA',$k)->assertOk();$this->care('EN_ATENCION','EN_ESPERA',$k)->assertOk();
        $this->detail()->assertJsonPath('procedures.can_select',true)->assertJsonPath('procedures.can_order_clinical',true);
        $this->change('selection',['procedures'=>['laboratory','mri'],'custom_procedures'=>[['id'=>(string)Str::uuid(),'label'=>'Procedimiento endocrino ficticio de control','family'=>'laboratory']],
            'clinical_order'=>['diagnosis'=>'DIAGNOSTICO FICTICIO','observations'=>'OBSERVACION FICTICIA'], 'clinical_order_revision'=>\App\Support\Scheduling\ClinicalOrderSpecification::revision(),'expected_patient_id'=>$this->a->patient_id])->assertOk();
        $this->detail()->assertJsonPath('procedures.requested_count',3)->assertJsonPath('procedures.clinical_order.diagnosis','DIAGNOSTICO FICTICIO')->assertJsonPath('medical_followup',null);
        $this->care('ATENDIDO','EN_ATENCION')->assertOk();$this->assertDatabaseHas('appointment_events',['actor_user_id'=>13,'event_type'=>'ADMISION_ESTADO_ACTUALIZADO']);
        $this->assertDatabaseHas('appointment_requested_procedures',['requested_by_user_id'=>13,'procedure_key'=>'laboratory']);
        $this->assertDatabaseCount('doctor_procedure_suggestions',1);$this->assertDatabaseCount('medical_procedure_followups',0);$this->assertDatabaseCount('appointments',1);$this->assertDatabaseCount('payments',0);$this->assertDatabaseCount('vouchers',0);
    }
    public function test_medico_two_can_indicate_withdraw_and_resume_reevaluation_without_finishing_early():void {
        $u=$this->doctor();$this->arrive();$this->actingAs($u);$this->care('EN_ATENCION','EN_ESPERA')->assertOk();$this->select(['laboratory'])->assertOk();
        $decision=fn($action,$extra=[])=>$this->postJson(route('scheduling.mvp.admission.physician-reevaluation',[$this->a->id,$action]),$extra+['expected_state'=>'EN_ATENCION','confirmed'=>true,'request_key'=>(string)Str::uuid()]);
        $decision('indicate')->assertOk();$this->care('ATENDIDO','EN_ATENCION')->assertUnprocessable();
        $decision('withdraw',['reason'=>'Corrección clínica ficticia verificada.'])->assertOk();$decision('indicate')->assertOk();
        $this->actingAs($this->admission);$this->postJson(route('scheduling.mvp.admission.confirm-reevaluation',$this->a->id),['expected_state'=>'EN_ATENCION','confirmed'=>true,'request_key'=>(string)Str::uuid()])->assertOk();$this->complete('laboratory')->assertOk();
        $this->actingAs($u);$this->care('ATENDIDO','EN_ATENCION')->assertUnprocessable();$decision('resume')->assertOk();$this->care('ATENDIDO','EN_ATENCION')->assertOk();
        $this->assertDatabaseHas('appointment_events',['actor_user_id'=>13,'event_type'=>'REEVALUACION_MEDICA_RETOMADA']);
    }
    public function test_agenda_permissions_do_not_expose_other_doctors_reservations_or_operational_feeds():void {
        $u=$this->doctor();$other=$this->lifecycleAppointment($this->admin,$this->createAppointmentCatalog(),['estado_agenda'=>'CONFIRMADA']);
        $private=$this->lifecycleAppointment($this->admin,$this->catalog,['estado_agenda'=>'PENDIENTE_CONFIRMACION']);
        $feed=$this->postJson(route('scheduling.mvp.admission.feed'),['fecha'=>now('America/Lima')->toDateString()])->assertOk()->json();
        $this->assertSame([$this->a->id],array_column($feed['rows'],'id'));$this->assertSame(1,$feed['kpis']['total']);
        foreach([$other,$private]as $a){$this->getJson(route('scheduling.mvp.admission.detail',$a->id))->assertForbidden();$this->change('selection',['procedures'=>['mri']],null,null,$a->id)->assertStatus($a->id===$private->id?404:403);}
        $this->get(route('scheduling.mvp.agenda'))->assertRedirect(route('scheduling.mvp.admission'));
        $this->getJson(route('scheduling.mvp.agenda'))->assertForbidden();
        $this->getJson('/scheduling-mvp/agenda/feed?vista=dia&fecha='.now('America/Lima')->toDateString())->assertForbidden();
        $this->assertFalse(\App\Support\Scheduling\MvpOperationalAccess::reads($u));$this->assertFalse(\App\Support\Scheduling\MvpOperationalAccess::writesPatients($u));
        $this->assertDatabaseCount('appointment_requested_procedures',0);
    }
    public function test_followups_remain_independently_restricted_for_user_thirteen():void {
        $this->doctor();$this->assertSame([2,1,7],config('medical_followups.authorized_user_ids'));
        $this->get('/scheduling-mvp/medical-followups')->assertForbidden();$this->postJson('/scheduling-mvp/medical-followups/feed',[])->assertForbidden();
        $this->postJson(route('scheduling.mvp.medical-followups.refer',$this->a->id),['request_key'=>(string)Str::uuid(),'expected_patient_id'=>$this->a->patient_id,'expected_procedure_version'=>0])->assertForbidden();
        $this->assertDatabaseCount('medical_procedure_followups',0);$this->assertFalse(AdmissionRolePolicy::followups(auth()->user()));
    }
    public function test_inactive_unlinked_wrong_doctor_or_missing_permission_cannot_sign():void {
        $u=$this->doctor();$this->arrive();$this->actingAs($u);$events=$this->a->events()->count();
        foreach([['is_active'=>false],['doctor_id'=>null],['doctor_id'=>$this->createAppointmentCatalog()['doctor']->id]]as $bad){$u->forceFill($bad)->save();$this->actingAs($u->fresh());$this->care('EN_ATENCION','EN_ESPERA')->assertStatus(isset($bad['is_active'])?401:403);$u->forceFill(['is_active'=>true,'doctor_id'=>$this->catalog['doctor']->id])->save();$this->actingAs($u->fresh());}
        Role::findByName('MEDICO 2')->revokePermissionTo(K::START);$this->care('EN_ATENCION','EN_ESPERA')->assertForbidden();
        $this->assertNull($this->a->fresh()->hora_atencion);$this->assertSame($events,$this->a->fresh()->events()->count());
    }
    public function test_privilege_contamination_and_unknown_medical_names_are_rejected():void {
        $u=$this->doctor();$r=Role::findByName('MEDICO 2');$p=Permission::findOrCreate(C::UPDATE,'web');$r->givePermissionTo($p);
        $this->get(route('scheduling.mvp.admission'))->assertForbidden();$r->revokePermissionTo($p);$u->givePermissionTo($p);$this->get(route('scheduling.mvp.admission'))->assertForbidden();$u->revokePermissionTo($p);
        $u->assignRole('COMERCIAL');$this->care('EN_ATENCION','PROGRAMADO')->assertForbidden();$this->assertFalse(AdmissionRolePolicy::operational($u));
        Role::findOrCreate('MEDICO 3')->syncPermissions(K::rolePermissions());$u->syncRoles(['MEDICO 3']);$this->care('EN_ATENCION','PROGRAMADO')->assertForbidden();
    }
    public function test_original_medico_also_accepts_only_safe_agenda_read_grants():void {
        $u=$this->doctor('MEDICO');$this->get(route('scheduling.mvp.admission'))->assertOk();$this->assertSame($u->id,ClinicalAccess::actor($u)->id);
        $this->arrive();$this->actingAs($u);$this->care('EN_ATENCION','EN_ESPERA')->assertOk();$this->select(['radiography'])->assertOk();$this->care('ATENDIDO','EN_ATENCION')->assertOk();
    }
    public function test_roles_editor_allows_navigation_and_rejects_financial_or_administrative_grants_for_both_names():void {
        foreach(K::ROLES as $name){$role=Role::findOrCreate($name);$this->actingAs($this->admin);$ids=[];foreach(K::rolePermissions()as $p)$ids[]=Permission::findOrCreate($p,'web')->id;
            $url=route('admin.permissions.update',$role);$payload=['name'=>$name,'permissions_present'=>1,'permission_revision'=>RoleController::revision($role),'permissions'=>$ids];
            $this->put($url,$payload)->assertRedirect();$this->assertEqualsCanonicalizing(K::rolePermissions(),$role->permissions()->pluck('name')->all());
            foreach([C::CREATE,C::UPDATE,C::SUBMIT_PAYMENT,C::EDIT_PRICING,'GESTION_USUARIOS']as $unsafe){$bad=$payload;$bad['permission_revision']=RoleController::revision($role);$bad['permissions'][]=Permission::findOrCreate($unsafe,'web')->id;$this->putJson($url,$bad)->assertUnprocessable();}
        }
    }
    public function test_bootstrap_never_adds_operational_or_payment_grants_to_either_medical_role():void {
        foreach(K::ROLES as $name)Role::findOrCreate($name);foreach(MvpPermissionBootstrap::REQUIRED as $p)Permission::findOrCreate($p,'web');
        foreach(app(MvpPermissionBootstrap::class)->plan(K::ROLES)['roles']as $r)$this->assertSame([],$r['additions']);
    }
    public function test_each_clinical_permission_is_still_required_for_its_action():void {
        $u=$this->doctor();$this->arrive();$this->actingAs($u);$this->care('EN_ATENCION','EN_ESPERA')->assertOk();$role=Role::findByName('MEDICO 2');
        $role->revokePermissionTo(K::PROCEDURES);$this->select(['laboratory'])->assertForbidden();$role->givePermissionTo(K::PROCEDURES);$this->select(['laboratory'])->assertOk();
        $decision=fn($action,$extra=[])=>$this->postJson(route('scheduling.mvp.admission.physician-reevaluation',[$this->a->id,$action]),$extra+['expected_state'=>'EN_ATENCION','confirmed'=>true,'request_key'=>(string)Str::uuid()]);
        $role->revokePermissionTo(K::INDICATE);$decision('indicate')->assertForbidden();$role->givePermissionTo(K::INDICATE);$decision('indicate')->assertOk();
        $role->revokePermissionTo(K::WITHDRAW);$decision('withdraw',['reason'=>'Corrección clínica ficticia autorizada.'])->assertForbidden();$role->givePermissionTo(K::WITHDRAW);$decision('withdraw',['reason'=>'Corrección clínica ficticia autorizada.'])->assertOk();
        $role->revokePermissionTo(K::FINISH);$this->care('ATENDIDO','EN_ATENCION')->assertForbidden();$role->givePermissionTo(K::FINISH);$this->care('ATENDIDO','EN_ATENCION')->assertOk();
        $role->revokePermissionTo(K::VIEW);$this->detail()->assertForbidden();
    }
    public function test_documents_and_alerts_keep_clinical_ownership_and_operational_actions_denied():void {
        $u=$this->doctor();$d=$this->detail()->assertOk()->json();$this->assertNotNull($d['patient_documents_url']);$this->assertNull($d['patient_alert_edit_url']??null);
        $this->getJson($d['patient_documents_url'])->assertOk();$other=$this->lifecycleAppointment($this->admin,$this->createAppointmentCatalog(),['estado_agenda'=>'CONFIRMADA']);
        $this->getJson(route('scheduling.mvp.admission.documents.index',$other->id))->assertForbidden();
        $this->assertFalse(AdmissionRolePolicy::operational($u));$this->assertFalse(AdmissionRolePolicy::administrator($u));
        $this->change('completion',['procedure'=>'laboratory','completed'=>true])->assertForbidden();
        $this->patchJson(route('scheduling.mvp.admission.manual-reevaluation',$this->a->id),[])->assertForbidden();
    }
    public function test_julio_user_two_keeps_medical_administrative_identity_and_independent_followup_access():void {
        foreach(K::all()as $p)Permission::findOrCreate($p,'web');Role::findOrCreate('MEDICO')->syncPermissions(K::all());
        // Transaction rollback does not rewind MariaDB AUTO_INCREMENT. Pin the
        // fictitious legacy identity instead of assuming the fixture's sequence.
        $julio=User::find(2)??User::forceCreate(['id'=>2,'name'=>'JULIO FICTICIO QA','email'=>'julio@demo.ceosalud.invalid','password'=>Hash::make('FicticioSoloQA!'),'is_active'=>true]);
        $this->assertSame(2,$julio->id);$julio->syncRoles(['MEDICO','ADMINISTRADOR']);$julio->givePermissionTo([C::MVP_ACCESS,C::VIEW]);$julio->forceFill(['doctor_id'=>$this->catalog['doctor']->id])->save();
        $this->a->forceFill(['estado_agenda'=>'CONFIRMADA','estado_cita'=>'EN_ESPERA','hora_llegada'=>now()])->save();$this->actingAs($julio);
        $this->assertSame(2,ClinicalAccess::actor($julio)->id);$this->get('/scheduling-mvp/medical-followups')->assertOk();$this->get(route('scheduling.mvp.agenda'))->assertOk();
        $this->care('EN_ATENCION','EN_ESPERA')->assertOk();$this->select(['mri'])->assertOk();$this->assertDatabaseCount('medical_procedure_followups',1);$this->care('ATENDIDO','EN_ATENCION')->assertOk();
        $this->assertTrue($julio->fresh()->hasAllRoles(['MEDICO','ADMINISTRADOR']));$this->assertSame([2,1,7],config('medical_followups.authorized_user_ids'));
    }
    public function test_existing_user_editor_assigns_medico_two_without_changing_doctor_or_granting_admin():void {
        foreach(K::rolePermissions()as $p)Permission::findOrCreate($p,'web');$role=Role::findOrCreate('MEDICO 2');$role->syncPermissions(K::rolePermissions());
        $target=User::forceCreate(['id'=>13,'name'=>'CUENTA ENDOCRINOLOGO FICTICIA','email'=>'endocrino@demo.ceosalud.invalid','password'=>Hash::make('FicticioSoloQA!'),'is_active'=>true,'doctor_id'=>$this->catalog['doctor']->id]);$before=$target->auth_version;
        $this->actingAs($this->admin);$this->put(route('admin.user.update',$target->id),['role'=>$role->id])->assertRedirect();
        $target=$target->fresh();$this->assertSame(['MEDICO 2'],$target->getRoleNames()->all());$this->assertSame($this->catalog['doctor']->id,$target->doctor_id);$this->assertGreaterThan($before,$target->auth_version);
        $this->assertDatabaseHas('user_account_events',['target_user_id'=>13,'event_type'=>'ROLES_ASSIGNED']);
    }
    public function test_medico_two_administrator_removal_still_needs_explicit_confirmation_in_existing_editor():void {
        $u=$this->doctor();$u->assignRole('ADMINISTRADOR');$this->actingAs($this->admin);
        $this->get(route('admin.user.edit',$u->id))->assertOk()->assertSee('confirm_remove_medico',false);
        $this->putJson(route('admin.user.update',$u->id),['role'=>Role::findByName('ADMINISTRADOR')->id])->assertUnprocessable();
        $this->assertTrue($u->fresh()->hasAllRoles(['MEDICO 2','ADMINISTRADOR']));
    }
}
