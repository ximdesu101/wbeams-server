<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; margin: 0; padding: 0; background: #f4f4f4;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; background: #ffffff;">
        <h2 style="margin-top: 0;">Hello {{ $operator->first_name }},</h2>

        <p>We received a request to reset the password for your operator account. Click the button below to choose a new password:</p>

        <p>
            <a href="{{ $resetUrl }}"
                style="display: inline-block; padding: 12px 24px; background: #2563eb; color: #ffffff; text-decoration: none; border-radius: 4px;">
                Reset Password
            </a>
        </p>

        <p>If the button doesn't work, copy and paste this URL into your browser:</p>
        <p style="word-break: break-all; background: #f5f5f5; padding: 10px; border-radius: 4px;">
            {{ $resetUrl }}
        </p>

        <p>If you did not request a password reset, you can safely ignore this email. Your password will remain unchanged.</p>

        <p>Regards,<br>WBEAMS System</p>

        <div style="margin-top: 30px; font-size: 12px; color: #666;">
            <p>This is an automated message, please do not reply to this email.</p>
            <p>This link expires: {{ $expiresAtLabel }}</p>
        </div>
    </div>
</body>
</html>