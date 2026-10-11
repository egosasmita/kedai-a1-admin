<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
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
        $activeShifts = DB::table('shifts')->where('is_active', true)->orderBy('sort_order')->get();
        $currentShift = $todayAttendance ? null : $this->currentShift($activeShifts);
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
            'activeShifts' => $activeShifts,
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

        $shifts = DB::table('shifts')->where('is_active', true)->orderBy('sort_order')->get();

        return view('employee.attendance.capture', [
            'actionType' => 'check-in',
            'attendance' => null,
            'shifts' => $shifts,
            'selectedShift' => $this->currentShift($shifts)?->id,
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

        return view('employee.attendance.capture', [
            'actionType' => 'check-out',
            'attendance' => $attendance,
            'shifts' => collect(),
            'selectedShift' => null,
        ]);
    }

    public function checkIn(Request $request): RedirectResponse
    {
        $validated = $this->validatedProof($request, true);
        $userId = $request->user()->id;
        $photoPath = $this->storePhoto($request);

        try {
            DB::transaction(function () use ($validated, $userId, $photoPath): void {
                $exists = DB::table('attendances')
                    ->where('user_id', $userId)
                    ->whereDate('attendance_date', today())
                    ->lockForUpdate()
                    ->exists();

                if ($exists) {
                    throw ValidationException::withMessages([
                        'attendance' => 'Absensi masuk hari ini sudah tercatat.',
                    ]);
                }

                $shift = null;
                if (isset($validated['shift_id']) && $validated['shift_id'] !== '') {
                    $shift = DB::table('shifts')
                        ->where('id', $validated['shift_id'])
                        ->where('is_active', true)
                        ->first();

                    if ($shift === null) {
                        throw ValidationException::withMessages([
                            'shift_id' => 'Shift yang dipilih sudah tidak aktif.',
                        ]);
                    }
                }

                $now = now();
                $lateMinutes = $shift === null ? 0 : $this->lateMinutes($shift, $now);
                DB::table('attendances')->insert([
                    'user_id' => $userId,
                    'shift_id' => $shift?->id,
                    'attendance_date' => today()->toDateString(),
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
        $validated = $this->validatedProof($request, false);
        $userId = $request->user()->id;
        $photoPath = $this->storePhoto($request);

        try {
            DB::transaction(function () use ($validated, $userId, $photoPath): void {
                $attendance = DB::table('attendances')
                    ->where('user_id', $userId)
                    ->whereDate('attendance_date', today())
                    ->lockForUpdate()
                    ->first();

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
        return DB::table('attendances')
            ->leftJoin('shifts', 'shifts.id', '=', 'attendances.shift_id')
            ->where('attendances.user_id', $userId)
            ->whereDate('attendances.attendance_date', today())
            ->first([
                'attendances.*',
                'shifts.name as shift_name',
                'shifts.starts_at',
                'shifts.ends_at',
            ]);
    }

    private function currentShift(iterable $shifts): ?object
    {
        $now = now()->format('H:i:s');

        foreach ($shifts as $shift) {
            $isCurrent = $shift->starts_at <= $shift->ends_at
                ? $now >= $shift->starts_at && $now < $shift->ends_at
                : $now >= $shift->starts_at || $now < $shift->ends_at;

            if ($isCurrent) {
                return $shift;
            }
        }

        return null;
    }

    private function lateMinutes(object $shift, Carbon $checkInAt): int
    {
        $shiftStart = Carbon::parse(today()->toDateString().' '.$shift->starts_at);

        if ($shift->ends_at < $shift->starts_at && $checkInAt->format('H:i:s') < $shift->ends_at) {
            $shiftStart->subDay();
        }

        return $checkInAt->greaterThan($shiftStart)
            ? (int) ceil($shiftStart->diffInSeconds($checkInAt) / 60)
            : 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedProof(Request $request, bool $includeShift): array
    {
        return $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'location_accuracy' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'shift_id' => [
                Rule::requiredIf($includeShift),
                'nullable',
                'integer',
                Rule::exists('shifts', 'id')->where(fn (Builder $query) => $query->where('is_active', true)),
            ],
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
