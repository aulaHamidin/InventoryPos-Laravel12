<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->restrictOnDelete();
            $table->enum('movement_type', [
                'stock_in', 'stock_out', 'sale', 'customer_return',
                'supplier_return', 'damaged', 'adjustment', 'opname_adjustment',
            ]);
            $table->unsignedInteger('qty');
            $table->enum('direction', ['in', 'out']);
            $table->decimal('harga_satuan', 15, 2)->nullable();
            $table->text('note')->nullable();
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'item_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        DB::statement('ALTER TABLE item_stock_movements ADD CONSTRAINT chk_movements_qty CHECK (qty > 0)');
        DB::statement('ALTER TABLE item_stock_movements ADD CONSTRAINT chk_movements_harga CHECK (harga_satuan IS NULL OR harga_satuan >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('item_stock_movements');
    }
};
