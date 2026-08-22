<?php

namespace App\Enums;

enum TenantDeletionStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Queued = 'queued';
    case Purged = 'purged';
}
