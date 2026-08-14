<?php

namespace App\Models\Concerns;

use LogicException;

trait PreventsHistoricalDeletion
{
    protected static function bootPreventsHistoricalDeletion(): void
    {
        static::deleting(function (): never {
            throw new LogicException('Historical records cannot be deleted.');
        });
    }
}
