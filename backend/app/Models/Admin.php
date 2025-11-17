<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable
{
    use HasApiTokens, Notifiable;



    
    protected $table = 'admins';
    // The attributes that are mass assignable
    protected $fillable = [
        'name',
        'email', 
        'password',
        'role',
        'is_active',
        'two_factor_enabled',
        'two_factor_secret',
        'last_login_at',
        'created_by',
        'created_by_name',
        'updated_by',
        'updated_by_name',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'two_factor_enabled' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }
}
