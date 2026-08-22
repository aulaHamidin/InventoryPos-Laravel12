<?php

use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

it('uses the atomic Redis throttler across concurrent processes', function () {
    if (! filter_var(env('HARDENING_RUNTIME_TESTS', false), FILTER_VALIDATE_BOOL)) {
        $this->markTestSkipped('Redis hardening harness hanya dijalankan oleh job hardening-runtime.');
    }

    expect(config('cache.default'))->toBe('redis')
        ->and(app('router')->getMiddleware()['throttle'])->toBe(ThrottleRequestsWithRedis::class);

    $key = 'f9a-rate-limit:'.Str::uuid();
    Redis::del($key, $key.':timer');

    $processes = collect(range(1, 10))->map(function () use ($key): Process {
        $process = new Process([
            PHP_BINARY,
            base_path('tests/Support/redis-rate-limit-worker.php'),
            $key,
            '5',
            '60',
        ], base_path());
        $process->setTimeout(20);
        $process->start();

        return $process;
    });

    $results = $processes->map(function (Process $process): string {
        $process->wait();
        expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());

        return trim($process->getOutput());
    });

    expect($results->filter(fn (string $result): bool => $result === 'ALLOWED')->count())->toBe(5)
        ->and($results->filter(fn (string $result): bool => $result === 'DENIED')->count())->toBe(5)
        ->and((int) Redis::ttl($key))->toBeGreaterThanOrEqual(60)->toBeLessThanOrEqual(120);

    Redis::del($key, $key.':timer');
});
