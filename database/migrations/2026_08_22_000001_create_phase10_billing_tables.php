<?php

use App\Enums\BillingInterval;
use App\Enums\BillingPaymentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->enum('billing_interval', array_column(BillingInterval::cases(), 'value'));
            $table->decimal('price', 15, 2);
            $table->boolean('is_trial')->default(false);
            $table->unsignedSmallInteger('trial_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_internal')->default(false);
            $table->timestamps();
        });
        DB::statement('ALTER TABLE plans ADD CONSTRAINT chk_plans_price CHECK (price >= 0)');
        DB::statement('ALTER TABLE plans ADD CONSTRAINT chk_plans_trial CHECK ((is_trial = 1 AND trial_days IS NOT NULL AND trial_days > 0) OR (is_trial = 0 AND trial_days IS NULL))');

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->enum('status', array_column(SubscriptionStatus::cases(), 'value'));
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedTinyInteger('current_slot')->nullable()
                ->storedAs("IF(status = 'expired', NULL, 1)");
            $table->timestamps();
            $table->unique(['tenant_id', 'current_slot'], 'subscriptions_one_current_unique');
            $table->index(['tenant_id', 'status', 'ends_at'], 'subscriptions_sweep_index');
        });
        DB::statement('ALTER TABLE subscriptions ADD CONSTRAINT chk_subscriptions_period CHECK (ends_at > starts_at)');

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('target_plan_id')->constrained('plans')->restrictOnDelete();
            $table->string('invoice_number', 64)->unique();
            $table->decimal('amount', 15, 2);
            $table->dateTime('due_at');
            $table->dateTime('paid_at')->nullable();
            $table->enum('status', array_column(InvoiceStatus::cases(), 'value'))->default(InvoiceStatus::Open->value);
            $table->unsignedTinyInteger('open_slot')->nullable()
                ->storedAs("IF(status = 'open', 1, NULL)");
            $table->timestamps();
            $table->unique(['subscription_id', 'open_slot'], 'invoices_one_open_unique');
            $table->index(['tenant_id', 'status', 'due_at'], 'invoices_status_index');
        });
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT chk_invoices_amount CHECK (amount >= 0)');

        Schema::create('billing_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->enum('status', array_column(BillingPaymentStatus::cases(), 'value'))->default(BillingPaymentStatus::Pending->value);
            $table->string('provider', 40)->default('manual');
            $table->string('provider_reference')->nullable();
            $table->foreignId('recorded_by_admin_id')->constrained('admins')->restrictOnDelete();
            $table->foreignId('verified_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->dateTime('verified_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
            $table->index(['invoice_id', 'status', 'id'], 'billing_payments_invoice_index');
        });
        DB::statement('ALTER TABLE billing_payments ADD CONSTRAINT chk_billing_payments_amount CHECK (amount >= 0)');

        Schema::create('subscription_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 64);
            $table->enum('from_status', array_column(SubscriptionStatus::cases(), 'value'))->nullable();
            $table->enum('to_status', array_column(SubscriptionStatus::cases(), 'value'))->nullable();
            $table->enum('actor_type', ['user', 'admin', 'system']);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['subscription_id', 'created_at', 'id'], 'subscription_events_timeline_index');
        });

        Schema::create('trial_claims', function (Blueprint $table): void {
            $table->id();
            $table->char('phone_hash', 64)->unique();
            $table->timestamp('created_at')->useCurrent();
        });

        $legacyPlanId = DB::table('plans')->insertGetId([
            'code' => 'LEGACY-F0-F9',
            'name' => 'Legacy F0-F9',
            'billing_interval' => BillingInterval::Monthly->value,
            'price' => '0.00',
            'is_trial' => false,
            'trial_days' => null,
            'is_active' => false,
            'is_internal' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('tenants')->orderBy('id')->each(function (object $tenant) use ($legacyPlanId): void {
            DB::table('subscriptions')->insert([
                'tenant_id' => $tenant->id,
                'plan_id' => $legacyPlanId,
                'status' => SubscriptionStatus::Active->value,
                'starts_at' => $tenant->created_at ?? now(),
                'ends_at' => '9999-12-31 16:59:59',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trial_claims');
        Schema::dropIfExists('subscription_events');
        Schema::dropIfExists('billing_payments');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
    }
};
