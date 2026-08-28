<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alert Acknowledged</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; margin: 0; padding: 0; background: #f4f4f4;">
    <div style="max-width: 500px; margin: 60px auto; padding: 32px; background: #ffffff;
                border-radius: 8px; text-align: center; border: 1px solid #e0e0e0;">

        <div style="width: 64px; height: 64px; background: #E8F5E9; border-radius: 50%;
                    display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
            <span style="font-size: 32px;">✓</span>
        </div>

        <h2 style="color: #2E7D32; margin-top: 0;">Alert Acknowledged</h2>

        <p style="color: #424242;">
            Thank you, <strong>{{ $recipient->first_name }}</strong>. You have successfully acknowledged
            the following alert:
        </p>

        <div style="background: #f5f5f5; padding: 12px 16px; border-radius: 4px; margin: 16px 0; text-align: left;">
            <p style="margin: 0 0 4px;"><strong>{{ $alert->title }}</strong></p>
            <p style="margin: 0; font-size: 13px; color: #757575;">
                Sent on {{ $alert->sent_at->format('F j, Y g:i A') }}
            </p>
        </div>

        <p style="color: #616161; font-size: 14px;">
            Your acknowledgment has been recorded in the system. You can close this page.
        </p>

        <div style="margin-top: 24px; font-size: 12px; color: #9e9e9e;">
            <p>This is an automated message. Do not reply to this email.</p>
        </div>
    </div>
</body>
</html>
