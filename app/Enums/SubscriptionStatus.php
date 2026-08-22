<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case PastDue = 'past_due';
    case Suspended = 'suspended';
    case Expired = 'expired';

    public function isReadable(): bool
    {
        return true;
    }

    public function permitsOperations(): bool
    {
        return in_array($this, [self::Trial, self::Active, self::PastDue], true);
    }

    public function permitsConfiguration(): bool
    {
        return in_array($this, [self::Trial, self::Active], true);
    }
}
