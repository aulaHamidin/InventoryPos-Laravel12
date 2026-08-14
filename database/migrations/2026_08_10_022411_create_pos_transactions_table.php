<?php

use App\Enums\PosTransactionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->string('invoice_number')->unique();
            $table->enum('status', array_column(PosTransactionStatus::cases(), 'value'))
                ->default(PosTransactionStatus::PendingPayment->value);
            $table->decimal('subtotal_amount', 15, 2);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);
            $table->string('idempotency_key');
            $table->char('request_hash', 64);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->index(['tenant_id', 'created_at']);
        });

        DB::statement('ALTER TABLE pos_transactions ADD CONSTRAINT chk_pos_subtotal CHECK (subtotal_amount >= 0)');
        DB::statement('ALTER TABLE pos_transactions ADD CONSTRAINT chk_pos_discount CHECK (discount_amount >= 0 AND discount_amount <= subtotal_amount)');
        DB::statement('ALTER TABLE pos_transactions ADD CONSTRAINT chk_pos_total CHECK (total_amount >= 0 AND total_amount = subtotal_amount - discount_amount)');
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_transactions');
    }
};
