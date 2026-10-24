<?php
namespace App\Http\Controllers\admin\master\user;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserAccountAccess;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(){ $this->middleware(['auth','role:ADMINISTRADOR']); }
    public function index()
    {
        return response()->view('admin.user.index',['users'=>User::with(['roles','doctor:id,nombre'])->orderBy('name')->get()])
            ->header('Cache-Control','private, no-store');
    }
    public function store(Request $r,UserAccountAccess $access)
    {
        $validator=Validator::make($r->all(),['nombre_usuario'=>'required|string|max:255',
            'email'=>['required','email','max:255',Rule::unique('users','email')],
            'dni'=>['required','string','regex:/\A[0-9]{8}\z/',Rule::unique('users','dni')],
            'password'=>'required|string|min:8|max:128']+$this->protectedFields());
        if($validator->fails())return $this->invalid($validator->errors()->toArray());
        $d=$validator->validated();
        try{DB::transaction(function()use($d,$r,$access){
            $actor=$access->lockAdministrator($r->user());
            $user=User::create(['name'=>$d['nombre_usuario'],'email'=>$d['email'],'dni'=>$d['dni'],'password'=>Hash::make($d['password'])]);
            $key=(string)Str::uuid();$access->record($user,$actor,'IDENTITY_CREATED',$key,hash('sha256','IDENTITY_CREATED:'.$key),null,
                ['fields'=>['name','email','dni','password'],'roles_assigned'=>false]);
        },3);}catch(QueryException $error){return $this->uniqueFailure($error);}
        return response()->json(['code'=>1,'msg'=>'Usuario creado correctamente. Asigna sus roles con el editor existente.']);
    }
    public function updateUser(Request $r,UserAccountAccess $access)
    {
        $identity=Validator::make($r->only('usuario_id_edit'),['usuario_id_edit'=>'required|integer|min:1']);
        if($identity->fails())return $this->invalid($identity->errors()->toArray());
        $user=User::findOrFail($r->input('usuario_id_edit'));
        $validator=$this->identityValidator($r,$user);
        if($validator->fails())return $this->invalid($validator->errors()->toArray());$d=$validator->validated();
        try{DB::transaction(function()use($d,$r,$access,$user){
            $actor=$access->lockAdministrator($r->user(),[$user->id]);$target=User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            // Revalidate against the locked identity: a stale form cannot clear a concurrently assigned DNI.
            $d=$this->identityValidator($r,$target)->validate();
            $changes=['name'=>$d['nombre_usuario_edit'],'email'=>$d['email_edit']];
            if(array_key_exists('dni_edit',$d))$changes['dni']=$d['dni_edit'];
            if(!empty($d['password'])){$changes['password']=Hash::make($d['password']);$access->rotateAccess($target);}
            $target->fill($changes);$fields=array_keys($target->getDirty());$target->save();
            $key=(string)Str::uuid();$access->record($target,$actor,'IDENTITY_UPDATED',$key,hash('sha256','IDENTITY_UPDATED:'.$key),null,
                ['fields'=>array_values(array_diff($fields,['remember_token'])),'sessions_revoked'=>!empty($d['password'])]);
        },3);}catch(\Illuminate\Validation\ValidationException $error){return $this->invalid($error->errors());}
        catch(QueryException $error){return $this->uniqueFailure($error);}
        return response()->json(['code'=>1,'msg'=>'Usuario actualizado correctamente.']);
    }
    public function state(Request $r,int $userId,UserAccountAccess $access)
    {
        $d=$r->validate(['active'=>'required|boolean','expected_version'=>'required|integer|min:0',
            'request_key'=>'required|uuid','reason'=>'required|string|min:3|max:500']);$d['active']=(bool)$d['active'];
        $target=$access->changeState($userId,$r->user(),$d);
        return response()->json(['code'=>1,'msg'=>'Estado de acceso registrado. Los cambios conservan roles e historial.',
            'user'=>['id'=>$target->id,'is_active'=>$target->is_active,'auth_version'=>$target->auth_version]])->header('Cache-Control','private, no-store');
    }
    public function edit(int $user)
    {
        $user=User::with('doctor')->findOrFail($user);
        $events=\App\Models\UserAccountEvent::where('target_user_id',$user->id)->where('event_type',\App\Services\UserDoctorLink::EVENT)->orderByDesc('id')->paginate(30);
        return response()->view('admin.user.edit',['user'=>$user,'target'=>$user,
            'doctors'=>\App\Models\Doctor::with('user:id,doctor_id,name')->orderBy('nombre')->get(),
            'events'=>$events,'people'=>User::whereIn('id',$events->pluck('actor_user_id'))->pluck('name','id'),
            'roles'=>Role::where('guard_name','web')->orderBy('name')->get()])
            ->header('Cache-Control','private, no-store');
    }
    public function update(int $user,Request $r,UserAccountAccess $access)
    {
        $user=User::findOrFail($user);
        $d=$r->validate(['role'=>['required','integer',Rule::exists('roles','id')->where('guard_name','web')],
            'confirm_remove_medico'=>'sometimes|accepted']);
        DB::transaction(function()use($user,$r,$d,$access){
            $actor=$access->lockAdministrator($r->user(),[$user->id]);$target=User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $before=$target->roles()->pluck('roles.id')->map(fn($id)=>(int)$id)->sort()->values()->all();
            $selectedRole=Role::findOrFail($d['role']);
            $isMedical=\App\Support\Scheduling\ClinicalCapability::isRole($selectedRole->name);
            $isAdministrator=$target->hasRole('ADMINISTRADOR','web');
            if(!$isMedical && $isAdministrator && \App\Support\Scheduling\ClinicalCapability::isMedical($target)){
                abort_unless($r->boolean('confirm_remove_medico'),422,
                    'Confirma expresamente la retirada de MEDICO de esta cuenta administrativa.');
            }
            if($isMedical){
                abort_if($target->id===$actor->id,403,'Otro administrador debe validar tu acceso médico.');
                $clinical=\App\Support\Scheduling\ClinicalCapability::rolePermissions();
                // Explicitly support the legacy administrator's same account. Never silently
                // replace staff roles or strip direct permissions to make MEDICO fit.
                abort_if($target->roles()->whereNotIn('name',array_merge(\App\Support\Scheduling\ClinicalCapability::ROLES,['ADMINISTRADOR']))->exists(),422,
                    'Esta cuenta posee otros roles. No se sustituyen silenciosamente al agregar MEDICO.');
                abort_if($selectedRole->permissions()->whereNotIn('name',$clinical)->exists()
                    || (!$isAdministrator && $target->permissions()->whereNotIn('name',$clinical)->exists()),422,
                    'Revisa los permisos directos de esta cuenta o del rol MEDICO; no se eliminan automáticamente.');
            }
            $proposed=$isMedical?array_values(array_unique(array_merge($before,[(int)$selectedRole->id]))):[(int)$selectedRole->id];
            $access->assertAdministratorRemains($target,$proposed);
            if($isMedical)$target->assignRole($selectedRole);
            else $target->syncRoles($selectedRole);
            $after=$target->roles()->pluck('roles.id')->map(fn($id)=>(int)$id)->sort()->values()->all();
            // Revoke only the changed medical account's sessions, not those of other workers.
            $medicalRoleIds=Role::whereIn('name',\App\Support\Scheduling\ClinicalCapability::ROLES)->where('guard_name','web')->pluck('id')->map(fn($id)=>(int)$id)->all();
            if($before!==$after && (array_intersect($medicalRoleIds,$before) || array_intersect($medicalRoleIds,$after))){
                $access->rotateAccess($target);$target->save();
            }
            \Illuminate\Support\Facades\Log::notice('authorization.user_role_updated',['actor_user_id'=>$actor->id,
                'target_user_id'=>$target->id,'before_role_ids'=>$before,'after_role_ids'=>$after]);
            $key=(string)Str::uuid();
            $access->record($target,$actor,'ROLES_ASSIGNED',$key,hash('sha256','ROLES_ASSIGNED:'.$key),null,
                ['before_role_ids'=>$before,'after_role_ids'=>$after]);
        },3);
        return redirect()->route('admin.user.index')->with('exito','Rol asignado correctamente al usuario');
    }
    private function identityValidator(Request $r,User $user)
    {
        $dniRules=['sometimes','nullable','string',Rule::unique('users','dni')->ignore($user->id)];
        if($r->input('dni_edit')!==$user->dni)$dniRules[]='regex:/\A[0-9]{8}\z/';
        if($user->dni!==null)$dniRules[]='required';
        $emailRules=['required','string','max:255',Rule::unique('users','email')->ignore($user->id)];
        if($r->input('email_edit')!==$user->email)$emailRules[]='email';
        return Validator::make($r->all(),['usuario_id_edit'=>'required|integer','nombre_usuario_edit'=>'required|string|max:255',
            'email_edit'=>$emailRules,'dni_edit'=>$dniRules,'password'=>'nullable|string|min:8|max:128']+$this->protectedFields());
    }
    private function protectedFields():array{return ['is_active'=>'prohibited','auth_version'=>'prohibited','role'=>'prohibited','permissions'=>'prohibited','doctor_id'=>'prohibited'];}
    private function invalid(array $errors){return response()->json(['code'=>0,'error'=>$errors]);}
    private function uniqueFailure(QueryException $error)
    {
        if(($error->errorInfo[0]??null)==='23000' && (str_contains($error->getMessage(),'users_worker_dni_unique')||str_contains($error->getMessage(),'users.dni')||str_contains($error->getMessage(),'users_email_unique')||str_contains($error->getMessage(),'users.email')))
            return $this->invalid(['dni'=>['El DNI o correo ya está registrado. Actualiza y verifica la identidad.'],'dni_edit'=>['El DNI o correo ya está registrado.']]);
        throw $error;
    }
}
