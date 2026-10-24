<?php
namespace App\Http\Middleware;

use App\Support\Scheduling\MvpOperationalAccess;
use App\Support\Scheduling\MedicalLanding;
use Closure;
use Illuminate\Http\Request;

class EnsureMvpOperationalAccess
{
    public function handle(Request $request, Closure $next, string $mode = 'read')
    {
        // A verified non-administrative physician opening a bookmarked Dashboard
        // belongs in Admission, not in the operational/economic Dashboard.
        if ($mode === 'read' && $request->isMethod('GET')
            && $request->routeIs('admin.dashboard.index') && !$request->expectsJson()
            && $request->user() && !$request->user()->hasRole('ADMINISTRADOR', 'web')
            && MedicalLanding::admissionFor($request->user())) {
            return redirect()->route('scheduling.mvp.admission');
        }
        abort_unless($mode === 'write' ? MvpOperationalAccess::writesPatients($request->user())
            : MvpOperationalAccess::reads($request->user()), 403);
        return $next($request);
    }
}
