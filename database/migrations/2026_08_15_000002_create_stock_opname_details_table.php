<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_opname_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('stock_opname_id')->constrained('stock_opnames')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->integer('qty_sistem_at_count')->nullable();
            $table->integer('qty_fisik')->nullable();
            $table->timestamp('counted_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['stock_opname_id', 'item_id']);
            $table->index(['tenant_id', 'stock_opname_id', 'item_id'], 'stock_opname_details_lookup_idx');
        });

        DB::statement('ALTER TABLE stock_opname_details ADD CONSTRAINT chk_stock_opname_qty_fisik CHECK (qty_fisik IS NULL OR qty_fisik >= 0)');
        DB::statement('ALTER TABLE stock_opname_details ADD CONSTRAINT chk_stock_opname_count_fields CHECK ((qty_sistem_at_count IS NULL AND qty_fisik IS NULL AND counted_at IS NULL) OR (qty_sistem_at_count IS NOT NULL AND qty_fisik IS NOT NULL AND counted_at IS NOT NULL))');
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opname_details');
    }
};
