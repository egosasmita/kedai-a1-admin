<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_categories_table_is_skipped_without_losing_data(): void
    {
        DB::table('categories')->insert([
            'name' => 'Kopi',
            'description' => 'Menu kopi',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_10_03_121528_create_categories_table.php');
        $migration->up();

        $this->assertDatabaseHas('categories', [
            'name' => 'Kopi',
            'description' => 'Menu kopi',
        ]);
    }

    public function test_menu_table_has_tag_and_discount_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('menus', [
            'name',
            'category_id',
            'description',
            'price',
            'image_url',
            'is_available',
            'tag',
            'discount_price',
            'discount_end_time',
        ]));
    }

    public function test_operational_tables_have_expected_columns(): void
    {
        $expectedColumns = [
            'employee_profiles' => ['user_id', 'employee_number', 'position', 'shift_id', 'hired_at', 'base_salary', 'employment_status'],
            'shifts' => ['name', 'starts_at', 'ends_at', 'is_active'],
            'attendances' => ['user_id', 'shift_id', 'attendance_date', 'check_in_at', 'check_out_at', 'status', 'late_minutes'],
            'stock_items' => ['sku', 'name', 'unit', 'quantity', 'minimum_quantity', 'unit_cost', 'is_active'],
            'stock_transactions' => ['stock_item_id', 'recorded_by', 'type', 'quantity', 'quantity_before', 'quantity_after', 'occurred_at'],
            'menu_ingredients' => ['menu_id', 'stock_item_id', 'quantity_per_serving'],
            'payroll_periods' => ['name', 'starts_on', 'ends_on', 'status', 'processed_by'],
            'payrolls' => ['payroll_period_id', 'user_id', 'attendance_days', 'overtime_hours', 'base_salary', 'deductions', 'net_pay', 'status'],
            'payroll_items' => ['payroll_id', 'type', 'label', 'amount'],
            'payroll_payments' => ['payroll_id', 'processed_by', 'amount', 'method', 'paid_at'],
        ];

        foreach ($expectedColumns as $table => $columns) {
            $this->assertTrue(Schema::hasColumns($table, $columns), "Missing expected columns in {$table}.");
        }
    }
}
