<?php
namespace App\Support\Scheduling;

final class ClinicalCapability
{
    public const ROLE = 'MEDICO';
    /** Explicit legacy compatibility, never a name-prefix wildcard. */
    public const ROLES = [self::ROLE, 'MEDICO 2'];

    public static function isRole(string $name): bool {return in_array($name,self::ROLES,true);}
    public static function isMedical(\App\Models\User $user): bool {
        return $user->roles()->whereIn('name',self::ROLES)->where('guard_name','web')->exists();
    }
    /** Navigation/read grants only; no operational, financial or patient-master writes. */
    public static function rolePermissions(): array {
        return array_merge(self::all(),[SchedulingCapability::MVP_ACCESS,SchedulingCapability::VIEW]);
    }
    public const VIEW = 'appointment.clinical.view';
    public const START = 'appointment.clinical.start';
    public const FINISH = 'appointment.clinical.finish';
    public const INDICATE = 'appointment.clinical.reevaluation.indicate';
    public const WITHDRAW = 'appointment.clinical.reevaluation.withdraw';
    public const PROCEDURES = 'appointment.clinical.procedures.request';

    public static function all(): array
    {
        return [self::VIEW,self::START,self::FINISH,self::INDICATE,self::WITHDRAW,self::PROCEDURES];
    }
}
