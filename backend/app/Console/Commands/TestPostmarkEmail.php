<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class TestPostmarkEmail extends Command
{
    protected $signature = 'test:email {email}';
    protected $description = 'Test Postmark email sending with a simple email';

    public function handle()
    {
        $email = $this->argument('email');
        
        $this->info('🧪 Testing Postmark Email Setup...');
        $this->info('📧 Sending to: ' . $email);
        
        // Log current mail configuration
        $this->info('📋 Current Mail Config:');
        $this->line('  MAIL_MAILER: ' . config('mail.default'));
        $this->line('  MAIL_HOST: ' . config('mail.mailers.smtp.host'));
        $this->line('  MAIL_PORT: ' . config('mail.mailers.smtp.port'));
        $this->line('  MAIL_USERNAME: ' . config('mail.mailers.smtp.username'));
        $this->line('  MAIL_ENCRYPTION: ' . config('mail.mailers.smtp.encryption'));
        $this->line('  MAIL_FROM_ADDRESS: ' . config('mail.from.address'));
        $this->line('  MAIL_FROM_NAME: ' . config('mail.from.name'));
        
        try {
            $this->info('🚀 Attempting to send test email...');
            
            Mail::raw('This is a test email from ISU-E Admin Panel to verify Postmark setup is working correctly.', function ($message) use ($email) {
                $message->to($email)
                        ->subject('🧪 Postmark Test Email - ISU-E Admin Panel');
            });
            
            $this->info('✅ EMAIL SENT SUCCESSFULLY!');
            $this->info('📬 Check your inbox (and spam folder) for the test email.');
            
        } catch (\Exception $e) {
            $this->error('❌ EMAIL SENDING FAILED!');
            $this->error('Error: ' . $e->getMessage());
            
            // Log detailed error
            Log::error('Test email failed', [
                'error' => $e->getMessage(),
                'email' => $email,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}