<?php

namespace App\Http\Middleware;

use App\Models\RolePermission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        abort_unless($user && ($user->role === 'admin' || RolePermission::where('role', $user->role)->where('permission', $permission)->where('is_allowed', true)->exists()), 403);

        return $next($request);
    }
}
