<?php

use App\Actions\Inventory\SetPreferredSupplierAction;
use App\Actions\Pos\CheckoutPosAction;
use App\Actions\Pos\PayCashAction;
use App\Actions\Shopping\ReceiveShoppingListAction;
use App\Exceptions\ApiProblemException;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $mode, $tenantId, $userId, $targetId, $key] = array_pad($argv, 6, null);

try {
    $tenant = Tenant::findOrFail((int) $tenantId);
    TenantContext::set($tenant);
    $owner = User::findOrFail((int) $userId);

    if ($mode === 'checkout') {
        $transaction = app(CheckoutPosAction::class)->execute([[
            'item_id' => (int) $targetId,
            'qty' => 1,
            'discount_amount' => '0.00',
        ]], (string) $key, $owner);
        echo $transaction->id;
    } elseif ($mode === 'pay') {
        app(PayCashAction::class)->execute((int) $targetId, '250.00', $owner);
        echo 'paid';
    } elseif ($mode === 'preferred') {
        app(SetPreferredSupplierAction::class)->execute((int) $targetId, $owner);
        echo 'preferred';
    } elseif ($mode === 'receive') {
        app(ReceiveShoppingListAction::class)->execute((int) $targetId, [[
            'shopping_list_item_id' => (int) $key,
            'qty_received' => 3,
            'harga_satuan' => '80.00',
        ]], $owner);
        echo 'completed';
    } else {
        throw new InvalidArgumentException('Unknown worker mode.');
    }
} catch (ApiProblemException $exception) {
    echo $exception->errorCode;
} catch (ValidationException) {
    echo 'VALIDATION_ERROR';
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.':'.$exception->getMessage());
    exit(1);
}
