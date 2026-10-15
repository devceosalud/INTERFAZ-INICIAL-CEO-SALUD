<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    protected function authenticate($request, array $guards)
    {
        parent::authenticate($request,$guards);
        $user=$request->user();
        $state=\App\Models\User::whereKey($user->getAuthIdentifier())->first(['id','is_active','auth_version']);
        $allowed=$state && $state->is_active;
        $token=method_exists($user,'currentAccessToken')?$user->currentAccessToken():null;
        $tokenAuthenticated=\Illuminate\Support\Facades\Auth::getDefaultDriver()==='sanctum'
            && $token instanceof \Laravel\Sanctum\PersonalAccessToken;
        if($allowed && $request->hasSession() && !$tokenAuthenticated){
            $version=$request->session()->get('ceosalud_auth_version');
            // Only untouched legacy accounts may adopt a session predating this feature.
            if($version===null && (int)$state->auth_version===0)$request->session()->put('ceosalud_auth_version',0);
            elseif($version===null || (int)$version!==(int)$state->auth_version)$allowed=false;
        }
        if(!$allowed){
            $guard=\Illuminate\Support\Facades\Auth::guard();
            if(method_exists($guard,'logout'))$guard->logout();
            if($request->hasSession()){$request->session()->invalidate();$request->session()->regenerateToken();}
            throw new \Illuminate\Auth\AuthenticationException('Unauthenticated.',$guards,$this->redirectTo($request));
        }
        $user->setAttribute('is_active',$state->is_active);$user->setAttribute('auth_version',$state->auth_version);
        $user->syncOriginalAttributes(['is_active','auth_version']);
    }

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        if (! $request->expectsJson()) {
            return route('login');
        }
    }
}
