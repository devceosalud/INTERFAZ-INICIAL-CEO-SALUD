<?php

namespace App\Http\Middleware;

use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @param  string|null  ...$guards
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next, ...$guards)
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                $user=Auth::guard($guard)->user();
                // Match the post-login destination for an already authenticated session.
                if (\App\Support\Authorization\AdmissionHomeRedirect::applies($user)) {
                    return redirect()->route('scheduling.mvp.admission');
                }
                if($user->hasRole('MEDICO') && !$user->hasAnyRole(\App\Support\Scheduling\MvpOperationalAccess::LEGACY_ROLES)){
                    return redirect()->route('scheduling.medical.index');
                }
                return redirect(RouteServiceProvider::HOME);
            }
        }

        return $next($request);
    }
}
