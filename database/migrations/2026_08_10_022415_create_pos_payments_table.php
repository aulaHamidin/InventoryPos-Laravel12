<?php

use App\Enums\PosPaymentMethod;
use App\Enums\PosPaymentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('pos_transaction_id')->constrained('pos_transactions')->restrictOnDelete();
            $table->enum('method', array_column(PosPaymentMethod::cases(), 'value'));
            $table->decimal('amount', 15, 2);
            $table->enum('status', array_column(PosPaymentStatus::cases(), 'value'));
            $table->string('gateway_reference')->nullable()->unique();
            $table->decimal('refunded_amount', 15, 2)->default(0);
            $table->timestamp('refunded_at')->nullable();
            $table->foreignId('refunded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('idempotency_key')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'pos_transaction_id']);
            $table->unique(['tenant_id', 'idempotency_key']);
        });

        DB::statement('ALTER TABLE pos_payments ADD CONSTRAINT chk_pos_payments_amount CHECK (amount > 0)');
        DB::statement('ALTER TABLE pos_payments ADD CONSTRAINT chk_pos_payments_refund CHECK (refunded_amount >= 0 AND refunded_amount <= amount)');
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_payments');
    }
};
