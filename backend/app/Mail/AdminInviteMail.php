<?php

namespace App\Mail;

use App\Models\AdminInvite;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdminInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public AdminInvite $invite;
    public string $acceptUrl;

    public function __construct(AdminInvite $invite, string $acceptUrl)
    {
        $this->invite = $invite;
        $this->acceptUrl = $acceptUrl;
    }

    public function build()
    {
        return $this->subject('Administrative Access Invitation – ISU-E Campus Interactive Map')
            ->view('emails.admin_invite')
            ->with([
                'email' => $this->invite->email,
                'role' => $this->invite->role,
                'acceptUrl' => $this->acceptUrl,
                'expiresAt' => $this->invite->expires_at,
                'authority' => $this->invite->role === 'super_admin'
                    ? 'Full System Control & User Management'
                    : 'Content Management & System Operations',
            ]);
    }
}


