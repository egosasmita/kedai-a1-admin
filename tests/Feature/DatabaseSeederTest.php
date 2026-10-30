<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_populates_all_modules_without_duplicates(): void
    {
        config([
            'admin.name' => 'Admin Kedai',
            'admin.email' => 'admin@kedaia1.test',
            'admin.password' => 'test-admin-password',
            'admin.employee.name' => 'Karyawan Kedai',
            'admin.employee.email' => 'employee@kedaia1.test',
            'admin.employee.password' => 'test-employee-password',
        ]);

        $this->seed();
        $this->seed();

        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('categories', 4);
        $this->assertDatabaseCount('menus', 10);
        $this->assertDatabaseCount('settings', 1);
        $this->assertDatabaseCount('employee_profiles', 1);
        $this->assertDatabaseCount('shifts', 3);
        $this->assertDatabaseCount('stock_items', 16);
        $this->assertDatabaseCount('stock_transactions', 16);
        $this->assertDatabaseCount('menu_ingredients', 23);
        $this->assertDatabaseCount('attendances', 5);
        $this->assertDatabaseCount('payroll_periods', 1);
        $this->assertDatabaseCount('payrolls', 1);
        $this->assertDatabaseCount('payroll_items', 3);
        $this->assertDatabaseCount('payroll_payments', 0);

        $this->assertDatabaseHas('employee_profiles', [
            'employee_number' => 'A1-DUMMY-001',
            'position' => 'Barista',
            'employment_status' => 'active',
        ]);
        $this->assertDatabaseHas('stock_items', [
            'sku' => 'DUMMY-SUGAR-001',
            'quantity' => 0.5,
            'minimum_quantity' => 1,
        ]);
        $this->assertDatabaseHas('menu_ingredients', [
            'quantity_per_serving' => 0.018,
            'notes' => 'Takaran contoh per porsi.',
        ]);
    }
}
