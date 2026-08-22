<?php

namespace App\Services;

use App\Enums\AdminRole;
use App\Models\ImpersonationSession;

final class ImpersonationContext
{
    private static ?ImpersonationSession $session = null;

    public static function set(ImpersonationSession $session): void
    {
        self::$session = $session;
    }

    public static function active(): bool
    {
        return self::$session !== null;
    }

    public static function session(): ?ImpersonationSession
    {
        return self::$session;
    }

    public static function isSupport(): bool
    {
        return self::$session?->admin?->role === AdminRole::Support;
    }

    public static function clear(): void
    {
        self::$session = null;
    }
}
