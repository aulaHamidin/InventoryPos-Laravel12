<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Redis\Limiters\DurationLimiter;
use Illuminate\Support\Facades\Redis;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $key, $maxAttempts, $decaySeconds] = $argv;

$limiter = new DurationLimiter(
    Redis::connection(),
    $key,
    (int) $maxAttempts,
    (int) $decaySeconds,
);

fwrite(STDOUT, $limiter->acquire() ? 'ALLOWED' : 'DENIED');
