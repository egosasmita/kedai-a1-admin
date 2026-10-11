<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('admin.email');
        $password = config('admin.password') ?: '12345678';

        if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Set a valid ADMIN_EMAIL in your .env before seeding the admin account.');
        }

        if (! is_string($password)) {
            throw new RuntimeException('ADMIN_PASSWORD must be a string.');
        }

        $admin = User::firstOrNew(['email' => $email]);

        if ($admin->exists) {
            if (! $admin->is_admin) {
                throw new RuntimeException("The configured admin email {$email} already belongs to a non-admin user.");
            }
        }

        $admin->name = config('admin.name');
        $admin->password = $password;
        $admin->is_admin = true;
        $admin->save();
    }
}
