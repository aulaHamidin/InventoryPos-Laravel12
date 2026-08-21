<?php

use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\CacheSchedulingMutex;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Console\Kernel;

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! filter_var(env('ANALYTICS_RUNTIME_TESTS', false), FILTER_VALIDATE_BOOL)) {
    fwrite(STDERR, "Runtime test flag is required.\n");
    exit(2);
}

$mode = $argv[1] ?? '';
$event = collect($app->make(Schedule::class)->events())
    ->first(fn ($scheduled): bool => str_contains((string) $scheduled->command, 'analytics:recalculate'));
if ($event === null) {
    fwrite(STDERR, "Analytics schedule not found.\n");
    exit(2);
}

if ($mode === 'overlap') {
    $acquired = $event->mutex->create($event);
} elseif ($mode === 'single-server') {
    $mutex = new CacheSchedulingMutex($app->make(CacheFactory::class));
    $minute = CarbonImmutable::parse('2026-08-16 00:15:00', 'Asia/Jakarta');
    $acquired = $mutex->create($event, $minute);
} else {
    fwrite(STDERR, "Unknown mutex mode.\n");
    exit(2);
}

echo $acquired ? "ACQUIRED\n" : "DENIED\n";
flush();
if (! $acquired) {
    exit(0);
}

usleep(750_000);
if ($mode === 'overlap') {
    $event->mutex->forget($event);
} else {
    $app->make(CacheFactory::class)->store()->getStore()
        ->lock($event->mutexName().$minute->format('Hi'))
        ->forceRelease();
}
