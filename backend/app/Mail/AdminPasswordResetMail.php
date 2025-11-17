<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdminPasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $resetUrl;
    public string $email;
    public string $expiresAt;

    public function __construct(string $resetUrl, string $email, string $expiresAt)
    {
        $this->resetUrl = $resetUrl;
        $this->email = $email;
        $this->expiresAt = $expiresAt;
    }

    public function build()
    {
        return $this->subject("Reset your ISU‑E Admin password")
            ->view('emails.admin_password_reset')
            ->with([
                'resetUrl' => $this->resetUrl,
                'email' => $this->email,
                'expiresAt' => $this->expiresAt,
            ]);
    }
}


