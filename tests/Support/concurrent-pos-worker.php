<?php

use App\Actions\Inventory\SetPreferredSupplierAction;
use App\Actions\Inventory\StockOutAction;
use App\Actions\Opname\CreateOpnameAction;
use App\Actions\Opname\FinalizeOpnameAction;
use App\Actions\Opname\SaveOpnameCountAction;
use App\Actions\Pos\CheckoutPosAction;
use App\Actions\Pos\ConfirmManualPaymentAction;
use App\Actions\Pos\ExpirePendingPosTransactionAction;
use App\Actions\Pos\PayCashAction;
use App\Actions\Pos\ReturnPosTransactionAction;
use App\Actions\Pos\VoidPosTransactionAction;
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

[$script, $mode, $tenantId, $userId, $targetId, $key, $value] = array_pad($argv, 7, null);

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
    } elseif ($mode === 'manual') {
        app(ConfirmManualPaymentAction::class)->execute(
            (int) $targetId, (string) $value, (string) $key, $owner, null, null,
        );
        echo 'manual';
    } elseif ($mode === 'void') {
        app(VoidPosTransactionAction::class)->execute((int) $targetId, (string) $key, $owner);
        echo 'voided';
    } elseif ($mode === 'return') {
        app(ReturnPosTransactionAction::class)->execute((int) $targetId, [[
            'pos_transaction_item_id' => (int) $key,
            'qty' => (int) $value,
        ]], $owner);
        echo 'returned';
    } elseif ($mode === 'expire') {
        echo app(ExpirePendingPosTransactionAction::class)->execute((int) $targetId) ? 'expired' : 'skipped';
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
    } elseif ($mode === 'opname-create') {
        $opname = app(CreateOpnameAction::class)->execute(
            (string) $key,
            $owner,
            (int) $targetId > 0 ? (int) $targetId : null,
        );
        echo $opname->id;
    } elseif ($mode === 'opname-count') {
        app(SaveOpnameCountAction::class)->execute((int) $targetId, [[
            'item_id' => (int) $key,
            'qty_fisik' => (int) $value,
        ]], $owner);
        echo 'counted';
    } elseif ($mode === 'opname-finalize') {
        app(FinalizeOpnameAction::class)->execute((int) $targetId, $owner);
        echo 'completed';
    } elseif ($mode === 'stock-out') {
        app(StockOutAction::class)->execute((int) $targetId, (int) $key, $owner);
        echo 'stocked-out';
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
