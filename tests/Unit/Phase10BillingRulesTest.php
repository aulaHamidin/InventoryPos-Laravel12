<?php

use App\Enums\BillingInterval;
use App\Support\BillingPeriodCalculator;
use App\Support\IdentityHasher;
use Carbon\CarbonImmutable;

it('calculates monthly and yearly periods without date overflow', function () {
    $calculator = new BillingPeriodCalculator;

    expect($calculator->end(CarbonImmutable::parse('2028-01-31 10:00:00', 'Asia/Jakarta'), BillingInterval::Monthly)->toDateTimeString())
        ->toBe('2028-02-29 10:00:00')
        ->and($calculator->end(CarbonImmutable::parse('2028-02-29 10:00:00', 'Asia/Jakarta'), BillingInterval::Yearly)->toDateTimeString())
        ->toBe('2029-02-28 10:00:00');
});

it('hashes canonical phone identity with the stable dedicated key', function () {
    $hasher = new IdentityHasher('stable-test-key');

    expect($hasher->phone('0812-3456-7890'))->toBe($hasher->phone('+62 812 3456 7890'))
        ->and($hasher->phone('0812-3456-7890'))->not->toBe(hash_hmac('sha256', '6281234567890', 'different-app-key'));
});
