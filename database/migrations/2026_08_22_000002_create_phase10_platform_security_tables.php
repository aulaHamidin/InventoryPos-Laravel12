<?php

use App\Enums\ImpersonationEndReason;
use App\Enums\OperationalStatus;
use App\Enums\TenantDeletionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('role');
            $table->unsignedBigInteger('auth_version')->default(1)->after('is_active');
            $table->json('two_factor_recovery_code_hashes')->nullable()->after('two_factor_confirmed_at');
            $table->unsignedBigInteger('two_factor_last_used_step')->nullable()->after('two_factor_recovery_code_hashes');
            $table->index(['role', 'is_active', 'id'], 'admins_role_active_index');
        });
        DB::table('admins')->update([
            'is_active' => true,
            'auth_version' => 1,
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_code_hashes' => null,
            'two_factor_last_used_step' => null,
        ]);

        Schema::create('tenant_deletion_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->enum('status', array_column(TenantDeletionStatus::cases(), 'value'))->default(TenantDeletionStatus::Requested->value);
            $table->text('reason');
            $table->text('review_reason')->nullable();
            $table->enum('previous_operational_status', array_column(OperationalStatus::cases(), 'value'))->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->dateTime('purge_after')->nullable();
            $table->dateTime('queued_at')->nullable();
            $table->dateTime('purged_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'purge_after', 'id'], 'tenant_deletion_due_index');
            $table->index(['tenant_id', 'status', 'id'], 'tenant_deletion_tenant_index');
        });

        Schema::create('impersonation_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('target_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 1000);
            $table->char('session_fingerprint_hash', 64);
            $table->dateTime('started_at');
            $table->dateTime('expires_at');
            $table->dateTime('ended_at')->nullable();
            $table->enum('end_reason', array_column(ImpersonationEndReason::cases(), 'value'))->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['admin_id', 'ended_at', 'expires_at', 'id'], 'impersonation_admin_active_index');
            $table->index(['tenant_id', 'ended_at', 'expires_at', 'id'], 'impersonation_tenant_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impersonation_sessions');
        Schema::dropIfExists('tenant_deletion_requests');

        Schema::table('admins', function (Blueprint $table): void {
            $table->dropIndex('admins_role_active_index');
            $table->dropColumn([
                'is_active', 'auth_version', 'two_factor_recovery_code_hashes',
                'two_factor_last_used_step',
            ]);
        });
    }
};
