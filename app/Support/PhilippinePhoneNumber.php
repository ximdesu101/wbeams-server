<?php

namespace App\Support;

class PhilippinePhoneNumber
{
    /**
     * Normalize a Philippine mobile number to PhilSMS format (639XXXXXXXXX).
     */
    public static function normalize(?string $number): ?string
    {
        if ($number === null || trim($number) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $number);

        if ($digits === null || $digits === '') {
            return null;
        }

        if (str_starts_with($digits, '09') && strlen($digits) === 11) {
            return '63'.substr($digits, 1);
        }

        if (str_starts_with($digits, '639') && strlen($digits) === 12) {
            return $digits;
        }

        if (str_starts_with($digits, '63') && strlen($digits) === 12) {
            return $digits;
        }

        return null;
    }
}
