<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OperationalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_operational_pages_render_for_admin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.stock.index'))
            ->assertOk()
            ->assertSee('Daftar bahan baku');

        $this->get(route('admin.employees.index'))
            ->assertOk()
            ->assertSee('Daftar karyawan');

        $this->get(route('admin.attendance.index'))
            ->assertOk()
            ->assertSee('Rekap harian');

        $this->get(route('admin.payroll.index'))
            ->assertOk()
            ->assertSee('Daftar penggajian');
    }

    public function test_payroll_page_renders_for_a_period_without_payroll_rows(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $startsOn = now()->startOfMonth()->toDateString();
        DB::table('payroll_periods')->insert([
            'name' => now()->locale('id')->translatedFormat('F Y'),
            'starts_on' => $startsOn,
            'ends_on' => now()->endOfMonth()->toDateString(),
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.payroll.index', ['month' => now()->format('Y-m')]))
            ->assertOk()
            ->assertSee('Belum ada perhitungan gaji')
            ->assertSee('Rp 0');
    }

    public function test_stock_item_and_transactions_keep_quantity_consistent(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)
            ->post(route('admin.stock.items.store'), [
                'name' => 'Susu segar',
                'unit' => 'liter',
                'quantity' => 5,
                'minimum_quantity' => 2,
                'unit_cost' => 18000,
            ])
            ->assertRedirect(route('admin.stock.index'));

        $item = DB::table('stock_items')->where('name', 'Susu segar')->first();
        $this->assertSame(5.0, (float) $item->quantity);
        $this->assertDatabaseHas('stock_transactions', [
            'stock_item_id' => $item->id,
            'type' => 'in',
            'quantity_after' => 5,
        ]);

        $this->post(route('admin.stock.transactions.store'), [
            'stock_item_id' => $item->id,
            'type' => 'out',
            'quantity' => 2,
        ])->assertRedirect(route('admin.stock.index'));

        $this->assertSame(3.0, (float) DB::table('stock_items')->where('id', $item->id)->value('quantity'));

        $this->post(route('admin.stock.transactions.store'), [
            'stock_item_id' => $item->id,
            'type' => 'adjustment',
            'quantity' => 0,
        ])->assertRedirect(route('admin.stock.index'));

        $this->assertSame(0.0, (float) DB::table('stock_items')->where('id', $item->id)->value('quantity'));

        $this->from(route('admin.stock.index'))
            ->post(route('admin.stock.transactions.store'), [
                'stock_item_id' => $item->id,
                'type' => 'out',
                'quantity' => 4,
            ])
            ->assertSessionHasErrors('quantity');

        $this->assertSame(0.0, (float) DB::table('stock_items')->where('id', $item->id)->value('quantity'));
    }

    public function test_admin_can_create_employee_account_and_profile(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)
            ->post(route('admin.employees.store'), [
                'name' => 'Ayu Lestari',
                'email' => 'ayu@example.test',
                'password' => 'KedaiA1Secure123',
                'password_confirmation' => 'KedaiA1Secure123',
                'employee_number' => 'A1-001',
                'position' => 'Barista',
                'hired_at' => today()->toDateString(),
                'base_salary' => 3500000,
            ])
            ->assertRedirect(route('admin.employees.index'));

        $employee = User::where('email', 'ayu@example.test')->firstOrFail();
        $this->assertFalse($employee->is_admin);
        $this->assertTrue(password_verify('KedaiA1Secure123', $employee->password));
        $this->assertDatabaseHas('employee_profiles', [
            'user_id' => $employee->id,
            'employee_number' => 'A1-001',
            'position' => 'Barista',
            'base_salary' => 3500000,
            'employment_status' => 'active',
        ]);
    }

    public function test_admin_can_record_check_in_and_check_out(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $employee = User::factory()->create(['is_admin' => false, 'name' => 'Fajar Nugraha']);
        DB::table('employee_profiles')->insert([
            'user_id' => $employee->id,
            'position' => 'Kasir',
            'employment_status' => 'active',
            'base_salary' => 3000000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $shiftId = DB::table('shifts')->insertGetId([
            'name' => 'Shift Hari Ini',
            'starts_at' => now()->subHour()->format('H:i:s'),
            'ends_at' => now()->addHours(4)->format('H:i:s'),
            'is_active' => true,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.attendance.check-in'), [
                'user_id' => $employee->id,
                'shift_id' => $shiftId,
            ])
            ->assertRedirect(route('admin.attendance.index'));

        $attendance = DB::table('attendances')->where('user_id', $employee->id)->first();
        $this->assertSame($shiftId, (int) $attendance->shift_id);
        $this->assertContains($attendance->status, ['present', 'late']);

        $this->post(route('admin.attendance.check-out'), ['user_id' => $employee->id])
            ->assertRedirect(route('admin.attendance.index'));

        $this->assertNotNull(DB::table('attendances')->where('id', $attendance->id)->value('check_out_at'));
    }

    public function test_admin_can_calculate_adjust_and_payroll_for_active_employee(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $employee = User::factory()->create(['is_admin' => false, 'name' => 'Nadia Putri']);
        DB::table('employee_profiles')->insert([
            'user_id' => $employee->id,
            'position' => 'Kasir',
            'employment_status' => 'active',
            'base_salary' => 3200000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('attendances')->insert([
            'user_id' => $employee->id,
            'attendance_date' => today()->toDateString(),
            'check_in_at' => now()->subHours(4),
            'status' => 'present',
            'late_minutes' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $month = today()->format('Y-m');

        $this->actingAs($admin)
            ->post(route('admin.payroll.process'), ['month' => $month])
            ->assertRedirect(route('admin.payroll.index', ['month' => $month]));

        $payroll = DB::table('payrolls')->where('user_id', $employee->id)->first();
        $this->assertNotNull($payroll);
        $this->assertSame(1, (int) $payroll->attendance_days);
        $this->assertSame(3200000.0, (float) $payroll->net_pay);

        $this->post(route('admin.payroll.items.store', $payroll->id), [
            'month' => $month,
            'type' => 'allowance',
            'label' => 'Tunjangan transport',
            'amount' => 150000,
        ])->assertRedirect(route('admin.payroll.index', ['month' => $month, 'payroll' => $payroll->id]));

        $payroll = DB::table('payrolls')->where('id', $payroll->id)->first();
        $this->assertSame(3350000.0, (float) $payroll->net_pay);

        $this->post(route('admin.payroll.pay', $payroll->id), [
            'method' => 'bank_transfer',
            'reference' => 'TRX-001',
        ])->assertRedirect(route('admin.payroll.index', ['month' => $month, 'payroll' => $payroll->id]));

        $this->assertDatabaseHas('payrolls', ['id' => $payroll->id, 'status' => 'paid']);
        $this->assertDatabaseHas('payroll_periods', ['starts_on' => $month.'-01', 'status' => 'paid']);
        $this->assertDatabaseHas('payroll_payments', [
            'payroll_id' => $payroll->id,
            'amount' => 3350000,
            'reference' => 'TRX-001',
        ]);
    }
}
