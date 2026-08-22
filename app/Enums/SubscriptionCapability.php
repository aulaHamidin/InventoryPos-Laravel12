<?php

namespace App\Enums;

enum SubscriptionCapability: string
{
    case Read = 'read';
    case Operate = 'operate';
    case Configure = 'configure';
    case Billing = 'billing';
    case Deletion = 'deletion';
}
