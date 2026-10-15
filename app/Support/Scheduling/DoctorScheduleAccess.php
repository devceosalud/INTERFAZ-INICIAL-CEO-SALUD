<?php
namespace App\Support\Scheduling;
use App\Models\User;
final class DoctorScheduleAccess
{
    public const MANAGE='doctor.schedule.manage';
    public static function allows(?User $user):bool
    {
        return $user && $user->is_active && $user->hasAnyRole(['ADMINISTRADOR','ADMISION','COMERCIAL'])
            && $user->can(self::MANAGE);
    }
    public static function authorize(?User $user):void {abort_unless(self::allows($user),403,'Gestionar horarios requiere doctor.schedule.manage y un perfil operativo autorizado.');}
}
