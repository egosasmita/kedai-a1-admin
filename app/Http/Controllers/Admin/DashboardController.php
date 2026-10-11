<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        if (! $request->user()->is_admin) {
            return redirect()->route('employee.dashboard');
        }

        $today = today();
        $weekStart = $today->copy()->subDays(6);

        $employeeQuery = User::query()
            ->where('users.is_admin', false)
            ->leftJoin('employee_profiles', 'employee_profiles.user_id', '=', 'users.id')
            ->where(function (EloquentBuilder $query): void {
                $query->whereNull('employee_profiles.id')
                    ->orWhere('employee_profiles.employment_status', 'active');
            });

        $employeeCount = (clone $employeeQuery)->count();
        $menuCount = Menu::count();
        $availableMenuCount = Menu::where('is_available', true)->count();

        $todayAttendance = DB::table('attendances')
            ->whereDate('attendance_date', $today)
            ->whereIn('status', ['present', 'late'])
            ->distinct('user_id')
            ->count('user_id');

        $lowStockCount = DB::table('stock_items')
            ->where('is_active', true)
            ->whereColumn('quantity', '<=', 'minimum_quantity')
            ->count();

        $weeklyAttendance = DB::table('attendances')
            ->selectRaw('attendance_date, COUNT(DISTINCT user_id) as present_count')
            ->whereBetween('attendance_date', [$weekStart->toDateString(), $today->toDateString()])
            ->whereIn('status', ['present', 'late'])
            ->groupBy('attendance_date')
            ->pluck('present_count', 'attendance_date');

        $attendanceDays = collect(range(0, 6))->map(function (int $daysAgo) use ($today, $weeklyAttendance, $employeeCount): array {
            $date = $today->copy()->subDays(6 - $daysAgo);
            $presentCount = (int) $weeklyAttendance->get($date->toDateString(), 0);

            return [
                'date' => $date,
                'label' => $date->locale('id')->translatedFormat('D'),
                'present' => $presentCount,
                'rate' => $employeeCount > 0 ? min(100, (int) round(($presentCount / $employeeCount) * 100)) : 0,
            ];
        });

        $attendanceAverage = $attendanceDays->avg('rate');

        $previousStart = $weekStart->copy()->subDays(7);
        $previousAttendanceDays = DB::table('attendances')
            ->selectRaw('attendance_date, COUNT(DISTINCT user_id) as present_count')
            ->whereBetween('attendance_date', [$previousStart->toDateString(), $weekStart->copy()->subDay()->toDateString()])
            ->whereIn('status', ['present', 'late'])
            ->groupBy('attendance_date')
            ->pluck('present_count', 'attendance_date');
        $previousAverage = $employeeCount > 0
            ? collect(range(0, 6))
                ->map(fn (int $daysAgo): int => min(100, (int) round(((int) $previousAttendanceDays->get($previousStart->copy()->addDays($daysAgo)->toDateString(), 0) / $employeeCount) * 100)))
                ->avg()
            : 0;
        $attendanceChange = $attendanceAverage - $previousAverage;

        $attendancePoints = $attendanceDays->map(function (array $day, int $index): array {
            $x = 12 + ($index * 79.67);
            $y = 150 - ($day['rate'] * 1.2);

            return [...$day, 'x' => round($x, 2), 'y' => round($y, 2)];
        });
        $attendancePath = $attendancePoints
            ->map(fn (array $point, int $index): string => ($index === 0 ? 'M' : 'L').$point['x'].' '.$point['y'])
            ->implode(' ');

        $stockItems = DB::table('stock_items')
            ->where('is_active', true)
            ->orderByRaw('CASE WHEN minimum_quantity = 0 THEN quantity ELSE quantity / minimum_quantity END ASC')
            ->limit(6)
            ->get()
            ->map(function (object $stock): array {
                $quantity = (float) $stock->quantity;
                $minimum = (float) $stock->minimum_quantity;
                $level = $minimum > 0
                    ? min(100, (int) round(($quantity / $minimum) * 100))
                    : ($quantity > 0 ? 100 : 0);
                $state = $quantity <= 0 ? 'critical' : ($quantity <= $minimum ? 'low' : 'good');

                return [
                    'name' => $stock->name,
                    'quantity' => $quantity,
                    'unit' => $stock->unit,
                    'level' => $level,
                    'state' => $state,
                ];
            });

        $recentActivities = $this->recentActivities();

        $this->shareSidebarShift($request);

        return view('dashboard', [
            'menuCount' => $menuCount,
            'availableMenuCount' => $availableMenuCount,
            'employeeCount' => $employeeCount,
            'todayAttendance' => $todayAttendance,
            'todayAttendanceRate' => $employeeCount > 0 ? (int) round(($todayAttendance / $employeeCount) * 100) : 0,
            'lowStockCount' => $lowStockCount,
            'attendanceDays' => $attendanceDays,
            'attendanceAverage' => (int) round($attendanceAverage),
            'attendanceChange' => (int) round($attendanceChange),
            'attendancePath' => $attendancePath,
            'attendancePoints' => $attendancePoints,
            'weekStart' => $weekStart,
            'today' => $today,
            'stockItems' => $stockItems,
            'recentActivities' => $recentActivities,
            'greeting' => match (true) {
                now()->hour < 11 => 'pagi',
                now()->hour < 15 => 'siang',
                now()->hour < 18 => 'sore',
                default => 'malam',
            },
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function recentActivities(): Collection
    {
        $attendanceActivities = DB::table('attendances')
            ->join('users', 'users.id', '=', 'attendances.user_id')
            ->leftJoin('employee_profiles', 'employee_profiles.user_id', '=', 'users.id')
            ->whereNotNull('attendances.check_in_at')
            ->select([
                'users.name as actor',
                'employee_profiles.position as detail',
                'attendances.status as status',
                'attendances.check_in_at as happened_at',
            ])
            ->latest('attendances.check_in_at')
            ->limit(5)
            ->get()
            ->map(fn (object $activity): array => [
                'actor' => $activity->actor,
                'detail' => $activity->detail ?: 'Karyawan',
                'status' => $activity->status === 'late' ? 'Terlambat' : 'Check in',
                'status_class' => $activity->status === 'late' ? 'status-late' : 'status-ok',
                'time' => Carbon::parse($activity->happened_at),
            ]);

        $stockActivities = DB::table('stock_transactions')
            ->join('stock_items', 'stock_items.id', '=', 'stock_transactions.stock_item_id')
            ->leftJoin('users', 'users.id', '=', 'stock_transactions.recorded_by')
            ->select([
                'users.name as actor',
                'stock_items.name as item_name',
                'stock_items.unit',
                'stock_transactions.type',
                'stock_transactions.quantity',
                'stock_transactions.occurred_at as happened_at',
            ])
            ->latest('stock_transactions.occurred_at')
            ->limit(5)
            ->get()
            ->map(fn (object $activity): array => [
                'actor' => $activity->actor ?: 'Operasional',
                'detail' => $this->stockActivityDetail($activity),
                'status' => match ($activity->type) {
                    'in' => 'Stok masuk',
                    'out' => 'Stok keluar',
                    default => 'Koreksi stok',
                },
                'status_class' => $activity->type === 'in' ? 'status-ok' : 'status-late',
                'time' => Carbon::parse($activity->happened_at),
            ]);

        return $attendanceActivities
            ->concat($stockActivities)
            ->sortByDesc(fn (array $activity): int => $activity['time']->getTimestamp())
            ->take(3)
            ->values();
    }

    private function stockActivityDetail(object $activity): string
    {
        $quantity = rtrim(rtrim(number_format((float) $activity->quantity, 3, ',', '.'), '0'), ',');

        return "{$quantity} {$activity->unit} · {$activity->item_name}";
    }

    private function shareSidebarShift(Request $request): void
    {
        $now = now();
        $currentTime = $now->format('H:i:s');

        $activeShift = DB::table('shifts')
            ->where('is_active', true)
            ->where(function (Builder $query) use ($currentTime): void {
                $query->where(function (Builder $query) use ($currentTime): void {
                    $query->whereColumn('starts_at', '<=', 'ends_at')
                        ->whereTime('starts_at', '<=', $currentTime)
                        ->whereTime('ends_at', '>', $currentTime);
                })->orWhere(function (Builder $query) use ($currentTime): void {
                    $query->whereColumn('starts_at', '>', 'ends_at')
                        ->where(function (Builder $query) use ($currentTime): void {
                            $query->whereTime('starts_at', '<=', $currentTime)
                                ->orWhereTime('ends_at', '>', $currentTime);
                        });
                });
            })
            ->orderBy('sort_order')
            ->first();

        $assignedCount = $activeShift
            ? DB::table('attendances')
                ->where('shift_id', $activeShift->id)
                ->whereDate('attendance_date', $now->toDateString())
                ->whereIn('status', ['present', 'late'])
                ->distinct('user_id')
                ->count('user_id')
            : 0;

        $request->attributes->set('sidebarShift', [
            'name' => $activeShift?->name ?? 'Tidak ada shift aktif',
            'starts_at' => $activeShift ? substr((string) $activeShift->starts_at, 0, 5) : null,
            'ends_at' => $activeShift ? substr((string) $activeShift->ends_at, 0, 5) : null,
            'employee_count' => $assignedCount,
        ]);
    }
}
