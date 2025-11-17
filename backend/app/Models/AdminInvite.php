<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminInvite extends Model
{
    protected $table = 'admin_invites';
    protected $fillable = ['email','role','token','expires_at','redeemed_at','created_by'];
    protected $casts = [
        'expires_at' => 'datetime',
        'redeemed_at' => 'datetime',
    ];
}


