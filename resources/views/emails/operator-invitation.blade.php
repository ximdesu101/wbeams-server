<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Operator Account Activation</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; margin: 0; padding: 0; background: #f4f4f4;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; background: #ffffff;">
        <h2 style="margin-top: 0;">Hello {{ $operator->first_name }},</h2>

        <p>An operator account has been created for you. Please click the button below to activate your account and set your password:</p>

        <p>
            <a href="{{ $activationUrl }}"
                style="display: inline-block; padding: 12px 24px; background: #4CAF50; color: #ffffff; text-decoration: none; border-radius: 4px;">
                Activate Account
            </a>
        </p>

        <p><strong>Account Details:</strong></p>
        <ul>
            <li>Operator ID: {{ $operator->operator_id }}</li>
            <li>Name: {{ $operator->full_name }}</li>
            <li>Email: {{ $operator->email }}</li>
            <li>Contact: {{ $operator->contact_number }}</li>
        </ul>

        <p>If the button doesn't work, copy and paste this URL into your browser:</p>
        <p style="word-break: break-all; background: #f5f5f5; padding: 10px; border-radius: 4px;">
            {{ $activationUrl }}
        </p>

        <p>This account was created for you by a system administrator. If you believe this was sent in error, please contact your administrator directly.</p>

        <p>Regards,<br>System Administrator</p>

        <div style="margin-top: 30px; font-size: 12px; color: #666;">
            <p>This is an automated message, please do not reply to this email.</p>
            <p>Token expires: {{ $expires_at->format('F j, Y g:i A') }}</p>
        </div>
    </div>
</body>
</html>