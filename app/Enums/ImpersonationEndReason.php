<?php

namespace App\Enums;

enum ImpersonationEndReason: string
{
    case Explicit = 'explicit';
    case Expired = 'expired';
    case Revoked = 'revoked';
}
