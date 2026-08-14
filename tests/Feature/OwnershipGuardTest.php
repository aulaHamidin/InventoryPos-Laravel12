<?php

use App\Actions\Audit\RecordAuditAction;
use App\Models\AuditLog;
use App\Models\Item;
use App\Services\TenantContext;
use App\Support\OwnershipGuard;
use Illuminate\Database\Eloquent\ModelNotFoundException;

it('fails closed for reads and writes when tenant context is missing', function () {
    [$tenant, $owner] = makeTenantUser();
    makeInventoryItem();
    app(RecordAuditAction::class)->execute('test.audit', $owner);
    TenantContext::clear();

    expect(Item::count())->toBe(0)
        ->and(AuditLog::count())->toBe(0)
        ->and(fn () => Item::create(['nama' => 'forged']))->toThrow(LogicException::class);

    TenantContext::set($tenant);
    expect(AuditLog::count())->toBe(1);
});

it('returns own model and hides a cross tenant model as 404', function () {
    [$tenantA] = makeTenantUser();
    $itemA = makeInventoryItem();
    makeTenantUser();
    $itemB = makeInventoryItem();

    TenantContext::set($tenantA);

    expect(OwnershipGuard::validate(Item::class, $itemA->id)->id)->toBe($itemA->id);
    expect(fn () => OwnershipGuard::validate(Item::class, $itemB->id))->toThrow(ModelNotFoundException::class);
});

it('overwrites forged tenant id on tenant model creation', function () {
    [$tenantA] = makeTenantUser();
    [$tenantB] = makeTenantUser();
    TenantContext::set($tenantA);

    $item = makeInventoryItem(['tenant_id' => $tenantB->id]);

    expect($item->tenant_id)->toBe($tenantA->id);
});
