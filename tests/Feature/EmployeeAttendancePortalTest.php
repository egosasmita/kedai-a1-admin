<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmployeeAttendancePortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_view_dashboard_check_in_check_out_and_own_history(): void
    {
        Storage::fake('local');
        $employee = User::factory()->create([
            'name' => 'Ayu Lestari',
            'is_admin' => false,
        ]);
        DB::table('employee_profiles')->insert([
            'user_id' => $employee->id,
            'employee_number' => 'A1-007',
            'position' => 'Barista',
            'employment_status' => 'active',
            'base_salary' => 3500000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $shiftId = DB::table('shifts')->insertGetId([
            'name' => 'Shift Pagi',
            'starts_at' => now()->subHour()->format('H:i:s'),
            'ends_at' => now()->addHours(6)->format('H:i:s'),
            'is_active' => true,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($employee)
            ->get(route('employee.dashboard'))
            ->assertOk()
            ->assertSee('Ayu Lestari')
            ->assertSee('Belum Absen')
            ->assertSee('Absen Masuk')
            ->assertSee('Keluar dari akun karyawan');
        $this->get(route('dashboard'))->assertRedirect(route('employee.dashboard'));

        $this->get(route('employee.attendance.check-in'))
            ->assertOk()
            ->assertSee('Aktifkan Kamera')
            ->assertSee('Ambil Lokasi')
            ->assertSee('Simpan Absen Masuk');

        $this->post(route('employee.attendance.check-in.store'), [
            'photo' => $this->fakeJpeg('check-in.jpg'),
            'latitude' => '-7.8161200',
            'longitude' => '112.0115000',
            'location_accuracy' => '12.5',
            'shift_id' => $shiftId,
        ])->assertRedirect(route('employee.dashboard'));

        $attendance = DB::table('attendances')->where('user_id', $employee->id)->first();
        $this->assertNotNull($attendance);
        $this->assertSame((string) $shiftId, (string) $attendance->shift_id);
        $this->assertEqualsWithDelta(-7.81612, (float) $attendance->check_in_latitude, 0.0000001);
        $this->assertEqualsWithDelta(112.0115, (float) $attendance->check_in_longitude, 0.0000001);
        Storage::disk('local')->assertExists($attendance->check_in_photo_path);

        $this->get(route('employee.dashboard'))
            ->assertOk()
            ->assertSee('Absen masuk tercatat');
        $this->get(route('employee.attendance.check-out'))
            ->assertOk()
            ->assertSee('Simpan Absen Pulang');

        $this->post(route('employee.attendance.check-out.store'), [
            'photo' => $this->fakeJpeg('check-out.jpg'),
            'latitude' => '-7.8161200',
            'longitude' => '112.0115000',
            'location_accuracy' => '15',
        ])->assertRedirect(route('employee.dashboard'));

        $attendance = DB::table('attendances')->where('user_id', $employee->id)->first();
        $this->assertNotNull($attendance->check_out_at);
        $this->assertEqualsWithDelta(-7.81612, (float) $attendance->check_out_latitude, 0.0000001);
        Storage::disk('local')->assertExists($attendance->check_out_photo_path);

        $this->get(route('employee.attendance.history'))
            ->assertOk()
            ->assertSee('Riwayat Absensi')
            ->assertSee('Shift Pagi')
            ->assertSee('✓ Masuk')
            ->assertSee('✓ Pulang');
    }

    public function test_attendance_proof_is_required_and_employees_cannot_access_admin_pages(): void
    {
        $employee = User::factory()->create(['is_admin' => false]);
        $this->actingAs($employee)
            ->from(route('employee.attendance.check-in'))
            ->post(route('employee.attendance.check-in.store'), [
                'latitude' => '100',
                'longitude' => '300',
            ])
            ->assertSessionHasErrors(['photo', 'latitude', 'longitude', 'shift_id']);

        $this->assertDatabaseCount('attendances', 0);
        $this->get(route('admin.stock.index'))->assertForbidden();
        $this->get(route('admin.payroll.index'))->assertForbidden();
    }

    public function test_admin_cannot_open_employee_portal_and_attendance_is_private_to_each_employee(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)
            ->get(route('employee.dashboard'))
            ->assertForbidden();

        $employee = User::factory()->create(['is_admin' => false]);
        $otherEmployee = User::factory()->create(['is_admin' => false]);
        DB::table('attendances')->insert([
            'user_id' => $otherEmployee->id,
            'attendance_date' => today()->toDateString(),
            'check_in_at' => now(),
            'status' => 'present',
            'late_minutes' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($employee)
            ->get(route('employee.attendance.history'))
            ->assertOk()
            ->assertSee('Belum ada riwayat absensi')
            ->assertDontSee($otherEmployee->name);
    }

    public function test_employee_cannot_check_out_before_check_in_or_twice(): void
    {
        $employee = User::factory()->create(['is_admin' => false]);
        $this->actingAs($employee)
            ->get(route('employee.attendance.check-out'))
            ->assertRedirect(route('employee.attendance.check-in'));

        DB::table('attendances')->insert([
            'user_id' => $employee->id,
            'attendance_date' => today()->toDateString(),
            'check_in_at' => now()->subHours(8),
            'check_out_at' => now(),
            'status' => 'present',
            'late_minutes' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get(route('employee.attendance.check-out'))
            ->assertRedirect(route('employee.dashboard'));
    }

    private function fakeJpeg(string $name): UploadedFile
    {
        $content = base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEAAkGBxAQEBAQEA8QDw8PDw8PDw8PDw8PDw8PFREWFhURFRUYHSggGBolGxUVITEhJSkrLi4uFx8zODMsNygtLisBCgoKDg0OGxAQGy0mICYtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLf/AABEIAAEAAQMBIgACEQEDEQH/xAAXAAEBAQEAAAAAAAAAAAAAAAAABQID/8QAFxEBAQEBAAAAAAAAAAAAAAAAAAERAv/aAAwDAQACEAMQAAAB6gD/xAAZEAEBAAMBAAAAAAAAAAAAAAABAgMABCH/2gAIAQEAAT8h0lZP/8QAFxEBAQEBAAAAAAAAAAAAAAAAAQARIf/aAAgBAgEBPwCq/8QAFxEBAQEBAAAAAAAAAAAAAAAAAQARIf/aAAgBAwEBPwCq/9k=', true);

        return UploadedFile::fake()->createWithContent($name, $content);
    }
}
