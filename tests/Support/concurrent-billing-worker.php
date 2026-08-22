<?php

use App\Actions\Billing\SweepSubscriptionsAction;
use App\Actions\Billing\VerifyManualPaymentAction;
use App\Models\Admin;
use App\Models\BillingPayment;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $mode, $adminId, $paymentId] = array_pad($argv, 4, null);

try {
    if ($mode === 'verify') {
        app(VerifyManualPaymentAction::class)->execute(
            Admin::query()->findOrFail((int) $adminId),
            BillingPayment::query()->findOrFail((int) $paymentId),
        );
        echo 'verified';
    } elseif ($mode === 'sweep') {
        app(SweepSubscriptionsAction::class)->execute();
        echo 'swept';
    } else {
        throw new InvalidArgumentException('Unknown billing worker mode.');
    }
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.':'.$exception->getMessage());
    exit(1);
}
