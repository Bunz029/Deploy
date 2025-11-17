<?php

namespace App\Services;

use App\Models\UserRateLimit;
use App\Models\Admin;
use Carbon\Carbon;

class RateLimitingService
{
    const MAX_PASSWORD_ATTEMPTS = 5;
    const MAX_OTP_ATTEMPTS = 5;
    const LOCKOUT_DURATION_MINUTES = 10;

    /**
     * Check if user is rate limited for password attempts
     */
    public function isPasswordRateLimited(int $userId, string $userType = 'admin'): array
    {
        $rateLimit = UserRateLimit::getOrCreateForUser($userId, $userType);
        
        // Check if lockout has expired and reset if needed
        if ($rateLimit->password_locked_until && $rateLimit->password_locked_until->isPast()) {
            $rateLimit->password_attempts = 0;
            $rateLimit->password_locked_until = null;
            $rateLimit->save();
        }
        
        if ($rateLimit->isPasswordRateLimited()) {
            $remaining = $rateLimit->getPasswordLockoutRemaining();
            $minutes = floor($remaining / 60);
            $seconds = $remaining % 60;
            
            return [
                'is_limited' => true,
                'remaining_seconds' => $remaining,
                'message' => "Account temporarily locked due to too many failed password attempts. Try again in {$minutes}:{$seconds}",
                'attempts' => $rateLimit->password_attempts,
                'max_attempts' => self::MAX_PASSWORD_ATTEMPTS
            ];
        }

        return [
            'is_limited' => false,
            'remaining_seconds' => 0,
            'message' => null,
            'attempts' => $rateLimit->password_attempts,
            'max_attempts' => self::MAX_PASSWORD_ATTEMPTS
        ];
    }

    /**
     * Check if user is rate limited for OTP attempts
     */
    public function isOtpRateLimited(int $userId, string $userType = 'admin'): array
    {
        $rateLimit = UserRateLimit::getOrCreateForUser($userId, $userType);
        
        // Check if lockout has expired and reset if needed
        if ($rateLimit->otp_locked_until && $rateLimit->otp_locked_until->isPast()) {
            $rateLimit->otp_attempts = 0;
            $rateLimit->otp_locked_until = null;
            $rateLimit->save();
        }
        
        if ($rateLimit->isOtpRateLimited()) {
            $remaining = $rateLimit->getOtpLockoutRemaining();
            $minutes = floor($remaining / 60);
            $seconds = $remaining % 60;
            
            return [
                'is_limited' => true,
                'remaining_seconds' => $remaining,
                'message' => "Account temporarily locked due to too many OTP resend attempts. Try again in {$minutes}:{$seconds}",
                'attempts' => $rateLimit->otp_attempts,
                'max_attempts' => self::MAX_OTP_ATTEMPTS
            ];
        }

        return [
            'is_limited' => false,
            'remaining_seconds' => 0,
            'message' => null,
            'attempts' => $rateLimit->otp_attempts,
            'max_attempts' => self::MAX_OTP_ATTEMPTS
        ];
    }

    /**
     * Record a failed password attempt
     */
    public function recordPasswordAttempt(int $userId, string $userType = 'admin'): array
    {
        $rateLimit = UserRateLimit::getOrCreateForUser($userId, $userType);
        $rateLimit->recordPasswordAttempt();

        return $this->isPasswordRateLimited($userId, $userType);
    }

    /**
     * Record a failed OTP attempt
     */
    public function recordOtpAttempt(int $userId, string $userType = 'admin'): array
    {
        $rateLimit = UserRateLimit::getOrCreateForUser($userId, $userType);
        $rateLimit->recordOtpAttempt();

        return $this->isOtpRateLimited($userId, $userType);
    }

    /**
     * Reset password attempts (on successful login)
     */
    public function resetPasswordAttempts(int $userId, string $userType = 'admin'): void
    {
        $rateLimit = UserRateLimit::getOrCreateForUser($userId, $userType);
        $rateLimit->resetPasswordAttempts();
    }

    /**
     * Reset OTP attempts (on successful OTP verification)
     */
    public function resetOtpAttempts(int $userId, string $userType = 'admin'): void
    {
        $rateLimit = UserRateLimit::getOrCreateForUser($userId, $userType);
        $rateLimit->resetOtpAttempts();
    }

    /**
     * Get user ID by email for rate limiting
     */
    public function getUserIdByEmail(string $email): ?int
    {
        $admin = Admin::where('email', $email)->first();
        return $admin ? $admin->id : null;
    }

    /**
     * Check rate limiting before authentication
     */
    public function checkAuthenticationRateLimit(string $email): array
    {
        $userId = $this->getUserIdByEmail($email);
        
        if (!$userId) {
            return [
                'is_limited' => false,
                'type' => null,
                'message' => null
            ];
        }

        $passwordLimit = $this->isPasswordRateLimited($userId);
        if ($passwordLimit['is_limited']) {
            return [
                'is_limited' => true,
                'type' => 'password',
                'message' => $passwordLimit['message'],
                'remaining_seconds' => $passwordLimit['remaining_seconds']
            ];
        }

        $otpLimit = $this->isOtpRateLimited($userId);
        if ($otpLimit['is_limited']) {
            return [
                'is_limited' => true,
                'type' => 'otp',
                'message' => $otpLimit['message'],
                'remaining_seconds' => $otpLimit['remaining_seconds']
            ];
        }

        return [
            'is_limited' => false,
            'type' => null,
            'message' => null
        ];
    }
}
