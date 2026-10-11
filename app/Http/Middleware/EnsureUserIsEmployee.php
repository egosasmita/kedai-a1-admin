<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsEmployee
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user !== null && ! $user->is_admin, 403);

        $profile = DB::table('employee_profiles')->where('user_id', $user->id)->first();
        abort_if($profile !== null && $profile->employment_status !== 'active', 403);

        return $next($request);
    }
}
