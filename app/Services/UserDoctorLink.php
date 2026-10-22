<?php
namespace App\Services;

use App\Models\{Doctor,User,UserAccountEvent};
use Illuminate\Support\Facades\DB;

final class UserDoctorLink
{
    public const EVENT = 'DOCTOR_LINK_CHANGED';

    public function change(int $id,User $actor,array $data): User
    {
        abort_if($id===$actor->id,403,'No puedes modificar tu propia vinculación médica.');
        return DB::transaction(function()use($id,$actor,$data){
            $access=app(UserAccountAccess::class);
            $admin=$access->lockAdministrator($actor,[$id]);
            $target=User::whereKey($id)->lockForUpdate()->firstOrFail();
            $next=isset($data['doctor_id'])?(int)$data['doctor_id']:null;
            $hash=hash('sha256',json_encode(['DOCTOR_LINK_V1',$id,$next,(int)$data['expected_version'],
                $data['expected_doctor_id']??null,trim($data['reason'])]));
            $previous=UserAccountEvent::where('actor_user_id',$admin->id)->where('request_key',$data['request_key'])->first();
            if($previous){
                abort_unless($previous->event_type===self::EVENT && hash_equals($previous->getRawOriginal('payload_hash'),$hash),409,'El identificador corresponde a otra operación.');
                return $target;
            }
            abort_unless($data['confirmed'] && $data['identity_confirmed'],422,'Confirma personalmente la identidad y la modificación.');
            abort_unless((int)$target->auth_version===(int)$data['expected_version']
                && ($target->doctor_id===null?null:(int)$target->doctor_id)===($data['expected_doctor_id']===null?null:(int)$data['expected_doctor_id']),409,
                'El usuario o su vínculo cambiaron. Actualiza antes de guardar.');
            abort_if(($target->doctor_id===null?null:(int)$target->doctor_id)===$next,422,'No hay una modificación de vínculo para guardar.');
            $ids=array_values(array_filter([$target->doctor_id,$next]));
            $doctors=Doctor::whereIn('id',$ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if($next!==null){
                abort_unless($target->is_active && isset($doctors[$next]) && $doctors[$next]->estado==='ACTIVO',422,'La cuenta y el médico deben estar activos.');
                abort_if(User::where('doctor_id',$next)->where('id','<>',$id)->exists(),409,'El médico ya tiene una cuenta vinculada. Reutiliza esa cuenta tras verificar su identidad.');
            }
            $before=['doctor_id'=>$target->doctor_id===null?null:(int)$target->doctor_id,'auth_version'=>(int)$target->auth_version];
            $target->forceFill(['doctor_id'=>$next]);
            $access->rotateAccess($target);
            $target->save();
            $access->record($target,$admin,self::EVENT,$data['request_key'],$hash,trim($data['reason']),
                ['version'=>1,'identity_confirmed'=>true,'before'=>$before,
                    'after'=>['doctor_id'=>$next,'auth_version'=>(int)$target->auth_version],
                    'roles_preserved'=>true,'sessions_revoked'=>true]);
            return $target;
        },3);
    }
}
