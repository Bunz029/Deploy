<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdminOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $otpCode;
    public string $adminName;
    public string $expiresAt;

    public function __construct(string $otpCode, string $adminName, string $expiresAt)
    {
        $this->otpCode = $otpCode;
        $this->adminName = $adminName;
        $this->expiresAt = $expiresAt;
    }

    public function build()
    {
        return $this->subject('Your ISU-E Admin Panel Login Code')
                    ->view('emails.admin_otp')
                    ->with([
                        'otpCode' => $this->otpCode,
                        'adminName' => $this->adminName,
                        'expiresAt' => $this->expiresAt,
                    ]);
    }
}