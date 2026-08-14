<?php

use App\Enums\OperationalStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('nama_toko');
            $table->string('slug')->unique();
            $table->enum('operational_status', array_column(OperationalStatus::cases(), 'value'))
                ->default(OperationalStatus::Active->value);
            $table->boolean('allow_negative_stock')->default(false);
            $table->unsignedInteger('dead_stock_days')->default(90);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE tenants ADD CONSTRAINT chk_tenants_dead_stock_days CHECK (dead_stock_days >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
