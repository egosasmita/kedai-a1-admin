<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AttendanceShiftService;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'date' => ['nullable', 'date'],
            'shift' => ['nullable', 'integer', 'exists:shifts,id'],
        ]);
        $date = Carbon::parse($filters['date'] ?? today())->startOfDay();

        $employees = DB::table('users')
            ->leftJoin('employee_profiles', 'employee_profiles.user_id', '=', 'users.id')
            ->leftJoin('shifts as assigned_shifts', 'assigned_shifts.id', '=', 'employee_profiles.shift_id')
            ->leftJoin('attendances', function (Builder $join) use ($date): void {
                $join->on('attendances.user_id', '=', 'users.id')
                    ->whereDate('attendances.attendance_date', $date->toDateString());
            })
            ->leftJoin('shifts', 'shifts.id', '=', 'attendances.shift_id')
            ->where('users.is_admin', false)
            ->where(function (Builder $query): void {
                $query->whereNull('employee_profiles.id')
                    ->orWhere('employee_profiles.employment_status', 'active');
            })
            ->when($filters['shift'] ?? null, fn (Builder $query, int $shiftId) => $query->where('attendances.shift_id', $shiftId))
            ->select([
                'users.id',
                'users.name',
                'employee_profiles.position',
                'employee_profiles.shift_id as assigned_shift_id',
                'assigned_shifts.name as assigned_shift_name',
                'assigned_shifts.starts_at as assigned_shift_starts_at',
                'assigned_shifts.ends_at as assigned_shift_ends_at',
                'assigned_shifts.is_active as assigned_shift_is_active',
                'attendances.id as attendance_id',
                'attendances.shift_id',
                'attendances.check_in_at',
                'attendances.check_out_at',
                'attendances.status',
                'attendances.late_minutes',
                'shifts.name as shift_name',
                'shifts.starts_at',
                'shifts.ends_at',
            ])
            ->orderBy('users.name')
            ->get();

        $monthStart = $date->copy()->startOfMonth()->toDateString();
        $monthEnd = $date->copy()->endOfMonth()->toDateString();
        $monthSummary = DB::table('attendances')
            ->whereBetween('attendance_date', [$monthStart, $monthEnd])
            ->selectRaw("
                COUNT(*) as recorded,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,
                SUM(CASE WHEN status = 'leave' THEN 1 ELSE 0 END) as leave_count,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent
            ")
            ->first();

        $shifts = DB::table('shifts')->where('is_active', true)->orderBy('sort_order')->get();
        $shiftService = app(AttendanceShiftService::class);
        $currentShift = $shifts->first(function (object $shift) use ($shiftService): bool {
            return $shiftService->checkInWindow($shift, now()) !== null
                || $shiftService->checkOutWindow($shift, now()) !== null;
        });
        $checkInEmployees = $date->isToday()
            ? $employees->filter(function (object $employee) use ($shiftService): bool {
                if ($employee->attendance_id !== null || $employee->assigned_shift_id === null || ! $employee->assigned_shift_is_active) {
                    return false;
                }

                $shift = (object) [
                    'starts_at' => $employee->assigned_shift_starts_at,
                    'ends_at' => $employee->assigned_shift_ends_at,
                ];

                return $shiftService->checkInWindow($shift, now()) !== null;
            })
            : collect();
        $checkOutEmployees = $date->isToday()
            ? $employees->filter(function (object $employee) use ($shiftService): bool {
                if ($employee->check_in_at === null || $employee->check_out_at !== null
                    || $employee->shift_id === null || $employee->starts_at === null) {
                    return false;
                }

                $shift = (object) ['starts_at' => $employee->starts_at, 'ends_at' => $employee->ends_at];

                return $shiftService->checkOutWindow($shift, now()) !== null;
            })
            : collect();

        return view('admin.attendance.index', [
            'employees' => $employees,
            'checkInEmployees' => $checkInEmployees,
            'checkOutEmployees' => $checkOutEmployees,
            'shifts' => $shifts,
            'currentShift' => $currentShift,
            'date' => $date,
            'monthSummary' => $monthSummary,
            'presentCount' => $employees->whereIn('status', ['present', 'late'])->count(),
            'lateCount' => $employees->where('status', 'late')->count(),
            'leaveCount' => $employees->where('status', 'leave')->count(),
            'notRecordedCount' => $employees->whereNull('attendance_id')->count(),
        ]);
    }

    public function checkIn(Request $request, AttendanceShiftService $shiftService): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);
        $employee = DB::table('users')
            ->leftJoin('employee_profiles', 'employee_profiles.user_id', '=', 'users.id')
            ->leftJoin('shifts', 'shifts.id', '=', 'employee_profiles.shift_id')
            ->where('users.id', $validated['user_id'])
            ->where('users.is_admin', false)
            ->where(function (Builder $query): void {
                $query->whereNull('employee_profiles.id')
                    ->orWhere('employee_profiles.employment_status', 'active');
            })
            ->first([
                'users.id',
                'employee_profiles.shift_id',
                'shifts.starts_at',
                'shifts.ends_at',
                'shifts.is_active as shift_is_active',
            ]);

        if ($employee === null) {
            throw ValidationException::withMessages(['user_id' => 'Karyawan tidak aktif atau tidak ditemukan.']);
        }

        if ($employee->shift_id === null || ! $employee->shift_is_active) {
            throw ValidationException::withMessages(['user_id' => 'Karyawan belum memiliki shift tetap yang aktif. Hubungi admin.']);
        }

        $now = now();
        $shift = (object) ['starts_at' => $employee->starts_at, 'ends_at' => $employee->ends_at];
        $window = $shiftService->checkInWindow($shift, $now);
        if ($window === null) {
            throw ValidationException::withMessages(['user_id' => 'Absen masuk hanya tersedia mulai 15 menit sebelum shift hingga shift berakhir.']);
        }
        $attendanceDate = $window['start']->toDateString();
        $alreadyRecorded = DB::table('attendances')
            ->where('user_id', $employee->id)
            ->whereDate('attendance_date', $attendanceDate)
            ->exists();

        if ($alreadyRecorded) {
            throw ValidationException::withMessages(['user_id' => 'Absensi untuk shift ini sudah tercatat.']);
        }

        $lateMinutes = $shiftService->lateMinutes($window, $now);

        DB::table('attendances')->insert([
            'user_id' => $employee->id,
            'shift_id' => $employee->shift_id,
            'attendance_date' => $attendanceDate,
            'check_in_at' => $now,
            'status' => $lateMinutes > 0 ? 'late' : 'present',
            'late_minutes' => $lateMinutes,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return redirect()->route('admin.attendance.index')->with('status', 'Check in berhasil dicatat.');
    }

    public function checkOut(Request $request, AttendanceShiftService $shiftService): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);
        $employeeIsActive = DB::table('users')
            ->leftJoin('employee_profiles', 'employee_profiles.user_id', '=', 'users.id')
            ->where('users.id', $validated['user_id'])
            ->where('users.is_admin', false)
            ->where(function (Builder $query): void {
                $query->whereNull('employee_profiles.id')
                    ->orWhere('employee_profiles.employment_status', 'active');
            })
            ->exists();
        if (! $employeeIsActive) {
            throw ValidationException::withMessages(['user_id' => 'Karyawan tidak aktif atau tidak ditemukan.']);
        }

        $assignedShift = DB::table('employee_profiles')
            ->join('shifts', 'shifts.id', '=', 'employee_profiles.shift_id')
            ->where('employee_profiles.user_id', $validated['user_id'])
            ->first(['shifts.id', 'shifts.starts_at', 'shifts.ends_at']);
        if ($assignedShift === null) {
            throw ValidationException::withMessages(['user_id' => 'Karyawan belum memiliki shift tetap.']);
        }
        $checkoutWindow = $shiftService->checkOutWindow($assignedShift, now());
        if ($checkoutWindow === null) {
            throw ValidationException::withMessages(['user_id' => 'Absen pulang hanya tersedia selama shift hingga 15 menit setelah shift berakhir.']);
        }

        $attendanceDate = $checkoutWindow['start']->toDateString();
        $attendance = DB::table('attendances')
            ->leftJoin('shifts', 'shifts.id', '=', 'attendances.shift_id')
            ->where('attendances.user_id', $validated['user_id'])
            ->whereDate('attendances.attendance_date', $attendanceDate)
            ->first(['attendances.*', 'shifts.starts_at', 'shifts.ends_at']);

        if ($attendance === null || $attendance->check_in_at === null) {
            throw ValidationException::withMessages(['user_id' => 'Karyawan belum melakukan check in hari ini.']);
        }
        if ($attendance->check_out_at !== null) {
            throw ValidationException::withMessages(['user_id' => 'Check out hari ini sudah tercatat.']);
        }
        DB::table('attendances')->where('id', $attendance->id)->update([
            'check_out_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.attendance.index')->with('status', 'Check out berhasil dicatat.');
    }
}
