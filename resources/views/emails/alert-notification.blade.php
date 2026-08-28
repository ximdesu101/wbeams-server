<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency Alert: {{ $alert->title }}</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; margin: 0; padding: 0; background: #f4f4f4;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; background: #ffffff;">

        {{-- Severity banner --}}
        @php
            $bannerColors = [
                'low'      => '#2196F3',
                'medium'   => '#FF9800',
                'high'     => '#f44336',
                'critical' => '#7B1FA2',
            ];
            $bannerColor = $bannerColors[$alert->severity] ?? '#555555';
        @endphp
        <div style="background: {{ $bannerColor }}; color: #ffffff; padding: 12px 16px; border-radius: 4px 4px 0 0; margin-bottom: 0;">
            <strong style="font-size: 13px; text-transform: uppercase; letter-spacing: 1px;">
                {{ strtoupper($alert->severity) }} SEVERITY — Emergency Alert
            </strong>
        </div>

        <div style="border: 1px solid #e0e0e0; border-top: none; padding: 24px; border-radius: 0 0 4px 4px;">
            <h2 style="margin-top: 0; color: #212121;">{{ $alert->title }}</h2>

            <p style="color: #424242;">Hello {{ $recipient->first_name }},</p>
            <p style="color: #424242;">
                An emergency alert has been issued. Please read the details carefully and follow the
                instructions below.
            </p>

            {{-- Message --}}
            <div style="background: #fafafa; border-left: 4px solid {{ $bannerColor }}; padding: 12px 16px; margin: 16px 0; border-radius: 0 4px 4px 0;">
                <p style="margin: 0; color: #212121;">{{ $alert->message }}</p>
            </div>

            {{-- Response instructions --}}
            @if (! empty($alert->response_instructions))
                <p style="color: #424242; margin-bottom: 6px;"><strong>Response Instructions:</strong></p>
                <ol style="color: #424242; padding-left: 20px;">
                    @foreach ($alert->response_instructions as $instruction)
                        <li style="margin-bottom: 4px;">{{ $instruction }}</li>
                    @endforeach
                </ol>
            @endif

            {{-- Alert metadata --}}
            <table style="width: 100%; border-collapse: collapse; margin: 16px 0; font-size: 14px; color: #424242;">
                <tr>
                    <td style="padding: 6px 8px; background: #f5f5f5; font-weight: bold; width: 40%;">Alert Type</td>
                    <td style="padding: 6px 8px;">{{ $alert->alertType->name ?? '—' }}</td>
                </tr>
                <tr>
                    <td style="padding: 6px 8px; background: #f5f5f5; font-weight: bold;">Issued By</td>
                    <td style="padding: 6px 8px;">{{ $alert->operator->full_name ?? '—' }}</td>
                </tr>
                <tr>
                    <td style="padding: 6px 8px; background: #f5f5f5; font-weight: bold;">Sent At</td>
                    <td style="padding: 6px 8px;">{{ $alert->sent_at->format('F j, Y g:i A') }}</td>
                </tr>
            </table>

            {{-- Acknowledge button --}}
            <div style="text-align: center; margin: 28px 0 16px;">
                <a href="{{ $acknowledgeUrl }}"
                   style="display: inline-block; padding: 14px 32px; background: {{ $bannerColor }}; color: #ffffff;
                          text-decoration: none; border-radius: 4px; font-size: 16px; font-weight: bold;">
                    Acknowledge Alert
                </a>
            </div>

            <p style="font-size: 13px; color: #757575; text-align: center;">
                Clicking this button confirms you have received and read this alert.<br>
                Your acknowledgment will automatically be reflected in the system.
            </p>

            <p style="font-size: 12px; color: #9e9e9e;">
                If the button does not work, copy and paste this link into your browser:<br>
                <span style="word-break: break-all; background: #f5f5f5; padding: 6px; display: inline-block;
                             border-radius: 4px; margin-top: 4px;">{{ $acknowledgeUrl }}</span>
            </p>
        </div>

        <div style="margin-top: 16px; font-size: 12px; color: #9e9e9e; text-align: center;">
            <p>This is an automated emergency notification. Do not reply to this email.</p>
            <p>This acknowledgment link expires in 7 days.</p>
        </div>

    </div>
</body>
</html>
