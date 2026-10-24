<?php
namespace App\Support\Scheduling;

use Closure;
use Illuminate\Http\Request;

/** Read grants are navigation, never a bypass to the operational patient/economy feeds. */
final class ClinicalAgendaBoundary
{
    public function handle(Request $request,Closure $next)
    {
        $user=$request->user();
        if($user && ClinicalCapability::isMedical($user) && !$user->hasRole('ADMINISTRADOR','web')){
            $user=ClinicalAccess::actor($user);
            if($request->routeIs('scheduling.mvp.agenda') && $request->isMethod('GET') && !$request->expectsJson())
                return redirect()->route('scheduling.mvp.admission');
            if($request->routeIs('scheduling.mvp.admission.reevaluation-history')){
                ClinicalAccess::appointment($user,\App\Models\Appointment::findOrFail((int)$request->route('appointmentId')));
                return $next($request);
            }
            abort_unless($request->routeIs('scheduling.mvp.access'),403,
                'Tu acceso médico se realiza desde Agenda de admisión, sobre tus citas autorizadas.');
        }
        return $next($request);
    }
}
