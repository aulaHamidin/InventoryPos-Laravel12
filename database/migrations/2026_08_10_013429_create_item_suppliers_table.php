<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->string('supplier_sku', 100)->nullable();
            $table->decimal('harga_beli_terakhir', 15, 2)->nullable();
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->boolean('is_preferred')->default(false);
            $table->timestamps();
            $table->unique(['tenant_id', 'item_id', 'supplier_id']);
            $table->index(['tenant_id', 'item_id']);
        });

        DB::statement('ALTER TABLE item_suppliers ADD CONSTRAINT chk_item_suppliers_harga CHECK (harga_beli_terakhir IS NULL OR harga_beli_terakhir >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('item_suppliers');
    }
};
