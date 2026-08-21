<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE items MODIFY movement_class ENUM('unclassified','fast','normal','slow','dead') NOT NULL DEFAULT 'unclassified'");

        Schema::table('items', function (Blueprint $table): void {
            $table->timestamp('analytics_calculated_at')->nullable()->after('movement_class');
            $table->index(
                ['tenant_id', 'is_active', 'movement_class'],
                'idx_items_tenant_active_movement_class',
            );
        });

        Schema::table('item_stock_movements', function (Blueprint $table): void {
            $table->index(
                ['tenant_id', 'item_id', 'movement_type', 'created_at'],
                'idx_movements_tenant_item_type_created',
            );
        });

        DB::table('items')->update([
            'movement_class' => 'unclassified',
            'analytics_calculated_at' => null,
        ]);
        DB::table('items')->where('threshold_mode', 'auto_velocity')->update([
            'threshold_mode' => 'manual',
        ]);
    }

    public function down(): void
    {
        DB::table('items')->where('movement_class', 'unclassified')->update([
            'movement_class' => 'normal',
        ]);

        Schema::table('item_stock_movements', function (Blueprint $table): void {
            $table->dropIndex('idx_movements_tenant_item_type_created');
        });

        Schema::table('items', function (Blueprint $table): void {
            $table->dropIndex('idx_items_tenant_active_movement_class');
            $table->dropColumn('analytics_calculated_at');
        });

        DB::statement("ALTER TABLE items MODIFY movement_class ENUM('fast','normal','slow','dead') NOT NULL DEFAULT 'normal'");
    }
};
