<?php

use App\Enums\AdminRole;
use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

it('provisions a super-admin without a seeded default credential', function () {
    $this->artisan('admin:create', [
        '--name' => 'Initial Platform Admin',
        '--email' => 'initial-admin@example.test',
    ])
        ->expectsQuestion('Password (minimum 12 characters)', 'VerySecret123!')
        ->expectsOutput('Super-admin created.')
        ->assertSuccessful();

    $admin = Admin::where('email', 'initial-admin@example.test')->sole();

    expect($admin->role)->toBe(AdminRole::SuperAdmin)
        ->and(Hash::check('VerySecret123!', $admin->password))->toBeTrue();
});
