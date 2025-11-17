<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Verification Code</title>
    <style>
        /* Inline CSS for maximum email client compatibility */
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
            color: #333;
        }
        .email-container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        .header {
            background-color: #10b981; /* Green brand color */
            padding: 20px 30px;
            color: #ffffff;
            text-align: center;
        }
        .header img {
            max-width: 60px;
            margin-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .content {
            padding: 30px;
            line-height: 1.6;
            color: #555;
        }
        .content h2 {
            color: #333;
            font-size: 20px;
            margin-top: 0;
            margin-bottom: 15px;
        }
        .otp-container {
            text-align: center;
            margin: 25px 0;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
            border: 2px dashed #10b981;
        }
        .otp-code {
            font-size: 32px;
            font-weight: 700;
            color: #10b981;
            letter-spacing: 8px;
            font-family: 'Courier New', monospace;
            margin: 10px 0;
        }
        .otp-label {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }
        .security-note {
            background-color: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin: 20px 0;
            color: #1565c0;
        }
        .footer {
            background-color: #f9f9f9;
            padding: 20px 30px;
            text-align: center;
            font-size: 12px;
            color: #888;
            border-top: 1px solid #eee;
        }
        .footer a {
            color: #10b981;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <img src="{{ url('storage/email_icon/email_icon.png') }}" alt="ISU-E Logo" style="max-width: 60px; height: auto; display: block; margin: 0 auto 10px;">
            <h1>ISU-E Admin Panel</h1>
        </div>
        <div class="content">
            <h2>Your Login Verification Code</h2>
            <p>Hello {{ $adminName }},</p>
            <p>You're signing in to the ISU-E Campus Map Admin Panel. Use the verification code below to complete your login:</p>
            
            <div class="otp-container">
                <div class="otp-label">Your verification code is:</div>
                <div class="otp-code">{{ $otpCode }}</div>
            </div>
            
            
            <div class="security-note">
                <strong>🔒 Security Notice:</strong> If you didn't request this code, please ignore this email and consider changing your password. Never share this code with anyone.
            </div>
            
            <p>This code is valid for 10 minutes and can only be used once.</p>
            <p>Thank you,<br>The ISU-E Admin Team</p>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} ISU-E Campus Map Admin Panel. All rights reserved.</p>
            <p>Isabela State University - Echague Campus</p>
        </div>
    </div>
</body>
</html>
