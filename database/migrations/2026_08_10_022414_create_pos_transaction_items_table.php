<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_transaction_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('pos_transaction_id')->constrained('pos_transactions')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->unsignedInteger('qty');
            $table->unsignedInteger('returned_qty')->default(0);
            $table->decimal('harga_saat_transaksi', 15, 2);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('subtotal_amount', 15, 2);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tenant_id', 'pos_transaction_id']);
        });

        DB::statement('ALTER TABLE pos_transaction_items ADD CONSTRAINT chk_pos_items_qty CHECK (qty > 0)');
        DB::statement('ALTER TABLE pos_transaction_items ADD CONSTRAINT chk_pos_items_return CHECK (returned_qty <= qty)');
        DB::statement('ALTER TABLE pos_transaction_items ADD CONSTRAINT chk_pos_items_amount CHECK (harga_saat_transaksi >= 0 AND discount_amount >= 0 AND subtotal_amount >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_transaction_items');
    }
};
