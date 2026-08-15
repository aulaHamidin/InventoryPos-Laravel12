<?php

use App\Enums\StockOpnameStatus;

it('only permits the draft to completed transition', function () {
    expect(StockOpnameStatus::Draft->canTransitionTo(StockOpnameStatus::Completed))->toBeTrue()
        ->and(StockOpnameStatus::Completed->canTransitionTo(StockOpnameStatus::Draft))->toBeFalse()
        ->and(StockOpnameStatus::Completed->canTransitionTo(StockOpnameStatus::Completed))->toBeFalse();
});
