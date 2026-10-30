<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Services\AttendanceShiftService;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class AttendancePortalController extends Controller
{
    public function dashboard(Request $request): View
    {
        $user = $request->user();
        $todayAttendance = DB::table('attendances')
            ->leftJoin('shifts', 'shifts.id', '=', 'attendances.shift_id')
            ->where('attendances.user_id', $user->id)
            ->whereDate('attendances.attendance_date', today())
            ->first([
                'attendances.*',
                'shifts.name as shift_name',
                'shifts.starts_at',
                'shifts.ends_at',
            ]);
        $profile = DB::table('employee_profiles')->where('user_id', $user->id)->first();
        $assignedShift = $this->assignedShift($user->id);
        $currentShift = $todayAttendance ? null : $assignedShift;
        $shiftService = app(AttendanceShiftService::class);
        $checkInAllowed = ! $todayAttendance && $assignedShift !== null
            && $shiftService->checkInWindow($assignedShift, now()) !== null;
        $checkOutAllowed = $todayAttendance !== null && $todayAttendance->check_in_at !== null
            && $todayAttendance->check_out_at === null
            && $todayAttendance->starts_at !== null
            && $shiftService->checkOutWindow((object) [
                'starts_at' => $todayAttendance->starts_at,
                'ends_at' => $todayAttendance->ends_at,
            ], now()) !== null;
        $monthStart = today()->startOfMonth()->toDateString();
        $monthEnd = today()->endOfMonth()->toDateString();
        $monthRows = DB::table('attendances')
            ->where('user_id', $user->id)
            ->whereBetween('attendance_date', [$monthStart, $monthEnd])
            ->get(['status', 'late_minutes', 'check_in_at', 'check_out_at']);
        $workMinutes = $monthRows->sum(function (object $row): int {
            if ($row->check_in_at === null || $row->check_out_at === null) {
                return 0;
            }

            return (int) Carbon::parse($row->check_in_at)->diffInMinutes(Carbon::parse($row->check_out_at));
        });
        $recentAttendances = DB::table('attendances')
            ->leftJoin('shifts', 'shifts.id', '=', 'attendances.shift_id')
            ->where('attendances.user_id', $user->id)
            ->orderByDesc('attendances.attendance_date')
            ->limit(5)
            ->get([
                'attendances.attendance_date',
                'attendances.check_in_at',
                'attendances.check_out_at',
                'attendances.status',
                'attendances.late_minutes',
                'shifts.starts_at',
                'shifts.ends_at',
            ]);

        return view('employee.dashboard', [
            'profile' => $profile,
            'attendance' => $todayAttendance,
            'currentShift' => $currentShift,
            'assignedShift' => $assignedShift,
            'checkInAllowed' => $checkInAllowed,
            'checkOutAllowed' => $checkOutAllowed,
            'monthCount' => $monthRows->whereIn('status', ['present', 'late'])->count(),
            'monthLateCount' => $monthRows->where('status', 'late')->count(),
            'workMinutes' => $workMinutes,
            'recentAttendances' => $recentAttendances,
        ]);
    }

    public function checkInPage(Request $request): View|RedirectResponse
    {
        $attendance = $this->todayAttendance($request->user()->id);
        if ($attendance !== null) {
            return redirect()->route('employee.dashboard')
                ->with('status', 'Absensi masuk hari ini sudah tercatat.');
        }

        $shift = $this->assignedShift($request->user()->id);
        if ($shift === null) {
            return redirect()->route('employee.dashboard')
                ->with('status', 'Kamu belum memiliki shift tetap yang aktif. Hubungi admin.');
        }
        if (app(AttendanceShiftService::class)->checkInWindow($shift, now()) === null) {
            return redirect()->route('employee.dashboard')
                ->with('status', 'Absen masuk tersedia mulai 15 menit sebelum shift hingga shift berakhir.');
        }

        return view('employee.attendance.capture', [
            'actionType' => 'check-in',
            'attendance' => null,
            'assignedShift' => $shift,
        ]);
    }

    public function checkOutPage(Request $request): View|RedirectResponse
    {
        $attendance = $this->todayAttendance($request->user()->id);
        if ($attendance === null || $attendance->check_in_at === null) {
            return redirect()->route('employee.attendance.check-in')
                ->with('status', 'Catat absen masuk terlebih dahulu sebelum absen pulang.');
        }
        if ($attendance->check_out_at !== null) {
            return redirect()->route('employee.dashboard')
                ->with('status', 'Absensi pulang hari ini sudah tercatat.');
        }
        if ($attendance->starts_at === null
            || app(AttendanceShiftService::class)->checkOutWindow((object) [
                'starts_at' => $attendance->starts_at,
                'ends_at' => $attendance->ends_at,
            ], now()) === null) {
            return redirect()->route('employee.dashboard')
                ->with('status', 'Absen pulang hanya tersedia selama shift hingga 15 menit setelah shift berakhir.');
        }

        return view('employee.attendance.capture', [
            'actionType' => 'check-out',
            'attendance' => $attendance,
            'assignedShift' => null,
        ]);
    }

    public function checkIn(Request $request): RedirectResponse
    {
        $validated = $this->validatedProof($request);
        $userId = $request->user()->id;
        $shift = $this->assignedShift($userId);
        if ($shift === null) {
            throw ValidationException::withMessages([
                'attendance' => 'Kamu belum memiliki shift tetap yang aktif. Hubungi admin.',
            ]);
        }
        if (app(AttendanceShiftService::class)->checkInWindow($shift, now()) === null) {
            throw ValidationException::withMessages([
                'attendance' => 'Absen masuk tersedia mulai 15 menit sebelum shift hingga shift berakhir.',
            ]);
        }
        $photoPath = $this->storePhoto($request);

        try {
            DB::transaction(function () use ($validated, $userId, $photoPath): void {
                $shift = $this->assignedShift($userId, true);
                if ($shift === null) {
                    throw ValidationException::withMessages([
                        'attendance' => 'Kamu belum memiliki shift tetap yang aktif. Hubungi admin.',
                    ]);
                }

                $now = now();
                $window = app(AttendanceShiftService::class)->checkInWindow($shift, $now);
                if ($window === null) {
                    throw ValidationException::withMessages([
                        'attendance' => 'Waktu absen masuk sudah di luar jadwal shift.',
                    ]);
                }
                $attendanceDate = $window['start']->toDateString();
                $exists = DB::table('attendances')
                    ->where('user_id', $userId)
                    ->whereDate('attendance_date', $attendanceDate)
                    ->lockForUpdate()
                    ->exists();

                if ($exists) {
                    throw ValidationException::withMessages([
                        'attendance' => 'Absensi untuk shift ini sudah tercatat.',
                    ]);
                }

                $lateMinutes = app(AttendanceShiftService::class)->lateMinutes($window, $now);
                DB::table('attendances')->insert([
                    'user_id' => $userId,
                    'shift_id' => $shift->id,
                    'attendance_date' => $attendanceDate,
                    'check_in_at' => $now,
                    'check_in_photo_path' => $photoPath,
                    'check_in_latitude' => $validated['latitude'],
                    'check_in_longitude' => $validated['longitude'],
                    'check_in_location_accuracy' => $validated['location_accuracy'] ?? null,
                    'status' => $lateMinutes > 0 ? 'late' : 'present',
                    'late_minutes' => $lateMinutes,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($photoPath);
            throw $exception;
        }

        return redirect()->route('employee.dashboard')->with('status', 'Absen masuk berhasil dicatat.');
    }

    public function checkOut(Request $request): RedirectResponse
    {
        $validated = $this->validatedProof($request);
        $userId = $request->user()->id;
        $attendance = $this->todayAttendance($userId);
        if ($attendance === null || $attendance->check_in_at === null || $attendance->check_out_at !== null
            || $attendance->starts_at === null
            || app(AttendanceShiftService::class)->checkOutWindow((object) [
                'starts_at' => $attendance->starts_at,
                'ends_at' => $attendance->ends_at,
            ], now()) === null) {
            throw ValidationException::withMessages([
                'attendance' => 'Absen pulang hanya tersedia selama shift hingga 15 menit setelah shift berakhir.',
            ]);
        }
        $photoPath = $this->storePhoto($request);
        $attendanceDate = Carbon::parse($attendance->attendance_date)->toDateString();

        try {
            DB::transaction(function () use ($validated, $userId, $photoPath, $attendanceDate): void {
                $attendance = DB::table('attendances')
                    ->leftJoin('shifts', 'shifts.id', '=', 'attendances.shift_id')
                    ->where('user_id', $userId)
                    ->whereDate('attendance_date', $attendanceDate)
                    ->lockForUpdate()
                    ->first(['attendances.*', 'shifts.starts_at', 'shifts.ends_at']);

                if ($attendance === null || $attendance->check_in_at === null) {
                    throw ValidationException::withMessages([
                        'attendance' => 'Belum ada absen masuk hari ini.',
                    ]);
                }
                if ($attendance->check_out_at !== null) {
                    throw ValidationException::withMessages([
                        'attendance' => 'Absen pulang hari ini sudah tercatat.',
                    ]);
                }

                if ($attendance->starts_at === null
                    || app(AttendanceShiftService::class)->checkOutWindow((object) [
                        'starts_at' => $attendance->starts_at,
                        'ends_at' => $attendance->ends_at,
                    ], now()) === null) {
                    throw ValidationException::withMessages([
                        'attendance' => 'Waktu absen pulang sudah di luar jadwal shift.',
                    ]);
                }

                DB::table('attendances')->where('id', $attendance->id)->update([
                    'check_out_at' => now(),
                    'check_out_photo_path' => $photoPath,
                    'check_out_latitude' => $validated['latitude'],
                    'check_out_longitude' => $validated['longitude'],
                    'check_out_location_accuracy' => $validated['location_accuracy'] ?? null,
                    'updated_at' => now(),
                ]);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($photoPath);
            throw $exception;
        }

        return redirect()->route('employee.dashboard')->with('status', 'Absen pulang berhasil dicatat.');
    }

    public function history(Request $request): View
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'status' => ['nullable', Rule::in(['all', 'present', 'late', 'leave', 'absent'])],
        ]);
        $month = $validated['month'] ?? now()->format('Y-m');
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->toDateString();
        $end = Carbon::createFromFormat('Y-m', $month)->endOfMonth()->toDateString();
        $baseQuery = DB::table('attendances')
            ->leftJoin('shifts', 'shifts.id', '=', 'attendances.shift_id')
            ->where('attendances.user_id', $request->user()->id)
            ->whereBetween('attendances.attendance_date', [$start, $end])
            ->when(
                isset($validated['status']) && $validated['status'] !== 'all',
                fn (Builder $query) => $query->where('attendances.status', $validated['status']),
            );
        $summary = DB::table('attendances')
            ->where('user_id', $request->user()->id)
            ->whereBetween('attendance_date', [$start, $end])
            ->when(
                isset($validated['status']) && $validated['status'] !== 'all',
                fn (Builder $query) => $query->where('status', $validated['status']),
            )
            ->selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count,
            SUM(CASE WHEN status = 'leave' THEN 1 ELSE 0 END) as leave_count,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count
        ")->first();
        $attendances = $baseQuery
            ->select([
                'attendances.*',
                'shifts.name as shift_name',
                'shifts.starts_at',
                'shifts.ends_at',
            ])
            ->orderByDesc('attendances.attendance_date')
            ->paginate(10)
            ->withQueryString();

        return view('employee.attendance.history', [
            'attendances' => $attendances,
            'summary' => $summary,
            'month' => $month,
        ]);
    }

    private function todayAttendance(int $userId): ?object
    {
        $shift = $this->assignedShift($userId, false, false);
        $attendanceDate = $shift === null
            ? today()->toDateString()
            : app(AttendanceShiftService::class)->attendanceDate($shift, now());

        return DB::table('attendances')
            ->leftJoin('shifts', 'shifts.id', '=', 'attendances.shift_id')
            ->where('attendances.user_id', $userId)
            ->whereDate('attendances.attendance_date', $attendanceDate)
            ->first([
                'attendances.*',
                'shifts.name as shift_name',
                'shifts.starts_at',
                'shifts.ends_at',
            ]);
    }

    private function assignedShift(int $userId, bool $lock = false, bool $activeOnly = true): ?object
    {
        $query = DB::table('employee_profiles')
            ->join('shifts', 'shifts.id', '=', 'employee_profiles.shift_id')
            ->where('employee_profiles.user_id', $userId)
            ->when($activeOnly, fn (Builder $query) => $query->where('shifts.is_active', true))
            ->select(['shifts.id', 'shifts.name', 'shifts.starts_at', 'shifts.ends_at']);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedProof(Request $request): array
    {
        return $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'location_accuracy' => ['nullable', 'numeric', 'min:0', 'max:10000'],
        ]);
    }

    private function storePhoto(Request $request): string
    {
        $path = $request->file('photo')->store('attendance/'.$request->user()->id, 'local');

        if (! is_string($path) || $path === '') {
            throw new \RuntimeException('Foto absensi gagal disimpan. Silakan coba lagi.');
        }

        return $path;
    }
}
