<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\ActivityLog;

class AdminSecurityController extends Controller
{
    public function resetPassword(Request $request, Admin $admin)
    {
        $data = $request->validate([
            'new_password' => 'required|string|min:12',
        ]);
        $admin->password = Hash::make($data['new_password']);
        $admin->save();
        // Revoke tokens (logout everywhere)
        try { $admin->tokens()->delete(); } catch (\Throwable $e) {}
        // If the target is the acting user, also revoke current access token
        try {
            if ($request->user() && $request->user()->id === $admin->id) {
                $request->user()->currentAccessToken()?->delete();
            }
        } catch (\Throwable $e) {}
        // Audit
        ActivityLog::create([
            'user_id' => $request->user()->id ?? null,
            'user_name' => $request->user()->name ?? 'Superadmin',
            'action' => 'admin_password_reset',
            'target_type' => 'admin',
            'target_id' => $admin->id,
            'target_name' => $admin->email,
            'details' => [],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
        return response()->json(['success' => true]);
    }

    public function reset2FA(Request $request, Admin $admin)
    {
        // Clear 2FA secret to force new setup (but keep 2FA enabled)
        $admin->two_factor_secret = null;
        $admin->save();
        
        // Revoke all sessions (force logout everywhere)
        try { 
            $admin->tokens()->delete(); 
        } catch (\Throwable $e) {
            \Log::warning('Failed to revoke tokens during 2FA reset', ['admin_id' => $admin->id, 'error' => $e->getMessage()]);
        }
        
        // Clear all trusted devices (force re-verification)
        try {
            \DB::table('admin_trusted_devices')->where('admin_id', $admin->id)->delete();
        } catch (\Throwable $e) {
            \Log::warning('Failed to clear trusted devices during 2FA reset', ['admin_id' => $admin->id, 'error' => $e->getMessage()]);
        }
        
        // If the target is the acting user, also revoke current access token
        try {
            if ($request->user() && $request->user()->id === $admin->id) {
                $request->user()->currentAccessToken()?->delete();
            }
        } catch (\Throwable $e) {
            \Log::warning('Failed to revoke current token during 2FA reset', ['admin_id' => $admin->id, 'error' => $e->getMessage()]);
        }
        
        // Audit
        ActivityLog::create([
            'user_id' => $request->user()->id ?? null,
            'user_name' => $request->user()->name ?? 'Superadmin',
            'action' => 'admin_2fa_reset',
            'target_type' => 'admin',
            'target_id' => $admin->id,
            'target_name' => $admin->email,
            'details' => [
                'sessions_revoked' => true,
                'trusted_devices_cleared' => true,
                'requires_new_2fa_setup' => true
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
        
        return response()->json([
            'success' => true,
            'message' => '2FA has been reset. The admin will need to set up 2FA again and re-verify all devices.'
        ]);
    }
}


