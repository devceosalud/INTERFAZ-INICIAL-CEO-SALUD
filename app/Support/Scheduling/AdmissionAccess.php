<?php
namespace App\Support\Scheduling;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

/** Admission-only read gate. Grants no generic Agenda, financial or administrative access. */
final class AdmissionAccess
{
    public static function operational(User $actor): bool
    {
        $u=$actor->fresh();
        if($u && ClinicalCapability::isMedical($u) && !$u->hasRole('ADMINISTRADOR','web'))return false;
        return $u && $u->is_active && ClinicalAccess::has($u,SchedulingCapability::MVP_ACCESS)
            && ClinicalAccess::has($u,SchedulingCapability::VIEW);
    }
    public static function clinicalOnly(User $actor): bool
    {
        if(self::operational($actor))return false;
        ClinicalAccess::actor($actor);
        return true;
    }
    public function handle(Request $request,Closure $next)
    {
        self::clinicalOnly($request->user());
        return $next($request);
    }
}
