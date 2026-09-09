<?php

namespace App\Support;

use App\Models\Operator\Alert;
use App\Models\Recipient\Recipient;

class AlertSmsMessage
{
    public static function build(Alert $alert, Recipient $recipient, string $acknowledgeUrl): string
    {
        $severity = strtoupper($alert->severity);
        $greeting = "Hello {$recipient->first_name},";
        $header = "[{$severity}] Emergency Alert: {$alert->title}";
        $body = trim($alert->message);
        $footer = "Acknowledge: {$acknowledgeUrl}";

        return implode("\n\n", array_filter([$greeting, $header, $body, $footer]));
    }
}
