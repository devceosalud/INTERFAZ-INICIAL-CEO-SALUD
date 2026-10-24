<?php
namespace App\Support\Scheduling;

use App\Models\{Appointment,Doctor,User};
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/** Clinical identity only. Never expands administrative or financial capabilities. */
final class ClinicalAccess
{
    public static function has(User $user,string $capability): bool
    {
        // Read effective grants from Spatie relations, bypassing request-local permission caches.
        return $user->permissions()->where('name',$capability)->where('guard_name','web')->exists()
            || $user->roles()->where('guard_name','web')->whereHas('permissions',fn($q)=>$q->where('name',$capability)->where('guard_name','web'))->exists();
    }

    public static function actor(User $actor,string $capability=ClinicalCapability::VIEW): User
    {
        $fresh=User::find($actor->id);
        abort_unless($fresh && $fresh->is_active
            && ClinicalCapability::isMedical($fresh)
            && self::has($fresh,ClinicalCapability::VIEW) && self::has($fresh,$capability)
            && $fresh->doctor_id && Doctor::whereKey($fresh->doctor_id)->where('estado','ACTIVO')->exists(),403,
            'Se requiere una cuenta médica activa, vinculada y autorizada.');
        // Only an explicitly verified account with MEDICO and its own doctor link may
        // perform clinical actions. Its pre-existing ADMINISTRADOR role can be retained,
        // but it does not grant medical access to any administrator by itself.
        $isAdministrator=$fresh->roles()->where('name','ADMINISTRADOR')->where('guard_name','web')->exists();
        abort_if($fresh->roles()->whereNotIn('name',array_merge(ClinicalCapability::ROLES,['ADMINISTRADOR']))->exists()
            || $fresh->roles()->whereIn('name',ClinicalCapability::ROLES)->whereHas('permissions',
                fn($q)=>$q->whereNotIn('name',ClinicalCapability::rolePermissions()))->exists()
            || (!$isAdministrator && $fresh->permissions()->whereNotIn('name',ClinicalCapability::rolePermissions())->exists()),403,
            'Revisa los roles y permisos: se requiere MEDICO o MEDICO 2, con capacidades clínicas y acceso de lectura; ADMINISTRADOR por sí solo no concede acceso médico.');
        return $fresh;
    }

    public static function appointment(User $actor,Appointment $a): void
    {
        abort_unless((int)$actor->doctor_id===(int)$a->doctor_id
            && in_array($a->estado_agenda,['LEGADO','CONFIRMADA'],true),403,'La cita no pertenece a tu atención autorizada.');
    }

    /** Caller owns the transaction. Serializes role permissions, identity, doctor and appointment. */
    public static function lock(User $actor,int $id,string $capability): array
    {
        abort_unless(DB::transactionLevel()>0,500);
        abort_if(Role::whereIn('name',ClinicalCapability::ROLES)->where('guard_name','web')->orderBy('id')->lockForUpdate()->get()->isEmpty(),403);
        $fresh=User::whereKey($actor->id)->lockForUpdate()->firstOr(fn()=>abort(403));
        abort_unless($fresh->is_active && (int)$fresh->auth_version===(int)$actor->auth_version
            && (int)$fresh->doctor_id===(int)$actor->doctor_id && $fresh->doctor_id,403,'El acceso médico cambió. Vuelve a ingresar.');
        Doctor::whereKey($fresh->doctor_id)->lockForUpdate()->firstOr(fn()=>abort(403));
        // actor() rechecks live roles, grants and professional state after identity locks.
        $fresh=self::actor($fresh,$capability);
        $a=Appointment::whereKey($id)->lockForUpdate()->firstOr(fn()=>abort(404));
        self::appointment($fresh,$a);
        return [$fresh,$a];
    }

    public static function activeCare(Appointment $a): void
    {
        abort_unless(($a->attendance_mode??'PRESENCIAL')==='PRESENCIAL' && !$a->hora_atendido && !$a->hora_salida,422,
            'La acción requiere una cita presencial activa, sin finalización ni salida.');
    }
}
