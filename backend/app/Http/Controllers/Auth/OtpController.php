<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\AdminOtpMail;
use App\Models\Admin;
use App\Models\TrustedDevice;
use App\Services\RateLimitingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OtpController extends Controller
{
    protected $rateLimitingService;

    public function __construct(RateLimitingService $rateLimitingService)
    {
        $this->rateLimitingService = $rateLimitingService;
    }

    private function generateDeviceToken()
    {
        return Str::random(64);
    }

    private function getDeviceFingerprint(Request $request)
    {
        $userAgent = $request->userAgent();
        $ip = $request->ip();
        
        // Create a simple device fingerprint
        $fingerprint = hash('sha256', $userAgent . $ip);
        return $fingerprint;
    }

    private function getDeviceName(Request $request)
    {
        $userAgent = $request->userAgent();
        
        // Simple device name detection
        if (strpos($userAgent, 'Chrome') !== false) {
            return 'Chrome Browser';
        } elseif (strpos($userAgent, 'Firefox') !== false) {
            return 'Firefox Browser';
        } elseif (strpos($userAgent, 'Safari') !== false) {
            return 'Safari Browser';
        } elseif (strpos($userAgent, 'Edge') !== false) {
            return 'Edge Browser';
        } else {
            return 'Unknown Browser';
        }
    }

    private function verifyRecaptcha($recaptchaResponse)
    {
        // Allow 'resend_otp' for OTP resend operations
        if ($recaptchaResponse === 'resend_otp') {
            return true;
        }
        
        $secretKey = env('RECAPTCHA_SECRET_KEY', '6LdCwfMrAAAAAAwZ49Uig-kvC9u5-ecY8ATF1ocZ');
        $url = 'https://www.google.com/recaptcha/api/siteverify';
            
        $data = [
            'secret' => $secretKey,
            'response' => $recaptchaResponse,
            'remoteip' => request()->ip()
        ];
        
        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data)
            ]
        ];
        
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        $response = json_decode($result, true);
        
        return isset($response['success']) && $response['success'] === true;
    }

    private function isDeviceTrusted(Admin $admin, Request $request)
    {
        $deviceToken = $request->header('X-Device-Token');
        if (!$deviceToken) {
            return false;
        }

        $trustedDevice = TrustedDevice::where('admin_id', $admin->id)
            ->where('device_token', $deviceToken)
            ->where('expires_at', '>', now())
            ->first();

        if ($trustedDevice) {
            // Update last used timestamp
            $trustedDevice->update(['last_used_at' => now()]);
            return true;
        }

        return false;
    }

    public function send(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:12',
            'recaptcha_response' => 'nullable|string',
        ]);

        // Verify reCAPTCHA only if provided and not a resend operation
        if ($request->recaptcha_response && $request->recaptcha_response !== 'resend_otp') {
            if (!$this->verifyRecaptcha($request->recaptcha_response)) {
                return response()->json([
                    'success' => false,
                    'message' => 'reCAPTCHA verification failed'
                ], 400);
            }
        }

        $email = strtolower($request->email);
        $admin = Admin::where('email', $email)->first();

        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        // Check rate limiting before authentication
        $rateLimitCheck = $this->rateLimitingService->checkAuthenticationRateLimit($email);
        if ($rateLimitCheck['is_limited']) {
            return response()->json([
                'success' => false,
                'message' => $rateLimitCheck['message'],
                'rate_limited' => true,
                'rate_limit_type' => $rateLimitCheck['type'],
                'remaining_seconds' => $rateLimitCheck['remaining_seconds']
            ], 429);
        }

        // Check if account is active
        if ($admin->is_active === false) {
            return response()->json([
                'success' => false,
                'message' => 'Account is deactivated'
            ], 403);
        }

        // Verify password
        if (!Hash::check($request->password, $admin->password)) {
            // Record failed password attempt
            $this->rateLimitingService->recordPasswordAttempt($admin->id);
            
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        // Check if device is trusted
        if ($this->isDeviceTrusted($admin, $request)) {
            // Device is trusted, skip OTP and login directly
            $admin->tokens()->delete();
            $token = $admin->createToken('admin-token')->plainTextToken;
            $admin->update(['last_login_at' => now()]);

            // Reset rate limiting on successful login
            $this->rateLimitingService->resetPasswordAttempts($admin->id);

            Log::info('Admin logged in with trusted device', [
                'admin_id' => $admin->id,
                'email' => $admin->email
            ]);

            return response()->json([
                'success' => true,
                'token' => $token,
                'user' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'role' => $admin->role,
                    'is_active' => $admin->is_active,
                ],
                'trusted_device' => true
            ]);
        }

        // Check OTP rate limiting before sending OTP
        $otpRateLimit = $this->rateLimitingService->isOtpRateLimited($admin->id);
        if ($otpRateLimit['is_limited']) {
            return response()->json([
                'success' => false,
                'message' => $otpRateLimit['message'],
                'rate_limited' => true,
                'rate_limit_type' => 'otp',
                'remaining_seconds' => $otpRateLimit['remaining_seconds']
            ], 429);
        }

        // Generate 6-digit OTP
        $otpCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = now()->addMinutes(10);

        // Delete any existing OTPs for this email
        DB::table('admin_otps')->where('email', $email)->delete();

        // Store the OTP
        DB::table('admin_otps')->insert([
            'email' => $email,
            'otp_code' => $otpCode,
            'expires_at' => $expiresAt,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Send OTP email
        try {
            Mail::to($email)->send(new AdminOtpMail(
                $otpCode,
                $admin->name,
                $expiresAt->format('F d, Y H:i A')
            ));
            
            // Record OTP attempt (successful send)
            $this->rateLimitingService->recordOtpAttempt($admin->id);
            
            Log::info('OTP sent successfully', ['email' => $email]);
        } catch (\Throwable $e) {
            Log::error('Failed to send OTP email', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send verification code. Please try again.'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Verification code sent to your email',
            'expires_at' => $expiresAt->toISOString()
        ]);
    }

    public function verify(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp_code' => 'required|string|size:6',
            'trust_device' => 'boolean'
        ]);

        $email = strtolower($request->email);
        $otpCode = $request->otp_code;

        // Find valid OTP
        $otpRecord = DB::table('admin_otps')
            ->where('email', $email)
            ->where('otp_code', $otpCode)
            ->where('expires_at', '>', now())
            ->where('used', false)
            ->first();

        if (!$otpRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired verification code'
            ], 400);
        }

        // Mark OTP as used
        DB::table('admin_otps')
            ->where('id', $otpRecord->id)
            ->update(['used' => true]);

        // Get admin
        $admin = Admin::where('email', $email)->first();
        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Admin not found'
            ], 404);
        }

        // Revoke existing tokens
        $admin->tokens()->delete();

        // Create new token
        $token = $admin->createToken('admin-token')->plainTextToken;

        // Update last login
        $admin->update(['last_login_at' => now()]);

        // Reset rate limiting on successful OTP verification
        $this->rateLimitingService->resetPasswordAttempts($admin->id);
        $this->rateLimitingService->resetOtpAttempts($admin->id);

        // If trust device is requested, store the device token
        if ($request->trust_device) {
            $deviceToken = $request->header('X-Device-Token');
            if (!$deviceToken) {
                Log::warning('Trust device requested but no device token provided');
            } else {
                $deviceName = $this->getDeviceName($request);
                
                try {
                    // Use UPSERT to handle duplicate device tokens gracefully
                    TrustedDevice::createOrUpdateTrustedDevice(
                        $admin->id,
                        $deviceToken,
                        $deviceName,
                        $request->ip(),
                        $request->userAgent(),
                        30 // 30 days trust duration
                    );

                    Log::info('Device trusted for OTP bypass', [
                        'admin_id' => $admin->id,
                        'email' => $email,
                        'device_token' => $deviceToken,
                        'ip' => $request->ip()
                    ]);
                } catch (\Exception $e) {
                    // If device trust fails because device is already trusted, just log them in
                    Log::info('Device already trusted, proceeding with login', [
                        'admin_id' => $admin->id,
                        'email' => $email,
                        'device_token' => $deviceToken,
                        'error' => $e->getMessage()
                    ]);
                    
                    // Continue with normal login flow - device is already trusted
                }
            }
        }

        Log::info('Admin logged in with OTP', [
            'admin_id' => $admin->id,
            'email' => $email,
            'trust_device' => $request->trust_device
        ]);

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => $admin->role,
                'is_active' => $admin->is_active,
            ],
        ]);
    }
}