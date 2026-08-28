<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Request Approved</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; margin: 0; padding: 0; background: #f4f4f4;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; background: #ffffff;">
        <h2 style="margin-top: 0; color: #166534;">Hello {{ $accessRequest->first_name }},</h2>

        <p>Good news! Your access request has been <strong style="color: #166534;">approved</strong>.</p>

        <p>You can now proceed to register your account using the information you submitted:</p>

        <ul>
            <li><strong>ID Number:</strong> {{ $accessRequest->id_number }}</li>
            <li><strong>Name:</strong> {{ $accessRequest->first_name }} {{ $accessRequest->last_name }}</li>
            <li><strong>Email:</strong> {{ $accessRequest->email }}</li>
        </ul>

        <p>Please go to the registration page and complete your account setup with the same ID number and name so the system can verify you against the masterlist.</p>

        <p>If you did not submit this request, please contact an administrator.</p>

        <p>Regards,<br>WBEAMS System</p>

        <div style="margin-top: 30px; font-size: 12px; color: #666;">
            <p>This is an automated message, please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>