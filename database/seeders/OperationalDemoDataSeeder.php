<?php

namespace Database\Seeders;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OperationalDemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->firstOrCreate(
            'settings',
            ['shop_name' => 'Kedai A1'],
            [
                'tagline' => 'Kopi dan cerita baik setiap hari.',
                'address' => 'Nganjuk, Jawa Timur',
                'phone' => '081234567890',
                'instagram' => '@kedaia1',
                'open_hour' => '08:00',
                'close_hour' => '22:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $employee = User::query()
            ->where('email', config('admin.employee.email'))
            ->where('is_admin', false)
            ->first();

        if ($employee === null) {
            throw new RuntimeException('Run EmployeeUserSeeder before OperationalDemoDataSeeder.');
        }

        $admin = User::query()->where('is_admin', true)->first();

        $this->firstOrCreate(
            'employee_profiles',
            ['user_id' => $employee->id],
            [
                'employee_number' => 'A1-DUMMY-001',
                'position' => 'Barista',
                'hired_at' => today()->subYear()->toDateString(),
                'base_salary' => 3500000,
                'employment_status' => 'active',
                'notes' => 'Profil contoh untuk data awal.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $shifts = [];
        foreach ([
            ['name' => 'Shift Pagi', 'starts_at' => '07:00:00', 'ends_at' => '15:00:00', 'sort_order' => 1],
            ['name' => 'Shift Siang', 'starts_at' => '11:00:00', 'ends_at' => '19:00:00', 'sort_order' => 2],
            ['name' => 'Shift Sore', 'starts_at' => '15:00:00', 'ends_at' => '23:00:00', 'sort_order' => 3],
        ] as $shift) {
            $shifts[$shift['name']] = $this->firstOrCreate(
                'shifts',
                ['name' => $shift['name']],
                [...$shift, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            )->id;
        }

        DB::table('employee_profiles')
            ->where('user_id', $employee->id)
            ->whereNull('shift_id')
            ->update(['shift_id' => $shifts['Shift Pagi'], 'updated_at' => now()]);

        foreach ([
            ['sku' => 'DUMMY-BEAN-001', 'name' => 'Biji kopi house blend', 'unit' => 'kg', 'quantity' => 8, 'minimum_quantity' => 2, 'unit_cost' => 180000],
            ['sku' => 'DUMMY-MILK-001', 'name' => 'Susu segar', 'unit' => 'liter', 'quantity' => 12, 'minimum_quantity' => 4, 'unit_cost' => 22000],
            ['sku' => 'DUMMY-TEA-001', 'name' => 'Bubuk matcha', 'unit' => 'kg', 'quantity' => 1.5, 'minimum_quantity' => 0.5, 'unit_cost' => 250000],
            ['sku' => 'DUMMY-CUP-001', 'name' => 'Gelas take away', 'unit' => 'pcs', 'quantity' => 100, 'minimum_quantity' => 25, 'unit_cost' => 1200],
            ['sku' => 'DUMMY-SUGAR-001', 'name' => 'Gula aren', 'unit' => 'kg', 'quantity' => 0.5, 'minimum_quantity' => 1, 'unit_cost' => 35000],
            ['sku' => 'DUMMY-RICE-001', 'name' => 'Beras', 'unit' => 'kg', 'quantity' => 10, 'minimum_quantity' => 3, 'unit_cost' => 16000],
            ['sku' => 'DUMMY-KATSU-001', 'name' => 'Chicken katsu', 'unit' => 'pcs', 'quantity' => 30, 'minimum_quantity' => 8, 'unit_cost' => 7500],
            ['sku' => 'DUMMY-MENTAI-001', 'name' => 'Saus mentai', 'unit' => 'liter', 'quantity' => 2, 'minimum_quantity' => 0.5, 'unit_cost' => 85000],
            ['sku' => 'DUMMY-NOODLE-001', 'name' => 'Mi telur', 'unit' => 'pcs', 'quantity' => 40, 'minimum_quantity' => 10, 'unit_cost' => 2500],
            ['sku' => 'DUMMY-EGG-001', 'name' => 'Telur ayam', 'unit' => 'pcs', 'quantity' => 30, 'minimum_quantity' => 10, 'unit_cost' => 2500],
            ['sku' => 'DUMMY-OIL-001', 'name' => 'Minyak goreng', 'unit' => 'liter', 'quantity' => 5, 'minimum_quantity' => 2, 'unit_cost' => 18000],
            ['sku' => 'DUMMY-BANANA-001', 'name' => 'Pisang', 'unit' => 'kg', 'quantity' => 4, 'minimum_quantity' => 1, 'unit_cost' => 22000],
            ['sku' => 'DUMMY-CHOCOLATE-001', 'name' => 'Cokelat', 'unit' => 'kg', 'quantity' => 2, 'minimum_quantity' => 0.5, 'unit_cost' => 95000],
            ['sku' => 'DUMMY-CIRENG-001', 'name' => 'Cireng frozen', 'unit' => 'pcs', 'quantity' => 50, 'minimum_quantity' => 15, 'unit_cost' => 1000],
            ['sku' => 'DUMMY-FRIES-001', 'name' => 'Kentang frozen', 'unit' => 'kg', 'quantity' => 5, 'minimum_quantity' => 2, 'unit_cost' => 30000],
            ['sku' => 'DUMMY-WATER-001', 'name' => 'Air mineral botol', 'unit' => 'pcs', 'quantity' => 48, 'minimum_quantity' => 12, 'unit_cost' => 2500],
        ] as $item) {
            $stock = $this->firstOrCreate(
                'stock_items',
                ['sku' => $item['sku']],
                [
                    ...$item,
                    'is_active' => true,
                    'notes' => 'Data dummy awal.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
            $this->firstOrCreate(
                'stock_transactions',
                [
                    'stock_item_id' => $stock->id,
                    'reference' => 'DATA-DUMMY-STOK-AWAL-'.$item['sku'],
                ],
                [
                    'recorded_by' => $admin?->id,
                    'type' => 'in',
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'quantity_before' => 0,
                    'quantity_after' => $item['quantity'],
                    'notes' => 'Transaksi stok awal contoh.',
                    'occurred_at' => now()->subDays(2),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        $recipeRows = [];
        foreach ([
            'americano' => ['DUMMY-BEAN-001' => 0.018, 'DUMMY-CUP-001' => 1],
            'matcha-milk' => ['DUMMY-TEA-001' => 0.02, 'DUMMY-MILK-001' => 0.2, 'DUMMY-CUP-001' => 1],
            'hazelnut-latte' => ['DUMMY-BEAN-001' => 0.018, 'DUMMY-MILK-001' => 0.2, 'DUMMY-SUGAR-001' => 0.02],
            'nasi-katsu-mentai' => ['DUMMY-RICE-001' => 0.15, 'DUMMY-KATSU-001' => 1, 'DUMMY-MENTAI-001' => 0.03],
            'mie-goreng-spesial' => ['DUMMY-NOODLE-001' => 1, 'DUMMY-EGG-001' => 1, 'DUMMY-OIL-001' => 0.02],
            'pisang-choco' => ['DUMMY-BANANA-001' => 0.15, 'DUMMY-CHOCOLATE-001' => 0.03],
            'cireng' => ['DUMMY-CIRENG-001' => 5, 'DUMMY-OIL-001' => 0.02],
            'french-fries' => ['DUMMY-FRIES-001' => 0.15, 'DUMMY-OIL-001' => 0.02],
            'matcha' => ['DUMMY-TEA-001' => 0.02, 'DUMMY-CUP-001' => 1],
            'mineral' => ['DUMMY-WATER-001' => 1],
        ] as $menuSlug => $ingredients) {
            $menuId = DB::table('menus')->where('slug', $menuSlug)->value('id');

            if ($menuId === null) {
                throw new RuntimeException("Missing menu {$menuSlug}; run MenuSeeder first.");
            }

            foreach ($ingredients as $sku => $quantity) {
                $stockItemId = DB::table('stock_items')->where('sku', $sku)->value('id');

                if ($stockItemId === null) {
                    throw new RuntimeException("Missing stock item {$sku} while seeding menu ingredients.");
                }

                $recipeRows[] = [
                    'menu_id' => $menuId,
                    'stock_item_id' => $stockItemId,
                    'quantity_per_serving' => $quantity,
                    'notes' => 'Takaran contoh per porsi.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if ($recipeRows !== []) {
            DB::table('menu_ingredients')->insertOrIgnore($recipeRows);
        }

        $profile = DB::table('employee_profiles')->where('user_id', $employee->id)->first();
        $attendanceDates = $this->sampleAttendanceDates();

        foreach ($attendanceDates as $index => $date) {
            $lateMinutes = $index === 2 ? 12 : 0;
            $checkIn = $date->copy()->setTime($lateMinutes > 0 ? 7 : 6, $lateMinutes > 0 ? 12 : 55);

            DB::table('attendances')->insertOrIgnore([
                'user_id' => $employee->id,
                'shift_id' => $shifts['Shift Pagi'],
                'attendance_date' => $date->toDateString(),
                'check_in_at' => $checkIn,
                'check_out_at' => $date->copy()->setTime(15, 5),
                'status' => $lateMinutes > 0 ? 'late' : 'present',
                'late_minutes' => $lateMinutes,
                'notes' => 'Data dummy absensi.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $start = now()->startOfMonth();
        $end = now()->endOfMonth();
        $period = $this->firstOrCreate(
            'payroll_periods',
            ['starts_on' => $start->toDateString(), 'ends_on' => $end->toDateString()],
            [
                'name' => $start->locale('id')->translatedFormat('F Y'),
                'status' => 'draft',
                'processed_by' => $admin?->id,
                'notes' => 'Periode contoh untuk pengujian awal.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $attendanceDays = DB::table('attendances')
            ->where('user_id', $employee->id)
            ->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('status', ['present', 'late'])
            ->count();
        $lateCount = DB::table('attendances')
            ->where('user_id', $employee->id)
            ->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])
            ->where('status', 'late')
            ->count();
        $allowance = 150000;
        $bonus = 100000;
        $deduction = 50000;
        $payroll = $this->firstOrCreate(
            'payrolls',
            ['payroll_period_id' => $period->id, 'user_id' => $employee->id],
            [
                'attendance_days' => $attendanceDays,
                'late_count' => $lateCount,
                'overtime_hours' => 0,
                'base_salary' => $profile->base_salary,
                'overtime_amount' => 0,
                'allowances' => $allowance,
                'bonus' => $bonus,
                'deductions' => $deduction,
                'net_pay' => (float) $profile->base_salary + $allowance + $bonus - $deduction,
                'status' => 'draft',
                'notes' => 'Data dummy penggajian; belum dibayarkan.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        foreach ([
            ['type' => 'allowance', 'label' => 'Tunjangan transport', 'amount' => $allowance],
            ['type' => 'bonus', 'label' => 'Bonus kinerja', 'amount' => $bonus],
            ['type' => 'deduction', 'label' => 'Potongan keterlambatan', 'amount' => $deduction],
        ] as $item) {
            $this->firstOrCreate(
                'payroll_items',
                ['payroll_id' => $payroll->id, 'type' => $item['type'], 'label' => $item['label']],
                [...$item, 'notes' => 'Komponen contoh.', 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $values
     */
    private function firstOrCreate(string $table, array $attributes, array $values): object
    {
        $query = DB::table($table);
        $record = $query->where($attributes)->first();

        if ($record !== null) {
            return $record;
        }

        $id = $query->insertGetId([...$attributes, ...$values]);
        $record = $query->where('id', $id)->first();

        if ($record === null) {
            throw new RuntimeException("Unable to retrieve seeded {$table} record.");
        }

        return $record;
    }

    /**
     * @return list<Carbon>
     */
    private function sampleAttendanceDates(): array
    {
        $dates = [];
        $date = today()->startOfMonth();

        while ($date->lessThanOrEqualTo(today()) && count($dates) < 5) {
            if (! $date->isWeekend()) {
                $dates[] = $date->copy();
            }

            $date->addDay();
        }

        return $dates;
    }
}
