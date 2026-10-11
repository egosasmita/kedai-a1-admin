<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
                'attendances.id as attendance_id',
                'attendances.check_in_at',
                'attendances.check_out_at',
                'attendances.status',
                'attendances.late_minutes',
                'shifts.name as shift_name',
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
        $currentShift = $shifts->first(function (object $shift): bool {
            $now = now()->format('H:i:s');
            return $shift->starts_at <= $shift->ends_at
                ? $now >= $shift->starts_at && $now < $shift->ends_at
                : $now >= $shift->starts_at || $now < $shift->ends_at;
        });

        return view('admin.attendance.index', [
            'employees' => $employees,
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

    public function checkIn(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'shift_id' => ['nullable', 'integer', 'exists:shifts,id'],
        ]);
        $employee = DB::table('users')
            ->leftJoin('employee_profiles', 'employee_profiles.user_id', '=', 'users.id')
            ->where('users.id', $validated['user_id'])
            ->where('users.is_admin', false)
            ->where(function (Builder $query): void {
                $query->whereNull('employee_profiles.id')
                    ->orWhere('employee_profiles.employment_status', 'active');
            })
            ->first(['users.id']);

        if ($employee === null) {
            throw ValidationException::withMessages(['user_id' => 'Karyawan tidak aktif atau tidak ditemukan.']);
        }

        $alreadyRecorded = DB::table('attendances')
            ->where('user_id', $employee->id)
            ->whereDate('attendance_date', today())
            ->exists();

        if ($alreadyRecorded) {
            throw ValidationException::withMessages(['user_id' => 'Absensi karyawan untuk hari ini sudah tercatat.']);
        }

        $shift = isset($validated['shift_id'])
            ? DB::table('shifts')->where('id', $validated['shift_id'])->where('is_active', true)->first()
            : null;
        if (isset($validated['shift_id']) && $shift === null) {
            throw ValidationException::withMessages(['shift_id' => 'Shift yang dipilih tidak aktif.']);
        }

        $now = now();
        $lateMinutes = 0;
        if ($shift !== null) {
            $shiftStart = Carbon::parse(today()->toDateString().' '.$shift->starts_at);
            if ($shift->ends_at < $shift->starts_at && $now->format('H:i:s') < $shift->ends_at) {
                $shiftStart->subDay();
            }
            $lateMinutes = $now->greaterThan($shiftStart)
                ? (int) ceil($shiftStart->diffInSeconds($now) / 60)
                : 0;
        }

        DB::table('attendances')->insert([
            'user_id' => $employee->id,
            'shift_id' => $shift?->id,
            'attendance_date' => today()->toDateString(),
            'check_in_at' => $now,
            'status' => $lateMinutes > 0 ? 'late' : 'present',
            'late_minutes' => $lateMinutes,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return redirect()->route('admin.attendance.index')->with('status', 'Check in berhasil dicatat.');
    }

    public function checkOut(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);
        $attendance = DB::table('attendances')
            ->where('user_id', $validated['user_id'])
            ->whereDate('attendance_date', today())
            ->first();

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
