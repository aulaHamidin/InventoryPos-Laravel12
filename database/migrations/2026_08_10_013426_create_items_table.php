<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('rack_id')->nullable()->constrained('racks')->nullOnDelete();
            $table->string('kode', 100);
            $table->string('barcode')->nullable();
            $table->string('nama');
            $table->string('satuan', 50);
            $table->decimal('harga_beli', 15, 2)->default(0);
            $table->decimal('average_cost', 15, 2)->default(0);
            $table->decimal('harga_jual', 15, 2)->default(0);
            $table->integer('stok_saat_ini')->default(0);
            $table->unsignedInteger('stok_minimal')->default(0);
            $table->enum('threshold_mode', ['manual', 'auto_velocity'])->default('manual');
            $table->unsignedInteger('lead_time_days')->default(0);
            $table->unsignedInteger('safety_stock_days')->default(0);
            $table->date('exp_date')->nullable();
            $table->enum('movement_class', ['fast', 'normal', 'slow', 'dead'])->default('normal');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'kode']);
            $table->unique(['tenant_id', 'barcode']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'category_id']);
            $table->index(['tenant_id', 'rack_id']);
        });

        DB::statement('ALTER TABLE items ADD CONSTRAINT chk_items_harga_beli CHECK (harga_beli >= 0)');
        DB::statement('ALTER TABLE items ADD CONSTRAINT chk_items_average_cost CHECK (average_cost >= 0)');
        DB::statement('ALTER TABLE items ADD CONSTRAINT chk_items_harga_jual CHECK (harga_jual >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
