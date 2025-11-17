<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use App\Models\ActivityLog;

class AdminSessionController extends Controller
{
    public function index(Admin $admin)
    {
        $tokens = $admin->tokens()
            ->orderByDesc('last_used_at')
            ->orderByDesc('created_at')
            ->get(['id','name','last_used_at','created_at']);
        return response()->json($tokens);
    }

    public function revoke(Request $request, Admin $admin, $tokenId)
    {
        $token = $admin->tokens()->where('id', $tokenId)->first();
        if (!$token) {
            return response()->json(['message' => 'Token not found'], 404);
        }
        $token->delete();
        // If revoking own current token, also drop it explicitly
        try {
            if ($request->user() && $request->user()->id === $admin->id && $request->user()->currentAccessToken() && $request->user()->currentAccessToken()->id == $tokenId) {
                $request->user()->currentAccessToken()->delete();
            }
        } catch (\Throwable $e) {}
        ActivityLog::create([
            'user_id' => $request->user()->id ?? null,
            'user_name' => $request->user()->name ?? 'Superadmin',
            'action' => 'admin_session_revoked',
            'target_type' => 'admin',
            'target_id' => $admin->id,
            'target_name' => $admin->email,
            'details' => ['token_id' => $tokenId],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
        return response()->json(['success' => true]);
    }

    public function revokeAll(Request $request, Admin $admin)
    {
        $admin->tokens()->delete();
        // If revoking all and target is acting user, drop current token
        try { if ($request->user() && $request->user()->id === $admin->id) { $request->user()->currentAccessToken()?->delete(); } } catch (\Throwable $e) {}
        ActivityLog::create([
            'user_id' => $request->user()->id ?? null,
            'user_name' => $request->user()->name ?? 'Superadmin',
            'action' => 'admin_sessions_revoked_all',
            'target_type' => 'admin',
            'target_id' => $admin->id,
            'target_name' => $admin->email,
            'details' => [],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
        return response()->json(['success' => true]);
    }
}


