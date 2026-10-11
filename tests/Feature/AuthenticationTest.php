<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_log_in_and_log_out(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@kedaia1.id',
            'password' => 'a-secure-test-password',
            'is_admin' => true,
        ]);

        $this->post(route('login.store'), [
            'email' => $admin->email,
            'password' => 'a-secure-test-password',
            'remember' => '1',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($admin);
        $this->get(route('dashboard'))->assertOk()->assertSee('Pemilik Kedai');

        $this->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_invalid_password_is_rejected(): void
    {
        User::factory()->create([
            'email' => 'admin@kedaia1.id',
            'password' => 'a-secure-test-password',
            'is_admin' => true,
        ]);

        $this->from(route('login'))
            ->post(route('login.store'), [
                'email' => 'admin@kedaia1.id',
                'password' => 'incorrect-password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_employee_can_log_in_and_access_dashboard(): void
    {
        $employee = User::factory()->create([
            'email' => 'staff@kedaia1.id',
            'password' => 'a-secure-test-password',
            'is_admin' => false,
        ]);

        $this->post(route('login.store'), [
            'email' => $employee->email,
            'password' => 'a-secure-test-password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($employee);
        $this->get(route('dashboard'))
            ->assertRedirect(route('employee.dashboard'));
        $this->get(route('employee.dashboard'))
            ->assertOk()
            ->assertSee('Karyawan');

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_database_seeder_creates_admin_and_employee_with_default_password(): void
    {
        config([
            'admin.name' => 'Admin Kedai',
            'admin.email' => 'admin@kedaia1.id',
            'admin.password' => null,
            'admin.employee.name' => 'Karyawan Kedai',
            'admin.employee.email' => 'karyawan@kedaia1.id',
            'admin.employee.password' => null,
        ]);

        $this->seed();

        $admin = User::where('email', 'admin@kedaia1.id')->firstOrFail();
        $employee = User::where('email', 'karyawan@kedaia1.id')->firstOrFail();

        $this->assertTrue($admin->is_admin);
        $this->assertFalse($employee->is_admin);
        $this->assertTrue(password_verify('12345678', $admin->password));
        $this->assertTrue(password_verify('12345678', $employee->password));

        $this->post(route('login.store'), [
            'email' => $employee->email,
            'password' => '12345678',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($employee);
        $this->get(route('dashboard'))
            ->assertRedirect(route('employee.dashboard'));
        $this->get(route('employee.dashboard'))
            ->assertOk()
            ->assertSee('Karyawan');

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->post(route('login.store'), [
            'email' => $admin->email,
            'password' => '12345678',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($admin);
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Pemilik Kedai')
            ->assertSee('Keluar dari akun admin');
    }

    public function test_admin_seeder_updates_existing_account_to_configured_password(): void
    {
        config([
            'admin.name' => 'Admin Kedai',
            'admin.email' => 'admin@kedaia1.id',
            'admin.password' => 'a-strong-initial-password',
        ]);

        $this->seed(AdminUserSeeder::class);
        $admin = User::where('email', 'admin@kedaia1.id')->firstOrFail();

        config(['admin.password' => 'a-different-password-value']);
        $this->seed(AdminUserSeeder::class);

        $this->assertTrue($admin->fresh()->is_admin);
        $this->assertTrue(password_verify('a-different-password-value', $admin->fresh()->password));
    }
}
