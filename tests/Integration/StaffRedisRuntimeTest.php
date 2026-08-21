<?php

use App\Actions\Pos\CheckoutPosAction;
use App\Actions\Staff\ActivateStaffAction;
use App\Actions\Staff\CreateStaffAction;
use App\Actions\Staff\DeactivateStaffAction;
use App\Actions\Staff\ResetStaffAccessAction;
use App\Enums\PosTransactionStatus;
use App\Models\AuditLog;
use App\Models\PosPayment;
use App\Models\PosTransaction;
use App\Models\StockMovement;
use App\Services\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

function requireStaffRuntime(object $test): void
{
    if (! filter_var(env('STAFF_RUNTIME_TESTS', false), FILTER_VALIDATE_BOOL)) {
        $test->markTestSkipped('Redis Staff runtime harness hanya dijalankan oleh job staff-runtime.');
    }
}

it('revokes Redis sessions and tokens without restoring old access after activation', function () {
    requireStaffRuntime($this);
    expect(config('session.driver'))->toBe('redis')
        ->and(config('cache.default'))->toBe('redis');

    [$tenant, $owner] = makeTenantUser();
    $staff = app(CreateStaffAction::class)->execute([
        'name' => 'Redis Staff',
        'email' => 'redis-staff-'.Str::lower(Str::random(10)).'@example.test',
        'no_hp' => '08'.random_int(1000000000, 9999999999),
        'password' => 'PasswordRedis12',
        'password_confirmation' => 'PasswordRedis12',
    ], $owner);
    $oldToken = $staff->createToken('redis-old-token')->plainTextToken;

    $this->actingAs($staff, 'web')->get('/app/items')->assertOk();
    $sessionId = session()->getId();
    expect($sessionId)->not->toBeEmpty();

    TenantContext::set($tenant);
    app(DeactivateStaffAction::class)->execute($staff->id, $owner);
    app(ActivateStaffAction::class)->execute($staff->id, $owner);
    expect($staff->fresh()->is_active)->toBeTrue()
        ->and($staff->fresh()->auth_version)->toBe(2)
        ->and($staff->tokens()->count())->toBe(0);

    Auth::forgetGuards();
    $this->get('/app/items')->assertRedirect('/app/login');
    expect(session()->getId())->not->toBe($sessionId);
    $this->withToken($oldToken)->getJson('/api/v1/items')->assertUnauthorized();

    TenantContext::set($tenant);
    $resetToken = $staff->fresh()->createToken('redis-reset-token')->plainTextToken;
    $this->actingAs($staff->fresh(), 'web')->get('/app/items')->assertOk();
    $resetSessionId = session()->getId();
    TenantContext::set($tenant);
    app(ResetStaffAccessAction::class)->execute($staff->id, [
        'password' => 'PasswordRedisBaru12',
        'password_confirmation' => 'PasswordRedisBaru12',
    ], $owner);

    Auth::forgetGuards();
    $this->get('/app/items')->assertRedirect('/app/login');
    expect(session()->getId())->not->toBe($resetSessionId)
        ->and($staff->fresh()->auth_version)->toBe(3);
    $this->withToken($resetToken)->getJson('/api/v1/items')->assertUnauthorized();
});

it('serializes two Staff payments on the final stock without duplicate actor state', function () {
    requireStaffRuntime($this);

    [$tenant, $owner] = makeTenantUser();
    $staffOne = app(CreateStaffAction::class)->execute([
        'name' => 'Concurrent Staff One',
        'email' => 'concurrent-one-'.Str::lower(Str::random(10)).'@example.test',
        'no_hp' => '08'.random_int(1000000000, 9999999999),
        'password' => 'PasswordKasir12',
        'password_confirmation' => 'PasswordKasir12',
    ], $owner);
    $staffTwo = app(CreateStaffAction::class)->execute([
        'name' => 'Concurrent Staff Two',
        'email' => 'concurrent-two-'.Str::lower(Str::random(10)).'@example.test',
        'no_hp' => '08'.random_int(1000000000, 9999999999),
        'password' => 'PasswordKasir12',
        'password_confirmation' => 'PasswordKasir12',
    ], $owner);
    $item = makeInventoryItem(['stok_saat_ini' => 1, 'harga_jual' => '100.00']);

    $checkout = app(CheckoutPosAction::class);
    $transactionOne = $checkout->execute([[
        'item_id' => $item->id, 'qty' => 1, 'discount_amount' => '0.00',
    ]], (string) Str::uuid(), $staffOne);
    $transactionTwo = $checkout->execute([[
        'item_id' => $item->id, 'qty' => 1, 'discount_amount' => '0.00',
    ]], (string) Str::uuid(), $staffTwo);

    $base = [PHP_BINARY, base_path('tests/Support/concurrent-pos-worker.php'), 'pay', (string) $tenant->id];
    $first = new Process([...$base, (string) $staffOne->id, (string) $transactionOne->id, 'unused', 'unused'], base_path());
    $second = new Process([...$base, (string) $staffTwo->id, (string) $transactionTwo->id, 'unused', 'unused'], base_path());
    $first->setTimeout(30);
    $second->setTimeout(30);
    $first->start();
    $second->start();
    $first->wait();
    $second->wait();

    expect($first->isSuccessful())->toBeTrue($first->getErrorOutput())
        ->and($second->isSuccessful())->toBeTrue($second->getErrorOutput())
        ->and([$first->getOutput(), $second->getOutput()])->toContain('paid')
        ->toContain('INSUFFICIENT_STOCK');

    TenantContext::set($tenant);
    $completed = PosTransaction::query()->whereIn('id', [$transactionOne->id, $transactionTwo->id])
        ->where('status', PosTransactionStatus::Completed->value)->firstOrFail();
    $failed = PosTransaction::query()->whereIn('id', [$transactionOne->id, $transactionTwo->id])
        ->where('status', PosTransactionStatus::Failed->value)->firstOrFail();
    $movement = StockMovement::query()->where('reference_type', PosTransaction::class)
        ->where('reference_id', $completed->id)->where('movement_type', 'sale')->firstOrFail();
    $payment = PosPayment::query()->where('pos_transaction_id', $completed->id)->firstOrFail();

    expect($item->fresh()->stok_saat_ini)->toBe(0)
        ->and(StockMovement::query()->where('movement_type', 'sale')->whereIn('reference_id', [$completed->id, $failed->id])->count())->toBe(1)
        ->and(PosPayment::query()->whereIn('pos_transaction_id', [$completed->id, $failed->id])->count())->toBe(1)
        ->and($movement->user_id)->toBe($completed->cashier_id)
        ->and($payment->confirmed_by)->toBeNull()
        ->and(AuditLog::query()->where('subject_type', PosTransaction::class)->where('subject_id', $completed->id)
            ->where('action', 'pos.paid_cash')->value('actor_id'))->toBe($completed->cashier_id);
});
