<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrustedDevice extends Model
{
    use HasFactory;

    protected $table = 'admin_trusted_devices';

    protected $fillable = [
        'admin_id',
        'device_token',
        'device_name',
        'ip_address',
        'user_agent',
        'trusted_at',
        'expires_at',
        'last_used_at',
    ];

    protected $casts = [
        'trusted_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }

    public function isExpired()
    {
        return $this->expires_at < now();
    }

    public function isActive()
    {
        return !$this->isExpired();
    }

    /**
     * Create or update a trusted device record
     * Handles duplicate device tokens gracefully
     */
    public static function createOrUpdateTrustedDevice($adminId, $deviceToken, $deviceName, $ipAddress, $userAgent, $trustDuration = 30)
    {
        $trustedAt = now();
        $expiresAt = $trustedAt->copy()->addDays($trustDuration);
        
        return static::updateOrCreate(
            [
                'admin_id' => $adminId,
                'device_token' => $deviceToken
            ],
            [
                'device_name' => $deviceName,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'trusted_at' => $trustedAt,
                'expires_at' => $expiresAt,
                'last_used_at' => $trustedAt,
            ]
        );
    }
}