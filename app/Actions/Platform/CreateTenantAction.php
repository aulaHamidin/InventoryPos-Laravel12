<?php

namespace App\Actions\Platform;

use App\Actions\Audit\RecordAuditAction;
use App\Actions\Billing\CreateSubscriptionAction;
use App\Actions\Billing\GenerateInvoiceAction;
use App\Enums\OperationalStatus;
use App\Enums\UserRole;
use App\Models\Admin;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use App\Support\AdminActorGuard;
use App\Support\AuditContext;
use App\Support\CredentialContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateTenantAction
{
    public function __construct(
        private readonly CreateSubscriptionAction $subscriptions,
        private readonly GenerateInvoiceAction $invoices,
        private readonly RecordAuditAction $audit,
    ) {}

    /** @return array{tenant: Tenant, owner: User, subscription: Subscription, invoice: ?Invoice} */
    public function execute(
        Admin $actor,
        string $storeName,
        string $ownerName,
        string $email,
        string $phone,
        string $password,
        Plan $plan,
        bool $trial,
        ?AuditContext $context = null,
    ): array {
        AdminActorGuard::superAdmin($actor);
        CredentialContract::password($password);

        return DB::transaction(function () use ($actor, $storeName, $ownerName, $email, $phone, $password, $plan, $trial, $context): array {
            $tenant = Tenant::query()->create([
                'nama_toko' => trim($storeName),
                'slug' => Str::slug($storeName).'-'.strtolower(Str::random(6)),
                'operational_status' => OperationalStatus::Active,
            ]);
            TenantContext::set($tenant);
            try {
                $owner = new User([
                    'name' => trim($ownerName), 'email' => strtolower(trim($email)),
                    'no_hp' => trim($phone), 'password' => $password,
                ]);
                $owner->forceFill(['role' => UserRole::Owner, 'is_active' => true, 'auth_version' => 1]);
                $owner->save();
                $subscription = $this->subscriptions->execute($actor, $tenant, $plan, $trial, $phone, context: $context);
                $invoice = $trial ? null : $this->invoices->execute($actor, $subscription, $plan, context: $context);
                $this->audit->execute('platform.tenant_created', $actor, $tenant, newValues: [
                    'nama_toko' => $tenant->nama_toko, 'owner_id' => $owner->getKey(),
                    'subscription_id' => $subscription->getKey(), 'provisioning' => $trial ? 'trial' : 'paid_pending',
                ], context: $context, tenantId: $tenant->getKey());

                return compact('tenant', 'owner', 'subscription', 'invoice');
            } finally {
                TenantContext::clear();
            }
        }, 3);
    }
}
