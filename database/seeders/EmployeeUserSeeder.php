<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class EmployeeUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('admin.employee.email');
        $password = config('admin.employee.password') ?: '12345678';

        if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Set a valid EMPLOYEE_EMAIL in your .env before seeding the employee account.');
        }

        if ($email === config('admin.email')) {
            throw new RuntimeException('EMPLOYEE_EMAIL must be different from ADMIN_EMAIL.');
        }

        if (! is_string($password)) {
            throw new RuntimeException('EMPLOYEE_PASSWORD must be a string.');
        }

        $employee = User::firstOrNew(['email' => $email]);

        if ($employee->exists) {
            if ($employee->is_admin) {
                throw new RuntimeException("The configured employee email {$email} already belongs to an admin user.");
            }
        }

        $employee->name = config('admin.employee.name');
        $employee->password = $password;
        $employee->is_admin = false;
        $employee->save();
    }
}
