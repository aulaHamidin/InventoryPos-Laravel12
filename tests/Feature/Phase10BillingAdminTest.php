<?php

use App\Actions\Admin\CreateSupportAction;
use App\Actions\Admin\DeactivateSupportAction;
use App\Actions\Admin\ResetSupportAccessAction;
use App\Actions\Billing\CreatePlanAction;
use App\Actions\Billing\CreateSubscriptionAction;
use App\Actions\Billing\GenerateInvoiceAction;
use App\Actions\Billing\RecordManualPaymentAction;
use App\Actions\Billing\SweepSubscriptionsAction;
use App\Actions\Billing\TransitionSubscriptionAction;
use App\Actions\Billing\VerifyManualPaymentAction;
use App\Actions\Deletion\ApproveTenantDeletionAction;
use App\Actions\Deletion\CancelTenantDeletionAction;
use App\Actions\Deletion\PurgeTenantAction;
use App\Actions\Deletion\QueueDueTenantDeletionsAction;
use App\Actions\Deletion\RequestTenantDeletionAction;
use App\Actions\Impersonation\ExpireImpersonationSessionsAction;
use App\Actions\Platform\CreateTenantAction;
use App\Enums\AdminRole;
use App\Enums\BillingInterval;
use App\Enums\BillingPaymentStatus;
use App\Enums\ImpersonationEndReason;
use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionCapability;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantDeletionStatus;
use App\Enums\UserRole;
use App\Http\Middleware\EnsureAdminAccess;
use App\Http\Middleware\EnsureTenantUserActive;
use App\Http\Middleware\ResolveImpersonation;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\ImpersonationSession;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantDeletionRequest;
use App\Models\TrialClaim;
use App\Services\ImpersonationContext;
use App\Services\TenantContext;
use App\Support\AdminMfaService;
use App\Support\MrrCalculator;
use App\Support\SubscriptionCapabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

function f10Admin(AdminRole $role = AdminRole::SuperAdmin): Admin
{
    $admin = new Admin(['name' => 'F10 Admin', 'email' => uniqid('f10-', true).'@example.test', 'password' => 'strong-password-123']);
    $admin->forceFill(['role' => $role, 'is_active' => true, 'auth_version' => 1])->save();

    return $admin;
}

function f10Plan(Admin $admin, string $code, string $price = '120000.00', BillingInterval $interval = BillingInterval::Monthly, bool $trial = false): Plan
{
    return app(CreatePlanAction::class)->execute($admin, $code, "Plan {$code}", $interval, $price, $trial, $trial ? 14 : null);
}

it('backfills a hidden zero-priced Legacy subscription and enforces generated slots', function () {
    $legacy = Plan::query()->where('code', 'LEGACY-F0-F9')->firstOrFail();
    expect($legacy->is_internal)->toBeTrue()->and($legacy->is_active)->toBeFalse()->and($legacy->price)->toBe('0.00');

    [$tenant] = makeTenantUser();
    expect(Subscription::query()->where('tenant_id', $tenant->id)->where('status', SubscriptionStatus::Active)->count())->toBe(1);
    expect(fn () => Subscription::query()->create([
        'tenant_id' => $tenant->id, 'plan_id' => $legacy->id, 'status' => SubscriptionStatus::Active,
        'starts_at' => now(), 'ends_at' => now()->addYear(),
    ]))->toThrow(QueryException::class);
});

it('computes MRR from active paid subscriptions only and separates past due', function () {
    $admin = f10Admin();
    $monthly = f10Plan($admin, 'MONTHLY-MRR', '120000.00');
    $yearly = f10Plan($admin, 'YEARLY-MRR', '1200000.00', BillingInterval::Yearly);
    foreach ([[$monthly, SubscriptionStatus::Active], [$yearly, SubscriptionStatus::Active], [$monthly, SubscriptionStatus::PastDue]] as [$plan, $status]) {
        $tenant = Tenant::factory()->create();
        Subscription::query()->where('tenant_id', $tenant->id)->delete();
        Subscription::query()->create(['tenant_id' => $tenant->id, 'plan_id' => $plan->id, 'status' => $status, 'starts_at' => now(), 'ends_at' => now()->addYear()]);
    }

    expect(app(MrrCalculator::class)->totals())->toBe(['mrr' => '220000.00', 'past_due' => '120000.00']);
});

it('keeps the MRR query count constant as subscription volume grows', function () {
    $admin = f10Admin();
    $plan = f10Plan($admin, 'MRR-QUERY-COUNT', '100000.00');
    DB::enableQueryLog();
    DB::flushQueryLog();
    app(MrrCalculator::class)->totals();
    $emptyCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    foreach (range(1, 25) as $_index) {
        $tenant = Tenant::factory()->create();
        Subscription::query()->where('tenant_id', $tenant->id)->delete();
        Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonth(),
        ]);
    }

    DB::enableQueryLog();
    DB::flushQueryLog();
    app(MrrCalculator::class)->totals();
    $loadedCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($loadedCount)->toBe($emptyCount)->toBeLessThanOrEqual(2);
});

it('records and verifies a manual payment atomically with renewal rules', function () {
    [$tenant] = makeTenantUser();
    $admin = f10Admin();
    $plan = f10Plan($admin, 'PAID-MANUAL', '150000.00');
    $subscription = Subscription::query()->where('tenant_id', $tenant->id)->firstOrFail();
    $subscription->forceFill(['plan_id' => $plan->id, 'status' => SubscriptionStatus::PastDue, 'starts_at' => '2026-07-01 00:00:00', 'ends_at' => '2026-08-01 00:00:00'])->save();
    $invoice = app(GenerateInvoiceAction::class)->execute($admin, $subscription, $plan, CarbonImmutable::parse('2026-08-10', 'Asia/Jakarta'));
    $payment = app(RecordManualPaymentAction::class)->execute($admin, $invoice, 'BANK-SECRET-REFERENCE');
    expect(fn () => app(RecordManualPaymentAction::class)->execute($admin, $invoice, 'DUPLICATE-PENDING'))->toThrow(ConflictHttpException::class);
    $verified = app(VerifyManualPaymentAction::class)->execute($admin, $payment, CarbonImmutable::parse('2026-08-03 10:00:00', 'Asia/Jakarta'));

    expect($verified->status)->toBe(BillingPaymentStatus::Paid)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($subscription->fresh()->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->fresh()->ends_at->setTimezone('Asia/Jakarta')->toDateTimeString())->toBe('2026-09-01 07:00:00')
        ->and(app(VerifyManualPaymentAction::class)->execute($admin, $verified)->id)->toBe($verified->id)
        ->and(AuditLog::query()->withoutGlobalScopes()->where('action', 'billing.payment_verified')->count())->toBe(1)
        ->and(json_encode(AuditLog::query()->withoutGlobalScopes()->get()->toArray()))->not->toContain('BANK-SECRET-REFERENCE');
});

it('applies exact trial, active and grace sweep boundaries in Jakarta time', function () {
    $admin = f10Admin();
    $plan = f10Plan($admin, 'SWEEP-PLAN');
    $tenant = Tenant::factory()->create();
    Subscription::query()->where('tenant_id', $tenant->id)->delete();
    $subscription = Subscription::query()->create([
        'tenant_id' => $tenant->id, 'plan_id' => $plan->id, 'status' => SubscriptionStatus::Active,
        'starts_at' => '2026-07-01 17:00:00', 'ends_at' => '2026-08-21 17:00:00',
    ]);
    $counts = app(SweepSubscriptionsAction::class)->execute(CarbonImmutable::parse('2026-08-22 00:00:00', 'Asia/Jakarta'));
    expect($counts['past_due'])->toBe(1)->and($subscription->fresh()->status)->toBe(SubscriptionStatus::PastDue);
    app(SweepSubscriptionsAction::class)->execute(CarbonImmutable::parse('2026-08-29 00:00:00', 'Asia/Jakarta'));
    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Suspended);
});

it('enforces the complete legal subscription transition matrix', function () {
    $admin = f10Admin();
    $plan = f10Plan($admin, 'TRANSITION-MATRIX');
    $legal = [
        'trial' => ['active', 'expired'],
        'active' => ['past_due'],
        'past_due' => ['active', 'suspended'],
        'suspended' => ['active'],
        'expired' => [],
    ];

    foreach (SubscriptionStatus::cases() as $from) {
        foreach (SubscriptionStatus::cases() as $to) {
            if ($from === $to) {
                continue;
            }
            $tenant = Tenant::factory()->create();
            Subscription::query()->where('tenant_id', $tenant->id)->delete();
            $subscription = Subscription::query()->create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'status' => $from,
                'starts_at' => now()->subMonth(),
                'ends_at' => now()->addMonth(),
            ]);
            if (in_array($to->value, $legal[$from->value], true)) {
                expect(app(TransitionSubscriptionAction::class)->execute($subscription, $to)->status)->toBe($to);
            } else {
                expect(fn () => app(TransitionSubscriptionAction::class)->execute($subscription, $to))->toThrow(ConflictHttpException::class);
            }
        }
    }
});

it('intersects role and subscription capability without elevating Staff', function () {
    [$tenant, $owner] = makeTenantUser();
    $staff = makeTenantScopedUser(['name' => 'Staff F10', 'email' => uniqid().'@test.local', 'no_hp' => '081234560001', 'password' => 'password'], UserRole::Staff);
    $subscription = Subscription::query()->where('tenant_id', $tenant->id)->firstOrFail();
    $service = app(SubscriptionCapabilityService::class);
    $subscription->forceFill(['status' => SubscriptionStatus::PastDue])->save();
    $service->forget($tenant);

    expect($service->allows($owner, SubscriptionCapability::Read))->toBeTrue()
        ->and($service->allows($owner, SubscriptionCapability::Operate))->toBeTrue()
        ->and($service->allows($owner, SubscriptionCapability::Configure))->toBeFalse()
        ->and($service->allows($staff, SubscriptionCapability::Billing))->toBeFalse();
});

it('denies suspended mutations at web and API boundaries with zero side effects', function () {
    [$tenant, $owner] = makeTenantUser();
    $subscription = Subscription::query()->where('tenant_id', $tenant->id)->firstOrFail();
    $subscription->forceFill(['status' => SubscriptionStatus::Suspended])->save();
    app(SubscriptionCapabilityService::class)->forget($tenant);
    $auditCount = AuditLog::query()->withoutGlobalScopes()->count();

    Sanctum::actingAs($owner);
    $this->postJson('/api/v1/stock/movements/adjustment', [
        'item_id' => 999999,
        'qty_baru' => 1,
    ])->assertStatus(403)
        ->assertJsonPath('error_code', 'SUBSCRIPTION_CAPABILITY_DENIED');
    expect(AuditLog::query()->withoutGlobalScopes()->count())->toBe($auditCount);
    $this->getJson('/api/v1/items')->assertOk();

    $this->actingAs($owner, 'web')->get('/app/pos')->assertForbidden();
});

it('enforces Admin TOTP replay prevention and one-time recovery codes', function () {
    $admin = f10Admin();
    $mfa = app(AdminMfaService::class);
    $secret = $mfa->ensureSecret($admin);
    $otp = app(Google2FA::class)->getCurrentOtp($secret);
    $codes = $mfa->confirm($admin, $otp);
    expect($codes)->toHaveCount(8)
        ->and($mfa->verify($admin->fresh(), $otp))->toBeFalse()
        ->and($mfa->verify($admin->fresh(), $codes[0]))->toBeTrue()
        ->and($mfa->verify($admin->fresh(), $codes[0]))->toBeFalse();
});

it('revokes Support access by auth version without auditing credentials', function () {
    $admin = f10Admin();
    $support = app(CreateSupportAction::class)->execute($admin, 'Support F10', uniqid().'@example.test', 'initial-password-123');
    app(ResetSupportAccessAction::class)->execute($admin, $support, 'replacement-password-123');
    app(DeactivateSupportAction::class)->execute($admin, $support);
    $audit = json_encode(AuditLog::query()->withoutGlobalScopes()->get()->toArray());

    expect($support->fresh()->is_active)->toBeFalse()->and($support->fresh()->auth_version)->toBe(3)
        ->and($audit)->not->toContain('initial-password-123')->not->toContain('replacement-password-123');
});

it('queues and purges approved deletion while retaining trial claim and global tombstone', function () {
    [$tenant, $owner] = makeTenantUser();
    TrialClaim::query()->create(['phone_hash' => hash('sha256', 'retained')]);
    $admin = f10Admin();
    $deletion = app(RequestTenantDeletionAction::class)->execute($owner, 'Tenant tidak lagi digunakan untuk operasional.');
    $approvedAt = CarbonImmutable::parse('2026-07-01 10:00:00', 'Asia/Jakarta');
    app(ApproveTenantDeletionAction::class)->execute($admin, $deletion, $approvedAt);
    expect($tenant->fresh()->canOperate())->toBeFalse()->and($owner->fresh()->auth_version)->toBe(2);
    app(QueueDueTenantDeletionsAction::class)->execute($approvedAt->addDays(30));
    $deletion = TenantDeletionRequest::query()->findOrFail($deletion->id);
    expect($deletion->status)->toBe(TenantDeletionStatus::Queued);
    app(PurgeTenantAction::class)->execute($deletion, $approvedAt->addDays(30));

    expect(Tenant::query()->whereKey($tenant->id)->exists())->toBeFalse()
        ->and(TrialClaim::query()->count())->toBe(1)
        ->and(AuditLog::query()->withoutGlobalScopes()->whereNull('tenant_id')->where('action', 'tenant.purged')->exists())->toBeTrue();
});

it('exposes strict Owner billing and deletion API while denying Staff', function () {
    [$tenant, $owner] = makeTenantUser();
    Sanctum::actingAs($owner);
    $subscriptionResponse = $this->getJson('/api/v1/billing/subscription')->assertOk()->assertJsonPath('data.status', 'active');
    expect($subscriptionResponse->json('data.starts_at'))->toEndWith('+07:00')
        ->and($subscriptionResponse->json('data.ends_at'))->toEndWith('+07:00');
    $this->postJson('/api/v1/tenant/deletion-request', ['reason' => 'hapus'])->assertUnprocessable();
    $this->postJson('/api/v1/tenant/deletion-request', ['reason' => 'Tenant ini sudah tidak dipakai lagi.', 'tenant_id' => $tenant->id])->assertUnprocessable();
    TenantContext::set($tenant);
    $staff = makeTenantScopedUser(['name' => 'Staff API', 'email' => uniqid().'@test.local', 'no_hp' => '081234560002', 'password' => 'password'], UserRole::Staff);
    Sanctum::actingAs($staff);
    $this->getJson('/api/v1/billing/subscription')->assertForbidden();
});

it('provisions trial or paid-pending tenants and enforces lifetime trial claims', function () {
    $admin = f10Admin();
    $trialPlan = f10Plan($admin, 'TRIAL-PROVISION', '99000.00', trial: true);
    [$firstTenant] = makeTenantUser();
    [$secondTenant] = makeTenantUser();
    Subscription::query()->whereIn('tenant_id', [$firstTenant->id, $secondTenant->id])->delete();
    $asOf = CarbonImmutable::parse('2026-08-22 09:00:00', 'Asia/Jakarta');

    $trial = app(CreateSubscriptionAction::class)->execute($admin, $firstTenant, $trialPlan, true, '0812-0000-9999', $asOf);
    expect($trial->status)->toBe(SubscriptionStatus::Trial)
        ->and($trial->ends_at->setTimezone('Asia/Jakarta')->toDateTimeString())->toBe('2026-09-05 09:00:00')
        ->and(TrialClaim::query()->count())->toBe(1)
        ->and(fn () => app(CreateSubscriptionAction::class)->execute($admin, $secondTenant, $trialPlan, true, '081200009999', $asOf))->toThrow(ConflictHttpException::class);

    $paidPlan = f10Plan($admin, 'PAID-PROVISION', '175000.00');
    $provisioned = app(CreateTenantAction::class)->execute(
        $admin,
        'F10 Paid Pending Store',
        'F10 Paid Owner',
        uniqid('paid-owner-', true).'@example.test',
        '081299990001',
        'owner-password-123',
        $paidPlan,
        false,
    );
    expect($provisioned['subscription']->status)->toBe(SubscriptionStatus::Suspended)
        ->and($provisioned['invoice']?->status)->toBe(InvoiceStatus::Open)
        ->and($provisioned['invoice']?->amount)->toBe('175000.00')
        ->and(fn () => app(CreateTenantAction::class)->execute(
            $admin,
            'Invalid Password Store',
            'Invalid Owner',
            uniqid('invalid-owner-', true).'@example.test',
            '081299990002',
            'short',
            $paidPlan,
            false,
        ))->toThrow(ValidationException::class);
});

it('keeps referenced plans immutable and permits only one open invoice', function () {
    [$tenant] = makeTenantUser();
    $admin = f10Admin();
    $plan = f10Plan($admin, 'IMMUTABLE-PLAN', '210000.00');
    $subscription = Subscription::query()->where('tenant_id', $tenant->id)->firstOrFail();
    $subscription->forceFill(['plan_id' => $plan->id])->save();

    expect(function () use ($plan): void {
        $plan->name = 'Mutation yang dilarang';
        $plan->save();
    })->toThrow(LogicException::class);

    $invoice = app(GenerateInvoiceAction::class)->execute($admin, $subscription, $plan);
    expect($invoice->amount)->toBe('210000.00')
        ->and(fn () => app(GenerateInvoiceAction::class)->execute($admin, $subscription, $plan))->toThrow(ConflictHttpException::class);
});

it('rejects illegal terminal transitions and every mutation during impersonation', function () {
    [$tenant, $owner] = makeTenantUser();
    $admin = f10Admin();
    $subscription = Subscription::query()->where('tenant_id', $tenant->id)->firstOrFail();
    $subscription->forceFill(['status' => SubscriptionStatus::Expired])->save();
    expect(fn () => app(TransitionSubscriptionAction::class)->execute($subscription, SubscriptionStatus::Active, $admin))->toThrow(ConflictHttpException::class);

    $impersonation = ImpersonationSession::query()->create([
        'admin_id' => $admin->id,
        'tenant_id' => $tenant->id,
        'target_user_id' => $owner->id,
        'reason' => 'Pemeriksaan dukungan sintetis read-only.',
        'session_fingerprint_hash' => hash('sha256', 'synthetic-browser-session'),
        'started_at' => now(),
        'expires_at' => now()->addMinutes(30),
    ])->load('admin');
    ImpersonationContext::set($impersonation);
    try {
        expect(fn () => f10Plan($admin, 'IMPERSONATION-MUTATION'))->toThrow(AuthorizationException::class)
            ->and(fn () => app(RequestTenantDeletionAction::class)->execute($owner, 'Penghapusan melalui impersonation dilarang.'))->toThrow(AuthorizationException::class);
    } finally {
        ImpersonationContext::clear();
    }

    $impersonation->forceFill(['expires_at' => now()->subMinute()])->save();
    expect(app(ExpireImpersonationSessionsAction::class)->execute())->toBe(1)
        ->and($impersonation->fresh()->end_reason)->toBe(ImpersonationEndReason::Expired);

    $billingRoute = collect(app('router')->getRoutes()->getRoutes())->first(fn ($route): bool => $route->uri() === 'api/v1/tenant/deletion-request' && in_array('POST', $route->methods(), true));
    expect($billingRoute)->not->toBeNull()
        ->and($billingRoute->gatherMiddleware())->toContain(ResolveImpersonation::class);
});

it('restores the prior operational state after approval cancellation without restoring access versions', function () {
    [$tenant, $owner] = makeTenantUser();
    $admin = f10Admin();
    $deletion = app(RequestTenantDeletionAction::class)->execute($owner, 'Tenant sintetis dibatalkan sebelum masuk antrean purge.');
    app(ApproveTenantDeletionAction::class)->execute($admin, $deletion);
    $versionAfterApproval = $owner->fresh()->auth_version;
    $cancelled = app(CancelTenantDeletionAction::class)->execute($admin, $deletion);

    expect($cancelled->status)->toBe(TenantDeletionStatus::Cancelled)
        ->and($tenant->fresh()->canOperate())->toBeTrue()
        ->and($owner->fresh()->auth_version)->toBe($versionAfterApproval)
        ->and($owner->tokens()->count())->toBe(0);
});

it('renders only the minimum Support projection without MRR amounts or payment references', function () {
    [$tenant] = makeTenantUser();
    $superAdmin = f10Admin();
    $support = app(CreateSupportAction::class)->execute($superAdmin, 'Support Projection', uniqid('support-', true).'@example.test', 'support-password-123');
    $support->forceFill([
        'two_factor_secret' => app(Google2FA::class)->generateSecretKey(32),
        'two_factor_confirmed_at' => now(),
    ])->save();
    $plan = f10Plan($superAdmin, 'SUPPORT-PROJECTION', '987654.00');
    $subscription = Subscription::query()->where('tenant_id', $tenant->id)->firstOrFail();
    $invoice = app(GenerateInvoiceAction::class)->execute($superAdmin, $subscription, $plan);
    app(RecordManualPaymentAction::class)->execute($superAdmin, $invoice, 'REFERENCE-MUST-NOT-LEAK');

    $this->actingAs($support, 'admin')->withSession([
        EnsureAdminAccess::VERSION_KEY => $support->auth_version,
        EnsureAdminAccess::MFA_KEY => true,
    ]);
    $this->get('/admin')->assertOk()->assertDontSee('MRR Aktif');
    $this->get('/admin/invoices')->assertOk()
        ->assertSee($invoice->invoice_number)
        ->assertDontSee('987654')
        ->assertDontSee('REFERENCE-MUST-NOT-LEAK');
    $this->get('/admin/plans')->assertOk()->assertDontSee('987654');
    $this->get('/admin/billing-payments')->assertForbidden();
});

it('keeps Owner billing and deletion projections tenant-scoped', function () {
    [$firstTenant, $firstOwner] = makeTenantUser();
    [$secondTenant, $secondOwner] = makeTenantUser();
    $admin = f10Admin();
    $plan = f10Plan($admin, 'TENANT-SCOPED-BILLING', '333000.00');
    $foreignSubscription = Subscription::query()->where('tenant_id', $secondTenant->id)->firstOrFail();
    $foreignInvoice = app(GenerateInvoiceAction::class)->execute($admin, $foreignSubscription, $plan);
    $foreignDeletion = app(RequestTenantDeletionAction::class)->execute($secondOwner, 'Alasan penghapusan tenant kedua yang tidak boleh bocor.');

    $this->actingAs($firstOwner, 'web')->withSession([
        EnsureTenantUserActive::SESSION_KEY => $firstOwner->auth_version,
    ])->get('/app/billing')->assertOk()
        ->assertDontSee($foreignInvoice->invoice_number)
        ->assertDontSee($foreignDeletion->reason)
        ->assertSee($firstTenant->currentSubscription->plan->name);
});
