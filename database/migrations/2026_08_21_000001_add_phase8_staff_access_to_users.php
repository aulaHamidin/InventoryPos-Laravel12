<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('role');
            $table->unsignedBigInteger('auth_version')->default(1)->after('is_active');
            $table->index(
                ['tenant_id', 'role', 'is_active', 'id'],
                'users_tenant_role_active_id_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_tenant_role_active_id_idx');
            $table->dropColumn(['is_active', 'auth_version']);
        });
    }
};
