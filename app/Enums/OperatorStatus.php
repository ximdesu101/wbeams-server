<?php

namespace App\Enums;

enum OperatorStatus: string
{
    case Inactive = 'inactive';
    case Active = 'active';
    case Expired = 'expired';
    case Deactivated = 'deactivated';

    public function label(): string
    {
        return match ($this) {
            self::Inactive => 'Pending Activation',
            self::Active => 'Active',
            self::Expired => 'Expired',
            self::Deactivated => 'Deactivated',
        };
    }
}
