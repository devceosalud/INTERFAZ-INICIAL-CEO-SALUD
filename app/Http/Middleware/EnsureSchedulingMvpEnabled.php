<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureSchedulingMvpEnabled
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): mixed  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        abort_unless(config('scheduling.enabled', false), 404);

        return $next($request);
    }
}
