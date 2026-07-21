<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Your Verification Code</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #334155; margin: 0; padding: 20px; }
        .container { max-width: 500px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .header { background-color: #2563eb; padding: 20px; text-align: center; }
        .header h1 { color: #ffffff; margin: 0; font-size: 20px; font-weight: 800; letter-spacing: 0.5px; }
        .content { padding: 30px; text-align: center; }
        .content p { font-size: 15px; line-height: 1.6; margin-bottom: 20px; color: #475569; }
        .otp-box { background-color: #f1f5f9; border: 2px dashed #cbd5e1; border-radius: 8px; padding: 15px; margin: 25px 0; }
        .otp-code { font-size: 32px; font-weight: 900; letter-spacing: 8px; color: #1e40af; margin: 0; }
        .warning { font-size: 13px; color: #ef4444; font-weight: bold; margin-top: 20px; }
        .footer { background-color: #f8fafc; padding: 15px; text-align: center; border-top: 1px solid #e2e8f0; font-size: 12px; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Colegio de Naujan LMS</h1>
        </div>
        <div class="content">
            <p>Hello,</p>
            <p>You requested a verification code for your account. Please use the 6-digit code below to complete your request.</p>
            
            <div class="otp-box">
                <p class="otp-code">{{ $otp }}</p>
            </div>
            
            <p class="warning">This code will expire in exactly 90 seconds.</p>
            <p style="font-size: 13px; color: #64748b;">If you did not request this code, you can safely ignore this email.</p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Colegio de Naujan. All rights reserved.
        </div>
    </div>
</body>
</html>