<?php

use App\Support\HardeningEnvironment;
use Illuminate\Support\Facades\Artisan;

it('refuses every destructive hardening command on the normal test database', function () {
    expect((string) config('database.connections.'.config('database.default').'.database'))
        ->not->toContain('hardening');

    expect(Artisan::call('hardening:seed', ['--profile' => 'smoke']))->toBe(1)
        ->and(Artisan::output())->toContain('hardening');

    expect(Artisan::call('hardening:reconcile'))->toBe(1)
        ->and(Artisan::output())->toContain('hardening');

    expect(Artisan::call('hardening:profile-queue'))->toBe(1)
        ->and(Artisan::output())->toContain('hardening');
});

it('requires an isolated Redis namespace for hardening queue commands', function () {
    config()->set('database.redis.options.prefix', 'inventori-q-database-');
    expect(fn () => app(HardeningEnvironment::class)->assertRedisIsolation())
        ->toThrow(RuntimeException::class, 'REDIS_PREFIX');

    config()->set('database.redis.options.prefix', 'f9a-hardening-test-');
    expect(app(HardeningEnvironment::class)->assertRedisIsolation())->toBe('f9a-hardening-test-');
});
