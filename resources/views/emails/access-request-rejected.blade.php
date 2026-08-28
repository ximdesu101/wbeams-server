<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Request Rejected</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; margin: 0; padding: 0; background: #f4f4f4;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; background: #ffffff;">
        <h2 style="margin-top: 0; color: #991b1b;">Hello {{ $accessRequest->first_name }},</h2>

        <p>We regret to inform you that your access request has been <strong style="color: #991b1b;">rejected</strong>.</p>

        <p><strong>Request details:</strong></p>
        <ul>
            <li><strong>ID Number:</strong> {{ $accessRequest->id_number }}</li>
            <li><strong>Name:</strong> {{ $accessRequest->first_name }} {{ $accessRequest->last_name }}</li>
            <li><strong>Email:</strong> {{ $accessRequest->email }}</li>
        </ul>

        <p>If you believe this was done in error or you have additional information that may help, please contact an administrator for further assistance.</p>

        <p>Regards,<br>WBEAMS System</p>

        <div style="margin-top: 30px; font-size: 12px; color: #666;">
            <p>This is an automated message, please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>