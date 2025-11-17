<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Postmark\PostmarkClient;

class TestPostmarkAPI extends Command
{
    protected $signature = 'test:postmark-api {email}';
    protected $description = 'Test Postmark API directly (bypassing SMTP)';

    public function handle()
    {
        $email = $this->argument('email');
        $serverToken = env('MAIL_PASSWORD'); // Your Postmark server token
        
        $this->info('🧪 Testing Postmark API Direct...');
        $this->info('📧 Sending to: ' . $email);
        $this->info('🔑 Using token: ' . substr($serverToken, 0, 8) . '...');
        
        try {
            $client = new PostmarkClient($serverToken);
            
            $this->info('🚀 Sending via Postmark API...');
            
            $response = $client->sendEmail(
                "vinceerrol214@gmail.com", // From (must be verified)
                $email, // To
                "🧪 Postmark API Test - ISU-E Admin Panel", // Subject
                "This is a test email sent directly via Postmark API to verify the setup is working correctly.\n\nIf you receive this, Postmark is configured properly!" // Text body
            );
            
            $this->info('✅ EMAIL SENT SUCCESSFULLY via API!');
            $this->info('📬 Message ID: ' . $response['MessageID']);
            $this->info('📬 Check your inbox for the test email.');
            
        } catch (\Exception $e) {
            $this->error('❌ API EMAIL SENDING FAILED!');
            $this->error('Error: ' . $e->getMessage());
        }
    }
}
