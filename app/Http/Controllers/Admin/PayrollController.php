<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PayrollController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'status' => ['nullable', Rule::in(['all', 'draft', 'review', 'ready', 'paid'])],
            'payroll' => ['nullable', 'integer', 'exists:payrolls,id'],
        ]);
        $month = $validated['month'] ?? now()->format('Y-m');
        $startsOn = $month.'-01';
        $endsOn = Carbon::parse($startsOn)->endOfMonth()->toDateString();
        $period = DB::table('payroll_periods')
            ->where('starts_on', $startsOn)
            ->where('ends_on', $endsOn)
            ->first();

        $payrollFilters = DB::table('payrolls')
            ->when($period !== null, fn (Builder $query) => $query->where('payrolls.payroll_period_id', $period->id), fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when(
                isset($validated['status']) && $validated['status'] !== 'all',
                fn (Builder $query) => $query->where('payrolls.status', $validated['status']),
            );
        $payrollQuery = (clone $payrollFilters)
            ->join('users', 'users.id', '=', 'payrolls.user_id')
            ->leftJoin('employee_profiles', 'employee_profiles.user_id', '=', 'users.id')
            ->select([
                'payrolls.*',
                'users.name',
                'users.email',
                'employee_profiles.position',
                'employee_profiles.employee_number',
            ]);
        $payrolls = (clone $payrollQuery)->orderBy('users.name')->paginate(8)->withQueryString();
        $payrollTotals = (clone $payrollFilters)
            ->selectRaw('COUNT(*) as employee_count, COALESCE(SUM(payrolls.net_pay), 0) as net_total, COALESCE(SUM(payrolls.base_salary), 0) as base_total, COALESCE(SUM(payrolls.overtime_amount + payrolls.allowances + payrolls.bonus), 0) as additions_total, COALESCE(SUM(payrolls.deductions), 0) as deductions_total')
            ->first();
        $selectedPayroll = isset($validated['payroll'])
            ? (clone $payrollQuery)->where('payrolls.id', $validated['payroll'])->first()
            : $payrolls->first();
        $payrollItems = $selectedPayroll
            ? DB::table('payroll_items')->where('payroll_id', $selectedPayroll->id)->orderBy('created_at')->get()
            : collect();
        $payments = $selectedPayroll
            ? DB::table('payroll_payments')->where('payroll_id', $selectedPayroll->id)->latest('paid_at')->get()
            : collect();

        return view('admin.payroll.index', [
            'month' => $month,
            'period' => $period,
            'payrolls' => $payrolls,
            'totals' => $payrollTotals,
            'selectedPayroll' => $selectedPayroll,
            'payrollItems' => $payrollItems,
            'payments' => $payments,
        ]);
    }

    public function process(Request $request): RedirectResponse
    {
        $validated = $request->validate(['month' => ['required', 'date_format:Y-m']]);
        $startsOn = $validated['month'].'-01';
        $endsOn = Carbon::parse($startsOn)->endOfMonth()->toDateString();

        DB::transaction(function () use ($startsOn, $endsOn, $request): void {
            $period = DB::table('payroll_periods')
                ->where('starts_on', $startsOn)
                ->where('ends_on', $endsOn)
                ->lockForUpdate()
                ->first();

            if ($period?->status === 'paid') {
                throw ValidationException::withMessages(['month' => 'Periode ini sudah dibayar dan tidak dapat dihitung ulang.']);
            }

            $periodId = $period?->id ?? DB::table('payroll_periods')->insertGetId([
                'name' => Carbon::parse($startsOn)->locale('id')->translatedFormat('F Y'),
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'status' => 'draft',
                'processed_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $employees = DB::table('users')
                ->leftJoin('employee_profiles', 'employee_profiles.user_id', '=', 'users.id')
                ->where('users.is_admin', false)
                ->where(function (Builder $query): void {
                    $query->whereNull('employee_profiles.id')
                        ->orWhere('employee_profiles.employment_status', 'active');
                })
                ->select(['users.id', 'employee_profiles.base_salary'])
                ->get();

            foreach ($employees as $employee) {
                $attendance = DB::table('attendances')
                    ->where('user_id', $employee->id)
                    ->whereBetween('attendance_date', [$startsOn, $endsOn])
                    ->selectRaw("SUM(CASE WHEN status IN ('present', 'late') THEN 1 ELSE 0 END) as attendance_days, SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count")
                    ->first();
                $payroll = DB::table('payrolls')
                    ->where('payroll_period_id', $periodId)
                    ->where('user_id', $employee->id)
                    ->first();

                if ($payroll !== null && in_array($payroll->status, ['ready', 'paid'], true)) {
                    continue;
                }

                $baseSalary = (float) ($employee->base_salary ?? 0);
                $additions = DB::table('payroll_items')
                    ->where('payroll_id', $payroll?->id)
                    ->selectRaw("
                        COALESCE(SUM(CASE WHEN type = 'allowance' THEN amount ELSE 0 END), 0) as allowances,
                        COALESCE(SUM(CASE WHEN type = 'bonus' THEN amount ELSE 0 END), 0) as bonus,
                        COALESCE(SUM(CASE WHEN type = 'overtime' THEN amount ELSE 0 END), 0) as overtime_amount,
                        COALESCE(SUM(CASE WHEN type = 'deduction' THEN amount ELSE 0 END), 0) as deductions
                    ")
                    ->first();
                $values = [
                    'attendance_days' => (int) ($attendance->attendance_days ?? 0),
                    'late_count' => (int) ($attendance->late_count ?? 0),
                    'base_salary' => $baseSalary,
                    'overtime_amount' => (float) $additions->overtime_amount,
                    'allowances' => (float) $additions->allowances,
                    'bonus' => (float) $additions->bonus,
                    'deductions' => (float) $additions->deductions,
                    'net_pay' => $baseSalary + (float) $additions->overtime_amount
                        + (float) $additions->allowances + (float) $additions->bonus
                        - (float) $additions->deductions,
                    'status' => 'review',
                    'updated_at' => now(),
                ];

                if ($payroll === null) {
                    DB::table('payrolls')->insert([
                        ...$values,
                        'payroll_period_id' => $periodId,
                        'user_id' => $employee->id,
                        'overtime_hours' => 0,
                        'created_at' => now(),
                    ]);
                } else {
                    DB::table('payrolls')->where('id', $payroll->id)->update($values);
                }
            }

            DB::table('payroll_periods')->where('id', $periodId)->update([
                'status' => 'calculated',
                'processed_by' => $request->user()->id,
                'updated_at' => now(),
            ]);
        });

        return redirect()->route('admin.payroll.index', ['month' => $validated['month']])
            ->with('status', 'Perhitungan gaji periode berhasil diperbarui.');
    }

    public function addItem(Request $request, int $payroll): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['allowance', 'bonus', 'overtime', 'deduction'])],
            'label' => ['required', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
        ]);

        DB::transaction(function () use ($validated, $payroll): void {
            $record = DB::table('payrolls')->where('id', $payroll)->lockForUpdate()->first();
            if ($record === null) {
                abort(404);
            }
            if ($record->status === 'paid') {
                throw ValidationException::withMessages(['amount' => 'Komponen tidak dapat diubah setelah gaji dibayar.']);
            }
            $period = DB::table('payroll_periods')->where('id', $record->payroll_period_id)->first();
            if ($period?->status === 'paid') {
                throw ValidationException::withMessages(['amount' => 'Periode gaji ini sudah ditutup.']);
            }

            DB::table('payroll_items')->insert([
                'payroll_id' => $record->id,
                ...$validated,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $sums = DB::table('payroll_items')
                ->where('payroll_id', $record->id)
                ->selectRaw("
                    COALESCE(SUM(CASE WHEN type = 'allowance' THEN amount ELSE 0 END), 0) as allowances,
                    COALESCE(SUM(CASE WHEN type = 'bonus' THEN amount ELSE 0 END), 0) as bonus,
                    COALESCE(SUM(CASE WHEN type = 'overtime' THEN amount ELSE 0 END), 0) as overtime_amount,
                    COALESCE(SUM(CASE WHEN type = 'deduction' THEN amount ELSE 0 END), 0) as deductions
                ")
                ->first();
            DB::table('payrolls')->where('id', $record->id)->update([
                'allowances' => $sums->allowances,
                'bonus' => $sums->bonus,
                'overtime_amount' => $sums->overtime_amount,
                'deductions' => $sums->deductions,
                'net_pay' => (float) $record->base_salary + (float) $sums->allowances
                    + (float) $sums->bonus + (float) $sums->overtime_amount - (float) $sums->deductions,
                'status' => 'review',
                'updated_at' => now(),
            ]);
        });

        return redirect()->route('admin.payroll.index', [
            'month' => $request->input('month'),
            'payroll' => $payroll,
        ])->with('status', 'Komponen gaji berhasil ditambahkan.');
    }

    public function pay(Request $request, int $payroll): RedirectResponse
    {
        $validated = $request->validate([
            'method' => ['required', Rule::in(['cash', 'bank_transfer', 'e_wallet', 'other'])],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);

        $month = DB::transaction(function () use ($validated, $payroll, $request): string {
            $record = DB::table('payrolls')->where('id', $payroll)->lockForUpdate()->first();
            if ($record === null) {
                abort(404);
            }
            if ($record->status === 'paid') {
                throw ValidationException::withMessages(['method' => 'Gaji ini sudah dibayar.']);
            }
            if (! in_array($record->status, ['review', 'ready'], true)) {
                throw ValidationException::withMessages(['method' => 'Hitung gaji periode ini sebelum mencatat pembayaran.']);
            }

            $now = now();
            DB::table('payroll_payments')->insert([
                'payroll_id' => $record->id,
                'processed_by' => $request->user()->id,
                'amount' => $record->net_pay,
                'method' => $validated['method'],
                'reference' => filled($validated['reference'] ?? null) ? trim($validated['reference']) : null,
                'paid_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('payrolls')->where('id', $record->id)->update([
                'status' => 'paid',
                'paid_at' => $now,
                'updated_at' => $now,
            ]);

            $remaining = DB::table('payrolls')
                ->where('payroll_period_id', $record->payroll_period_id)
                ->where('status', '!=', 'paid')
                ->exists();
            if (! $remaining) {
                DB::table('payroll_periods')->where('id', $record->payroll_period_id)->update([
                    'status' => 'paid',
                    'paid_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $period = DB::table('payroll_periods')->where('id', $record->payroll_period_id)->first();

            return substr($period->starts_on, 0, 7);
        });

        return redirect()->route('admin.payroll.index', ['month' => $month, 'payroll' => $payroll])
            ->with('status', 'Pembayaran gaji berhasil dicatat.');
    }
}
