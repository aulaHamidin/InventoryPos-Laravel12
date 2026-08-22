<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class HardeningEnvironment
{
    public function assertSafe(): string
    {
        $database = (string) DB::connection()->getDatabaseName();

        if (! app()->environment(['local', 'testing']) || ! str_contains(strtolower($database), 'hardening')) {
            throw new RuntimeException(
                'Hardening harness hanya boleh berjalan pada APP_ENV local/testing dan database terpisah yang namanya memuat "hardening".',
            );
        }

        return $database;
    }

    public function assertRedisIsolation(): string
    {
        $prefix = (string) config('database.redis.options.prefix');

        if (! preg_match('/(?:f9a|hardening)/i', $prefix)) {
            throw new RuntimeException(
                'Hardening Redis wajib memakai REDIS_PREFIX terisolasi yang memuat "f9a" atau "hardening".',
            );
        }

        return $prefix;
    }
}
