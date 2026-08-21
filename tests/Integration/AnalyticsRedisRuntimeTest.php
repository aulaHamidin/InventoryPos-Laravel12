<?php

use App\Events\ItemAnalyticsRecalculationRequested;
use App\Jobs\RecalculateItemAnalyticsJob;
use App\Models\Item;
use App\Support\AnalyticsClock;
use Illuminate\Bus\UniqueLock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

it('coalesces duplicates and accepts a follow-up after processing starts on real Redis', function () {
    if (! filter_var(env('ANALYTICS_RUNTIME_TESTS', false), FILTER_VALIDATE_BOOL)) {
        $this->markTestSkipped('Redis runtime harness hanya dijalankan oleh job analytics-runtime.');
    }

    expect(config('cache.default'))->toBe('redis')
        ->and(config('queue.default'))->toBe('redis');

    Queue::connection('redis')->clear('analytics');
    [, $owner] = makeTenantUser(tenantAttributes: ['dead_stock_days' => 0]);
    $item = makeInventoryItem(['threshold_mode' => 'manual']);
    $asOf = app(AnalyticsClock::class)->now();
    DB::table('items')->where('id', $item->id)->update([
        'created_at' => AnalyticsClock::storage($asOf->subDays(31)),
    ]);

    $job = new RecalculateItemAnalyticsJob((int) $owner->tenant_id, (int) $item->id, 'redis_runtime');
    RecalculateItemAnalyticsJob::dispatch($job->tenantId, $job->itemId, $job->reason);
    RecalculateItemAnalyticsJob::dispatch($job->tenantId, $job->itemId, $job->reason);
    expect(Queue::connection('redis')->size('analytics'))->toBe(1);

    $store = Cache::getStore();
    $redisKey = $store->getPrefix().UniqueLock::getKey($job);
    $ttl = (int) $store->lockConnection()->ttl($redisKey);
    expect($ttl)->toBeGreaterThanOrEqual(295)->toBeLessThanOrEqual(300);

    DB::beginTransaction();
    Item::whereKey($item->id)->lockForUpdate()->firstOrFail();
    $worker = new Process([
        PHP_BINARY,
        'artisan',
        'queue:work',
        'redis',
        '--queue=analytics',
        '--once',
        '--sleep=0',
        '--tries=1',
        '--timeout=30',
    ], base_path());
    $worker->setTimeout(40);

    try {
        $worker->start();
        $deadline = microtime(true) + 10;
        do {
            usleep(50_000);
        } while (Queue::connection('redis')->pendingSize('analytics') !== 0 && microtime(true) < $deadline);

        expect(Queue::connection('redis')->pendingSize('analytics'))->toBe(0)
            ->and(Queue::connection('redis')->reservedSize('analytics'))->toBe(1);
        do {
            $ttl = (int) $store->lockConnection()->ttl($redisKey);
            if ($ttl === -2) {
                break;
            }
            usleep(50_000);
        } while (microtime(true) < $deadline);
        expect($ttl)->toBe(-2);

        RecalculateItemAnalyticsJob::dispatch($job->tenantId, $job->itemId, 'follow_up');
        expect(Queue::connection('redis')->pendingSize('analytics'))->toBe(1)
            ->and(Queue::connection('redis')->size('analytics'))->toBe(2);
    } finally {
        DB::commit();
    }

    $worker->wait();
    expect($worker->isSuccessful())->toBeTrue($worker->getErrorOutput().$worker->getOutput());

    $followUpWorker = new Process([
        PHP_BINARY,
        'artisan',
        'queue:work',
        'redis',
        '--queue=analytics',
        '--once',
        '--sleep=0',
        '--tries=1',
        '--timeout=30',
    ], base_path());
    $followUpWorker->setTimeout(40);
    $followUpWorker->mustRun();

    expect(Item::withoutGlobalScopes()->findOrFail($item->id)->analytics_calculated_at)->not->toBeNull()
        ->and(Queue::connection('redis')->size('analytics'))->toBe(0);
});

it('uses Redis for overlap and single-server scheduler mutexes across processes', function () {
    if (! filter_var(env('ANALYTICS_RUNTIME_TESTS', false), FILTER_VALIDATE_BOOL)) {
        $this->markTestSkipped('Redis runtime harness hanya dijalankan oleh job analytics-runtime.');
    }

    foreach (['overlap', 'single-server'] as $mode) {
        $first = new Process([PHP_BINARY, 'tests/Support/analytics-scheduler-lock-worker.php', $mode], base_path());
        $first->setTimeout(15);
        $first->start();
        $deadline = microtime(true) + 5;
        do {
            usleep(25_000);
        } while (! str_contains($first->getOutput(), 'ACQUIRED') && microtime(true) < $deadline);
        expect($first->getOutput())->toContain('ACQUIRED');

        $second = new Process([PHP_BINARY, 'tests/Support/analytics-scheduler-lock-worker.php', $mode], base_path());
        $second->setTimeout(10);
        $second->mustRun();
        expect($second->getOutput())->toContain('DENIED');

        $first->wait();
        expect($first->isSuccessful())->toBeTrue($first->getErrorOutput().$first->getOutput());
    }
});

it('publishes analytics work only after commit and never after rollback', function () {
    if (! filter_var(env('ANALYTICS_RUNTIME_TESTS', false), FILTER_VALIDATE_BOOL)) {
        $this->markTestSkipped('Redis runtime harness hanya dijalankan oleh job analytics-runtime.');
    }

    Queue::connection('redis')->clear('analytics');
    [$tenant] = makeTenantUser();
    $item = makeInventoryItem();

    DB::beginTransaction();
    ItemAnalyticsRecalculationRequested::dispatch($tenant->id, [$item->id], 'commit_probe');
    expect(Queue::connection('redis')->size('analytics'))->toBe(0);
    DB::commit();
    expect(Queue::connection('redis')->size('analytics'))->toBe(1);

    Queue::connection('redis')->clear('analytics');
    DB::beginTransaction();
    ItemAnalyticsRecalculationRequested::dispatch($tenant->id, [$item->id], 'rollback_probe');
    expect(Queue::connection('redis')->size('analytics'))->toBe(0);
    DB::rollBack();
    expect(Queue::connection('redis')->size('analytics'))->toBe(0);
});
