<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopping_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('shopping_list_id')->constrained('shopping_lists')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->restrictOnDelete();
            $table->unsignedInteger('qty_disarankan');
            $table->unsignedInteger('qty_dibeli')->nullable();
            $table->unsignedInteger('qty_received')->default(0);
            $table->boolean('is_checked')->default(true);
            $table->timestamps();
            $table->unique(['shopping_list_id', 'item_id']);
            $table->index(['tenant_id', 'shopping_list_id', 'item_id']);
        });

        DB::statement('ALTER TABLE shopping_list_items ADD CONSTRAINT chk_shopping_qty_suggested CHECK (qty_disarankan > 0)');
        DB::statement('ALTER TABLE shopping_list_items ADD CONSTRAINT chk_shopping_qty_bought CHECK (qty_dibeli IS NULL OR qty_dibeli > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('shopping_list_items');
    }
};
