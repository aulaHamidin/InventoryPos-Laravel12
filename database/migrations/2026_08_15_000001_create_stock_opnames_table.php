<?php

use App\Enums\StockOpnameScope;
use App\Enums\StockOpnameStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->enum('scope_type', array_column(StockOpnameScope::cases(), 'value'));
            $table->foreignId('rack_id')->nullable()->constrained('racks')->restrictOnDelete();
            $table->enum('status', array_column(StockOpnameStatus::cases(), 'value'))
                ->default(StockOpnameStatus::Draft->value);
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->index(['tenant_id', 'status', 'scope_type', 'rack_id'], 'stock_opnames_conflict_idx');
            $table->index(['tenant_id', 'started_at']);
        });

        DB::statement("ALTER TABLE stock_opnames ADD CONSTRAINT chk_stock_opnames_scope CHECK ((scope_type = 'partial' AND rack_id IS NOT NULL) OR (scope_type = 'full' AND rack_id IS NULL))");
        DB::statement("ALTER TABLE stock_opnames ADD CONSTRAINT chk_stock_opnames_status_time CHECK ((status = 'draft' AND completed_at IS NULL) OR (status = 'completed' AND completed_at IS NOT NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opnames');
    }
};
