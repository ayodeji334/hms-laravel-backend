<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckUserRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if (!$user || !in_array($user->role, $roles)) {
            return response()->json([
                'message' => 'You do not have the required permission to perform the action.',
                'allowed_roles' => $roles,
                'user_role' => $user?->role,
                'success' => false,
                'status' => 'error',
            ], 403);
        }

        return $next($request);
    }
}
