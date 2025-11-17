<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user || !method_exists($user, 'isSuperAdmin') || !$user->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: super_admin role required'
            ], 403);
        }
        return $next($request);
    }
}

?>


