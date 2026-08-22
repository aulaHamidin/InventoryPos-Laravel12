<?php

namespace App\Console\Commands;

use App\Actions\Audit\RecordAuditAction;
use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class ResetAdminMfaCommand extends Command
{
    protected $signature = 'admin:mfa-reset {email?}';

    protected $description = 'Emergency MFA reset for a platform admin';

    public function handle(RecordAuditAction $audit): int
    {
        $email = $this->argument('email') ?: $this->ask('Admin email');
        $admin = Admin::query()->where('email', strtolower(trim((string) $email)))->first();
        if ($admin === null || ! $this->confirm("Reset MFA for {$admin->email}? Existing sessions will be revoked.")) {
            $this->warn('No changes made.');

            return self::FAILURE;
        }
        DB::transaction(function () use ($admin, $audit): void {
            $admin = Admin::query()->lockForUpdate()->findOrFail($admin->getKey());
            $admin->forceFill([
                'two_factor_secret' => null, 'two_factor_confirmed_at' => null,
                'two_factor_recovery_code_hashes' => null, 'two_factor_last_used_step' => null,
                'auth_version' => $admin->auth_version + 1,
            ])->save();
            $audit->execute('platform.admin_mfa_emergency_reset', null, $admin, newValues: [
                'admin_id' => $admin->getKey(), 'auth_version' => $admin->auth_version,
            ], global: true);
        });
        $this->info('MFA reset complete. No secret or recovery code was printed.');

        return self::SUCCESS;
    }
}
