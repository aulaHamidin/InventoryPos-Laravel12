<?php

use App\Data\AnalyticsCalculationInput;
use App\Enums\MovementClass;
use App\Support\AnalyticsCalculator;
use Carbon\CarbonImmutable;

function analyticsInput(array $overrides = []): AnalyticsCalculationInput
{
    $values = array_merge([
        'asOf' => CarbonImmutable::parse('2026-08-16 10:00:00', 'Asia/Jakarta'),
        'itemCreatedAt' => CarbonImmutable::parse('2026-05-01 10:00:00', 'Asia/Jakarta'),
        'deadStockDays' => 0,
        'grossSale30' => 0,
        'saleVoid30' => 0,
        'customerReturn30' => 0,
        'grossSaleDead' => 0,
        'saleVoidDead' => 0,
        'customerReturnDead' => 0,
        'effectiveLeadTimeDays' => 5,
        'leadTimeSource' => 'item',
        'safetyStockDays' => 2,
    ], $overrides);

    return new AnalyticsCalculationInput(...$values);
}

it('requires an explicit Asia Jakarta business clock', function () {
    $input = analyticsInput(['asOf' => CarbonImmutable::parse('2026-08-16 03:00:00', 'UTC')]);

    expect(fn () => (new AnalyticsCalculator)->calculate($input))
        ->toThrow(InvalidArgumentException::class, 'Asia/Jakarta');
});

it('keeps an item unclassified until exactly thirty times twenty four hours', function () {
    $calculator = new AnalyticsCalculator;
    $created = CarbonImmutable::parse('2026-07-17 10:00:00', 'Asia/Jakarta');

    $before = $calculator->calculate(analyticsInput([
        'asOf' => $created->addHours(720)->subSecond(),
        'itemCreatedAt' => $created,
        'grossSale30' => 30,
    ]));
    $atBoundary = $calculator->calculate(analyticsInput([
        'asOf' => $created->addHours(720),
        'itemCreatedAt' => $created,
        'grossSale30' => 30,
    ]));

    expect($before->eligible)->toBeFalse()
        ->and($before->movementClass)->toBe(MovementClass::Unclassified)
        ->and($before->recommendedThreshold)->toBeNull()
        ->and($atBoundary->eligible)->toBeTrue()
        ->and($atBoundary->movementClass)->toBe(MovementClass::Fast);
});

it('uses the exact seven eight and twenty nine thirty velocity boundaries', function (int $quantity, MovementClass $expected) {
    $result = (new AnalyticsCalculator)->calculate(analyticsInput(['grossSale30' => $quantity]));

    expect($result->movementClass)->toBe($expected);
})->with([
    [7, MovementClass::Slow],
    [8, MovementClass::Normal],
    [29, MovementClass::Normal],
    [30, MovementClass::Fast],
]);

it('subtracts POS reversals clamps at zero and calculates an exact ceiling', function () {
    $result = (new AnalyticsCalculator)->calculate(analyticsInput([
        'grossSale30' => 60,
        'saleVoid30' => 3,
        'customerReturn30' => 2,
        'effectiveLeadTimeDays' => 5,
        'safetyStockDays' => 2,
    ]));

    $clamped = (new AnalyticsCalculator)->calculate(analyticsInput([
        'grossSale30' => 1,
        'saleVoid30' => 2,
        'customerReturn30' => 3,
    ]));

    expect($result->netDemandQty)->toBe(55)
        ->and($result->averageDailyOut)->toBe('1.833333')
        ->and($result->recommendedThreshold)->toBe(13)
        ->and($clamped->netDemandQty)->toBe(0)
        ->and($clamped->recommendedThreshold)->toBe(0);
});

it('applies dead override only when enabled and the full dead window exists', function () {
    $asOf = CarbonImmutable::parse('2026-08-16 10:00:00', 'Asia/Jakarta');
    $calculator = new AnalyticsCalculator;

    $notOldEnough = $calculator->calculate(analyticsInput([
        'asOf' => $asOf,
        'itemCreatedAt' => $asOf->subDays(60),
        'deadStockDays' => 90,
    ]));
    $dead = $calculator->calculate(analyticsInput([
        'asOf' => $asOf,
        'itemCreatedAt' => $asOf->subDays(100),
        'deadStockDays' => 90,
    ]));
    $disabled = $calculator->calculate(analyticsInput([
        'asOf' => $asOf,
        'itemCreatedAt' => $asOf->subDays(100),
        'deadStockDays' => 0,
    ]));

    expect($notOldEnough->movementClass)->toBe(MovementClass::Slow)
        ->and($dead->movementClass)->toBe(MovementClass::Dead)
        ->and($disabled->movementClass)->toBe(MovementClass::Slow);
});

it('accepts preferred lead time zero and returns a zero threshold', function () {
    $result = (new AnalyticsCalculator)->calculate(analyticsInput([
        'grossSale30' => 60,
        'effectiveLeadTimeDays' => 0,
        'leadTimeSource' => 'preferred_supplier',
        'safetyStockDays' => 0,
    ]));

    expect($result->effectiveLeadTimeDays)->toBe(0)
        ->and($result->leadTimeSource)->toBe('preferred_supplier')
        ->and($result->recommendedThreshold)->toBe(0);
});
