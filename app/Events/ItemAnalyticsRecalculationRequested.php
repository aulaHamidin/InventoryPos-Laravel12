<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ItemAnalyticsRecalculationRequested implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    /** @param array<int> $itemIds */
    public function __construct(
        public readonly int $tenantId,
        public readonly array $itemIds,
        public readonly string $reason,
    ) {}
}
