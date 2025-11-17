<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\AdminPasswordResetMail;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function request(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $email = strtolower($request->email);
        // Always respond success to avoid leaking whether email exists
        $token = Str::random(64);
        $expiresAt = now()->addMinutes(60);

        // Only create token if email exists; otherwise noop
        if (Admin::where('email', $email)->exists()) {
            DB::table('admin_password_resets')->where('email', $email)->delete();
            DB::table('admin_password_resets')->insert([
                'email' => $email,
                'token' => $token,
                'expires_at' => $expiresAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $base = config('app.frontend_url') ?? env('FRONTEND_URL', 'http://localhost:8081');
            $resetUrl = rtrim($base, '/') . '/reset-password/' . $token;
            try {
                Mail::to($email)->send(new AdminPasswordResetMail($resetUrl, $email, $expiresAt->toDateTimeString()));
            } catch (\Throwable $e) {
                // Log but do not expose error to client
                \Log::error('Failed to send password reset mail', ['email' => $email, 'error' => $e->getMessage()]);
            }
        }

        return response()->json(['success' => true]);
    }

    public function show($token)
    {
        $row = DB::table('admin_password_resets')
            ->where('token', $token)
            ->where('expires_at', '>', now())
            ->first();
        if (!$row) {
            return response()->json(['valid' => false], 404);
        }
        return response()->json(['valid' => true, 'email' => $row->email]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'token' => 'required|string',
            'password' => 'required|string|min:12',
        ]);

        $row = DB::table('admin_password_resets')
            ->where('token', $data['token'])
            ->where('expires_at', '>', now())
            ->first();
        if (!$row) {
            return response()->json(['message' => 'Invalid or expired token'], 422);
        }

        $admin = Admin::where('email', $row->email)->first();
        if (!$admin) {
            return response()->json(['success' => true]);
        }

        $admin->password = Hash::make($data['password']);
        $admin->tokens()->delete(); // revoke all sessions
        $admin->save();

        DB::table('admin_password_resets')->where('email', $row->email)->delete();

        return response()->json(['success' => true]);
    }
}


