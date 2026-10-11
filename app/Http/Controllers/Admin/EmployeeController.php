<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'resigned'])],
        ]);

        $employees = DB::table('users')
            ->leftJoin('employee_profiles', 'employee_profiles.user_id', '=', 'users.id')
            ->where('users.is_admin', false)
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('users.name', 'like', "%{$search}%")
                        ->orWhere('users.email', 'like', "%{$search}%")
                        ->orWhere('employee_profiles.employee_number', 'like', "%{$search}%")
                        ->orWhere('employee_profiles.position', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, function (Builder $query, string $status): void {
                if ($status === 'active') {
                    $query->where(function (Builder $query): void {
                        $query->whereNull('employee_profiles.id')
                            ->orWhere('employee_profiles.employment_status', 'active');
                    });
                } else {
                    $query->where('employee_profiles.employment_status', $status);
                }
            })
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.created_at as account_created_at',
                'employee_profiles.employee_number',
                'employee_profiles.position',
                'employee_profiles.hired_at',
                'employee_profiles.base_salary',
                'employee_profiles.employment_status',
            ])
            ->orderBy('users.name')
            ->paginate(8)
            ->withQueryString();

        $counts = DB::table('users')
            ->leftJoin('employee_profiles', 'employee_profiles.user_id', '=', 'users.id')
            ->where('users.is_admin', false)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN employee_profiles.id IS NULL OR employment_status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN employment_status = 'inactive' THEN 1 ELSE 0 END) as inactive,
                SUM(CASE WHEN employment_status = 'resigned' THEN 1 ELSE 0 END) as resigned
            ")
            ->first();

        return view('admin.employees.index', [
            'employees' => $employees,
            'counts' => $counts,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
            'employee_number' => ['nullable', 'string', 'max:30', 'unique:employee_profiles,employee_number'],
            'position' => ['required', 'string', 'max:100'],
            'hired_at' => ['nullable', 'date', 'before_or_equal:today'],
            'base_salary' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
        ]);

        DB::transaction(function () use ($validated): void {
            $user = User::create([
                'name' => trim($validated['name']),
                'email' => mb_strtolower(trim($validated['email'])),
                'password' => $validated['password'],
                'is_admin' => false,
            ]);

            DB::table('employee_profiles')->insert([
                'user_id' => $user->id,
                'employee_number' => filled($validated['employee_number'] ?? null) ? trim($validated['employee_number']) : null,
                'position' => trim($validated['position']),
                'hired_at' => $validated['hired_at'] ?? null,
                'base_salary' => $validated['base_salary'],
                'employment_status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return redirect()->route('admin.employees.index')->with('status', 'Akun dan profil karyawan berhasil dibuat.');
    }
}
