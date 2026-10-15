<?php
namespace App\Http\Middleware;

use App\Support\Scheduling\MvpOperationalAccess;
use Closure;
use Illuminate\Http\Request;

class EnsureMvpOperationalAccess
{
    public function handle(Request $request, Closure $next, string $mode = 'read')
    {
        abort_unless($mode === 'write' ? MvpOperationalAccess::writesPatients($request->user())
            : MvpOperationalAccess::reads($request->user()), 403);
        return $next($request);
    }
}
