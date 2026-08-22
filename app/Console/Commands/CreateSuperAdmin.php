<?php

namespace App\Console\Commands;

use App\Enums\AdminRole;
use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateSuperAdmin extends Command
{
    protected $signature = 'admin:create {--name=} {--email=}';

    protected $description = 'Create the initial platform super-admin';

    public function handle(): int
    {
        $data = [
            'name' => $this->option('name') ?: $this->ask('Name'),
            'email' => $this->option('email') ?: $this->ask('Email'),
            'password' => $this->secret('Password (minimum 12 characters)'),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:admins,email'],
            'password' => ['required', 'string', 'min:12'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $admin = new Admin($validator->validated());
        $admin->forceFill(['role' => AdminRole::SuperAdmin, 'is_active' => true, 'auth_version' => 1]);
        $admin->save();

        $this->info('Super-admin created.');

        return self::SUCCESS;
    }
}
