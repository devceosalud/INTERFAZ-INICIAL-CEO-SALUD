<?php
namespace App\Services;

use App\Models\User;
use App\Models\UserAccountEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/** Never deletes workers or changes their appointments, roles or financial permissions. */
class UserAccountAccess
{
    public function lockAdministrator(User $actor,array $targetIds=[]): User
    {
        // Stable lock serializes deactivation and role changes, including two administrators.
        Role::where('name','ADMINISTRADOR')->where('guard_name','web')->lockForUpdate()->firstOr(fn()=>abort(422,'Normaliza el rol ADMINISTRADOR mediante el procedimiento controlado antes de administrar accesos.'));
        $users=User::whereIn('id',array_unique(array_merge([$actor->id],$targetIds)))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $fresh=$users->get($actor->id);
        abort_unless($fresh && $fresh->is_active && $fresh->hasRole('ADMINISTRADOR','web'),403);
        return $fresh;
    }

    public function changeState(int $targetId,User $actor,array $data): User
    {
        return DB::transaction(function()use($targetId,$actor,$data){
            $freshActor=$this->lockAdministrator($actor,[$targetId]);
            $target=User::whereKey($targetId)->lockForUpdate()->firstOrFail();
            $hash=hash('sha256',json_encode(['ACCOUNT_STATE',$targetId,$data['active'],$data['expected_version'],$data['reason']]));
            $event=UserAccountEvent::where('actor_user_id',$actor->id)->where('request_key',$data['request_key'])->first();
            if($event){abort_unless(hash_equals($event->payload_hash,$hash),409,'El identificador ya corresponde a otra actuación.');return $target;}
            abort_unless((int)$target->auth_version===(int)$data['expected_version'],409,'El acceso del trabajador cambió. Actualiza antes de continuar.');
            abort_if(!$data['active'] && $targetId===$freshActor->id,422,'No puedes desactivar tu propia cuenta. Otro administrador autorizado debe realizar esa actuación.');
            $before=['active'=>(bool)$target->is_active,'auth_version'=>(int)$target->auth_version];
            if((bool)$target->is_active !== $data['active']){
                $target->forceFill(['is_active'=>$data['active']]);
                $this->rotateAccess($target);$target->save();
            }
            $this->record($target,$freshActor,'ACCESS_STATE_CHANGED',$data['request_key'],$hash,$data['reason'],
                ['before'=>$before,'after'=>['active'=>(bool)$target->is_active,'auth_version'=>(int)$target->auth_version],
                    'changed'=>$before['active']!==(bool)$target->is_active,'roles_preserved'=>true]);
            return $target;
        },3);
    }

    /** Caller owns the user transaction/lock. Epoch revokes file/cookie/Redis sessions logically. */
    public function rotateAccess(User $target): void
    {
        $target->forceFill(['auth_version'=>(int)$target->auth_version+1,'remember_token'=>Str::random(60)]);
        $target->tokens()->delete();
        if(config('session.driver')==='database' && Schema::hasTable(config('session.table','sessions')))
            DB::table(config('session.table','sessions'))->where('user_id',$target->id)->delete();
    }

    public function assertAdministratorRemains(User $target,array $newRoleIds): void
    {
        if(!$target->is_active || !$target->hasRole('ADMINISTRADOR','web'))return;
        $adminId=Role::where('name','ADMINISTRADOR')->where('guard_name','web')->value('id');
        if(in_array((int)$adminId,array_map('intval',$newRoleIds),true))return;
        abort_unless(User::where('is_active',true)->where('id','<>',$target->id)->whereHas('roles',fn($q)=>$q->where('roles.id',$adminId))->exists(),422,
            'Debe permanecer otro administrador activo antes de retirar este rol.');
    }

    public function record(User $target,User $actor,string $type,string $key,string $hash,?string $reason,array $metadata): void
    {
        UserAccountEvent::create(['target_user_id'=>$target->id,'actor_user_id'=>$actor->id,'event_type'=>$type,
            'request_key'=>$key,'payload_hash'=>$hash,'reason'=>$reason,'metadata'=>$metadata]);
        // No names, DNI, email, passwords, tokens or free-text reasons in application logs.
        Log::notice('authorization.user_account_changed',['actor_user_id'=>$actor->id,'target_user_id'=>$target->id,'event_type'=>$type]);
    }
}
