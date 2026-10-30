<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Menu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_uses_live_menu_stock_attendance_activity_and_shift_data(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin Kedai',
            'is_admin' => true,
        ]);
        $employee = User::factory()->create([
            'name' => 'Ayu Lestari',
            'is_admin' => false,
        ]);
        $absentEmployee = User::factory()->create([
            'name' => 'Fajar Nugraha',
            'is_admin' => false,
        ]);
        DB::table('employee_profiles')->insert([
            'user_id' => $employee->id,
            'position' => 'Barista',
            'employment_status' => 'active',
            'base_salary' => 3500000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('employee_profiles')->insert([
            'user_id' => $absentEmployee->id,
            'position' => 'Kasir',
            'employment_status' => 'inactive',
            'base_salary' => 3000000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $category = Category::create(['name' => 'Kopi']);
        Menu::create([
            'category_id' => $category->id,
            'name' => 'Aren Latte',
            'slug' => 'aren-latte',
            'description' => 'Kopi susu aren.',
            'price' => 28000,
            'is_available' => true,
        ]);
        Menu::create([
            'category_id' => $category->id,
            'name' => 'Espresso',
            'slug' => 'espresso',
            'description' => 'Espresso.',
            'price' => 18000,
            'is_available' => false,
        ]);

        $stockItemId = DB::table('stock_items')->insertGetId([
            'name' => 'Susu segar',
            'unit' => 'liter',
            'quantity' => 2,
            'minimum_quantity' => 5,
            'unit_cost' => 18000,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('stock_transactions')->insert([
            'stock_item_id' => $stockItemId,
            'recorded_by' => $admin->id,
            'type' => 'in',
            'quantity' => 2,
            'quantity_before' => 0,
            'quantity_after' => 2,
            'occurred_at' => now()->subMinutes(5),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $shiftId = DB::table('shifts')->insertGetId([
            'name' => 'Shift Siang',
            'starts_at' => now()->subHour()->format('H:i:s'),
            'ends_at' => now()->addHour()->format('H:i:s'),
            'is_active' => true,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('attendances')->insert([
            'user_id' => $employee->id,
            'shift_id' => $shiftId,
            'attendance_date' => today()->toDateString(),
            'check_in_at' => now()->subMinutes(10),
            'status' => 'present',
            'late_minutes' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Total Menu')
            ->assertSee('1 tersedia')
            ->assertSee('Total Karyawan')
            ->assertSee('Stok Menipis')
            ->assertSee('Susu segar')
            ->assertSee('Shift Siang')
            ->assertSee('Ayu Lestari')
            ->assertSee('Stok masuk')
            ->assertSeeInOrder(['Absensi 7 hari', 'Kesehatan stok', 'Aktivitas terbaru', 'Aksi cepat']);
    }

    public function test_dashboard_handles_empty_operational_data_without_fabricating_activity(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Belum ada data bahan baku.')
            ->assertSee('Belum ada aktivitas absensi atau stok.')
            ->assertSee('Tidak ada shift aktif')
            ->assertSee('0 karyawan bertugas');
    }
}
