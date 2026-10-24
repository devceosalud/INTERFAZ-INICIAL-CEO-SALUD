<?php
namespace App\Support\Scheduling;
use App\Models\{User,Appointment};

/** Role precedence is deliberately restrictive: MEDICO > ADMINISTRADOR > operations. */
final class AdmissionRolePolicy
{
    public static function fresh(User $u): User {
        $fresh=User::findOrFail($u->id);abort_unless($fresh->is_active && (int)$fresh->auth_version===(int)$u->auth_version,403);return $fresh;
    }
    public static function operational(User $u): bool {
        $u=self::fresh($u);
        return !ClinicalCapability::isMedical($u) && !$u->hasRole('ADMINISTRADOR') && $u->hasAnyRole(['ADMISION','COMERCIAL'])
            && ClinicalAccess::has($u,SchedulingCapability::MVP_ACCESS) && ClinicalAccess::has($u,SchedulingCapability::VIEW);
    }
    public static function administrator(User $u): bool {
        $u=self::fresh($u);return !\App\Support\Scheduling\ClinicalCapability::isMedical($u) && $u->hasRole('ADMINISTRADOR')
            && ClinicalAccess::has($u,SchedulingCapability::MVP_ACCESS) && ClinicalAccess::has($u,SchedulingCapability::VIEW);
    }
    public static function alertAppointment(User $u,int $id): Appointment {
        $u=self::fresh($u);
        if(\App\Support\Scheduling\ClinicalCapability::isMedical($u)){ClinicalAccess::actor($u);$a=Appointment::findOrFail($id);ClinicalAccess::appointment($u,$a);return $a;}
        abort_unless(self::operational($u)||self::administrator($u),403);
        return Appointment::visibleToOperationalUser($u)->findOrFail($id);
    }
    public static function documentsAppointment(User $u,int $id): Appointment {
        $u=self::fresh($u);
        if(\App\Support\Scheduling\ClinicalCapability::isMedical($u)){$u=ClinicalAccess::actor($u);$a=Appointment::findOrFail($id);ClinicalAccess::appointment($u,$a);return $a;}
        abort_unless(self::administrator($u),403);return Appointment::visibleToOperationalUser($u)->findOrFail($id);
    }
    public static function history(User $u): bool {return \App\Support\Scheduling\ClinicalCapability::isMedical($u)||self::administrator($u);}
    public static function followups(User $u): bool {
        try {MedicalFollowupAccess::actor($u);return true;}
        catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){if($e->getStatusCode()!==403)throw $e;return false;}
    }
}
