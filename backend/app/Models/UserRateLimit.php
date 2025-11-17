<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class UserRateLimit extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'user_type',
        'password_attempts',
        'password_locked_until',
        'otp_attempts',
        'otp_locked_until',
        'last_password_attempt',
        'last_otp_attempt',
    ];

    protected $casts = [
        'password_locked_until' => 'datetime',
        'otp_locked_until' => 'datetime',
        'last_password_attempt' => 'datetime',
        'last_otp_attempt' => 'datetime',
    ];

    /**
     * Check if password is rate limited
     */
    public function isPasswordRateLimited(): bool
    {
        if (!$this->password_locked_until) {
            return false;
        }

        return Carbon::now()->isBefore($this->password_locked_until);
    }

    /**
     * Check if OTP is rate limited
     */
    public function isOtpRateLimited(): bool
    {
        if (!$this->otp_locked_until) {
            return false;
        }

        return Carbon::now()->isBefore($this->otp_locked_until);
    }

    /**
     * Get remaining lockout time in seconds
     */
    public function getPasswordLockoutRemaining(): int
    {
        if (!$this->isPasswordRateLimited()) {
            return 0;
        }

        return Carbon::now()->diffInSeconds($this->password_locked_until, false);
    }

    /**
     * Get remaining OTP lockout time in seconds
     */
    public function getOtpLockoutRemaining(): int
    {
        if (!$this->isOtpRateLimited()) {
            return 0;
        }

        return Carbon::now()->diffInSeconds($this->otp_locked_until, false);
    }

    /**
     * Record a failed password attempt
     */
    public function recordPasswordAttempt(): void
    {
        $this->password_attempts++;
        $this->last_password_attempt = Carbon::now();

        // Lock for 10 minutes after 5 attempts
        if ($this->password_attempts >= 5) {
            $this->password_locked_until = Carbon::now()->addMinutes(10);
        }

        $this->save();
    }

    /**
     * Record a failed OTP attempt
     */
    public function recordOtpAttempt(): void
    {
        $this->otp_attempts++;
        $this->last_otp_attempt = Carbon::now();

        // Lock for 10 minutes after 5 attempts
        if ($this->otp_attempts >= 5) {
            $this->otp_locked_until = Carbon::now()->addMinutes(10);
        }

        $this->save();
    }

    /**
     * Reset password attempts (on successful login)
     */
    public function resetPasswordAttempts(): void
    {
        $this->password_attempts = 0;
        $this->password_locked_until = null;
        $this->save();
    }

    /**
     * Reset OTP attempts (on successful OTP verification)
     */
    public function resetOtpAttempts(): void
    {
        $this->otp_attempts = 0;
        $this->otp_locked_until = null;
        $this->save();
    }

    /**
     * Get or create rate limit record for user
     */
    public static function getOrCreateForUser(int $userId, string $userType = 'admin'): self
    {
        return static::firstOrCreate(
            ['user_id' => $userId, 'user_type' => $userType],
            [
                'password_attempts' => 0,
                'otp_attempts' => 0,
            ]
        );
    }

    /**
     * Clean up expired locks (can be called by a scheduled job)
     */
    public static function cleanupExpiredLocks(): void
    {
        static::where('password_locked_until', '<', Carbon::now())
            ->update(['password_locked_until' => null, 'password_attempts' => 0]);

        static::where('otp_locked_until', '<', Carbon::now())
            ->update(['otp_locked_until' => null, 'otp_attempts' => 0]);
    }
}
