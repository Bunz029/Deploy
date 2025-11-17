<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AdminInvite;
use App\Models\ActivityLog;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\AdminInviteMail;

class AdminInviteController extends Controller
{
    public function send(Request $request)
    {
        Log::info('🚀 AdminInviteController::send called', [
            'user_id' => $request->user()?->id,
            'user_email' => $request->user()?->email,
            'request_data' => $request->all()
        ]);
        
        try {
            $data = $request->validate([
                'email' => 'required|email',
                'role' => 'required|in:admin,super_admin',
                'name' => 'nullable|string|max:255',
                'created_by' => 'nullable|integer',
                'created_by_name' => 'nullable|string|max:255',
            ]);
            Log::info('✅ Validation passed', $data);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('❌ Validation failed', [
                'errors' => $e->errors(),
                'request_data' => $request->all()
            ]);
            throw $e;
        }
        // create or refresh invite
        $token = Str::random(40);
        $invite = AdminInvite::create([
            'email' => strtolower($data['email']),
            'role' => $data['role'],
            'token' => $token,
            'expires_at' => now()->addDay(),
            'created_by' => $data['created_by'] ?? $request->user()->id ?? null,
            'created_by_name' => $data['created_by_name'] ?? $request->user()->name ?? 'System',
        ]);

        ActivityLog::create([
            'user_id' => $request->user()->id ?? null,
            'user_name' => $request->user()->name ?? 'Superadmin',
            'action' => 'admin_invite_sent',
            'target_type' => 'admin_invite',
            'target_id' => $invite->id,
            'target_name' => $invite->email,
            'details' => ['role' => $invite->role],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Send email with detailed logging
        $emailSent = false;
        $emailError = null;
        
        Log::info('=== STARTING EMAIL SEND PROCESS ===', [
            'email' => $invite->email,
            'role' => $invite->role,
            'token' => $token
        ]);
        
        try {
            // Log mail configuration
            Log::info('Mail Configuration Check', [
                'MAIL_MAILER' => config('mail.default'),
                'MAIL_HOST' => config('mail.mailers.smtp.host'),
                'MAIL_PORT' => config('mail.mailers.smtp.port'),
                'MAIL_USERNAME' => config('mail.mailers.smtp.username'),
                'MAIL_ENCRYPTION' => config('mail.mailers.smtp.encryption'),
                'MAIL_FROM_ADDRESS' => config('mail.from.address'),
                'MAIL_FROM_NAME' => config('mail.from.name'),
            ]);
            
            $frontendBase = config('app.frontend_url') ?? env('FRONTEND_URL', 'http://localhost:8081');
            $acceptUrl = rtrim($frontendBase, '/') . '/accept-invite/' . $token;
            
            Log::info('Generated Accept URL', ['accept_url' => $acceptUrl]);
            
            Log::info('Creating AdminInviteMail instance...');
            $mailInstance = new AdminInviteMail($invite, $acceptUrl);
            
            Log::info('Attempting to send email via Mail::to()...');
            Mail::to($invite->email)->send($mailInstance);
            
            Log::info('✅ EMAIL SENT SUCCESSFULLY!', [
                'email' => $invite->email,
                'accept_url' => $acceptUrl
            ]);
            
            $emailSent = true;
            
        } catch (\Throwable $e) {
            $emailError = $e->getMessage();
            Log::error('❌ FAILED TO SEND EMAIL', [
                'error' => $emailError,
                'email' => $invite->email,
                'exception_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        
        Log::info('=== EMAIL SEND PROCESS COMPLETE ===', [
            'email_sent' => $emailSent,
            'error' => $emailError
        ]);

        return response()->json([
            'success' => true,
            'email_sent' => $emailSent,
            'email_error' => $emailError, // Include error details for debugging
            'invite' => [
                'email' => $invite->email,
                'role' => $invite->role,
                'token' => $invite->token,
                'expires_at' => $invite->expires_at,
            ]
        ]);
    }

    public function validateToken($token)
    {
        $invite = AdminInvite::where('token', $token)
            ->whereNull('redeemed_at')
            ->where('expires_at', '>', now())
            ->first();
        if (!$invite) return response()->json(['valid' => false], 404);
        return response()->json(['valid' => true, 'email' => $invite->email, 'role' => $invite->role]);
    }

    public function accept(Request $request)
    {
        $data = $request->validate([
            'token' => 'required|string',
            'name' => 'required|string|max:255',
            'password' => 'required|string|min:12',
        ]);
        $invite = AdminInvite::where('token', $data['token'])
            ->whereNull('redeemed_at')
            ->where('expires_at', '>', now())
            ->firstOrFail();

        $admin = Admin::firstOrCreate(
            ['email' => $invite->email],
            [
                'name' => $data['name'], 
                'password' => Hash::make($data['password']), 
                'role' => $invite->role, 
                'is_active' => true,
                'created_by' => $invite->created_by,
                'created_by_name' => $invite->created_by_name ?? 'System'
            ]
        );

        $invite->redeemed_at = now();
        $invite->save();

        ActivityLog::create([
            'user_id' => null,
            'user_name' => 'Invitee',
            'action' => 'admin_invite_accepted',
            'target_type' => 'admin',
            'target_id' => $admin->id,
            'target_name' => $admin->email,
            'details' => ['role' => $admin->role],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json(['success' => true]);
    }
}


