@extends('layouts.admin')

@section('page-title', 'Absensi')
@section('page-subtitle', 'Check in, check out, dan rekap kehadiran tim Kedai A1')
@section('active', 'absensi')

@section('content')
    @if (session('status'))
        <div class="menu-flash" role="status">{{ session('status') }}</div>
    @endif
    <x-admin.validation-errors />
    <section class="attendance-top">
        <article class="attendance-clock">
            <div><span class="ops-kicker">{{ now()->locale('id')->translatedFormat('l, j F Y') }}</span><strong>{{ now()->format('H:i') }}</strong><small>{{ $currentShift ? $currentShift->name.' sedang berjalan' : 'Tidak ada shift aktif saat ini' }}</small></div>
            @if ($date->isToday())
                <div class="attendance-actions">
                    <form method="POST" action="{{ route('admin.attendance.check-in') }}">
                        @csrf
                        <label class="sr-only" for="check-in-user">Pilih karyawan untuk check in</label>
                        <select id="check-in-user" name="user_id" required><option value="">Pilih karyawan</option>@foreach ($employees as $employee)@if (!$employee->attendance_id)<option value="{{ $employee->id }}">{{ $employee->name }}</option>@endif @endforeach</select>
                        <input type="hidden" name="shift_id" value="{{ $currentShift?->id }}">
                        <button class="attendance-action-button is-gold" type="submit">◎<span>Check In</span></button>
                    </form>
                    <form method="POST" action="{{ route('admin.attendance.check-out') }}">
                        @csrf
                        <label class="sr-only" for="check-out-user">Pilih karyawan untuk check out</label>
                        <select id="check-out-user" name="user_id" required><option value="">Pilih karyawan</option>@foreach ($employees as $employee)@if ($employee->check_in_at && !$employee->check_out_at)<option value="{{ $employee->id }}">{{ $employee->name }}</option>@endif @endforeach</select>
                        <button class="attendance-action-button" type="submit">⇥<span>Check Out</span></button>
                    </form>
                </div>
            @else
                <span class="ops-readonly-note">Absensi hanya dapat dicatat untuk hari ini.</span>
            @endif
        </article>
        <article class="dashboard-card attendance-recap">
            <div class="card-heading"><div><h2>Rekap hari ini</h2><p>{{ $currentShift ? $currentShift->name.' · '.$currentShift->starts_at.'–'.$currentShift->ends_at : 'Semua shift' }}</p></div></div>
            <div class="attendance-recap-grid">
                <div><strong>{{ $presentCount }}</strong><small>Hadir</small></div>
                <div><strong>{{ $lateCount }}</strong><small>Terlambat</small></div>
                <div><strong>{{ $leaveCount }}</strong><small>Cuti</small></div>
                <div><strong>{{ $notRecordedCount }}</strong><small>Belum hadir</small></div>
            </div>
        </article>
    </section>

    <section class="ops-toolbar attendance-toolbar">
        <form class="menu-filters" action="{{ route('admin.attendance.index') }}" method="GET">
            <label class="menu-form-field attendance-filter-date"><span class="sr-only">Tanggal absensi</span><input type="date" name="date" value="{{ $date->toDateString() }}" max="{{ today()->toDateString() }}"></label>
            <select name="shift" aria-label="Filter shift"><option value="">Semua shift</option>@foreach ($shifts as $shift)<option value="{{ $shift->id }}" @selected((string) request('shift') === (string) $shift->id)>{{ $shift->name }}</option>@endforeach</select>
            <button class="filter-submit" type="submit">Terapkan</button>
        </form>
    </section>

    <section class="ops-two-column attendance-workspace">
        <article class="dashboard-card ops-table-card">
            <div class="menu-list-heading"><div><h2>Rekap harian</h2><p>{{ $employees->count() }} karyawan untuk {{ $date->locale('id')->translatedFormat('j F Y') }}</p></div></div>
            @if ($employees->isEmpty())
                <div class="menu-empty-state"><h3>Belum ada karyawan aktif</h3><p>Tambahkan karyawan untuk mulai mengelola absensi.</p></div>
            @else
                <div class="menu-table-scroll">
                    <table class="menu-table ops-table attendance-table">
                        <thead><tr><th>KARYAWAN</th><th>MASUK</th><th>KELUAR</th><th>DURASI</th><th>STATUS</th></tr></thead>
                        <tbody>
                        @foreach ($employees as $employee)
                            @php
                                $status = $employee->status ?: 'unrecorded';
                                $duration = $employee->check_in_at && $employee->check_out_at
                                    ? \Illuminate\Support\Carbon::parse($employee->check_in_at)->diffInMinutes(\Illuminate\Support\Carbon::parse($employee->check_out_at))
                                    : null;
                            @endphp
                            <tr>
                                <td><div class="ops-person"><span class="employee-avatar">{{ collect(explode(' ', $employee->name))->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('') }}</span><span class="employee-info"><strong>{{ $employee->name }}</strong><small>{{ $employee->position ?: 'Karyawan' }}{{ $employee->shift_name ? ' · '.$employee->shift_name : '' }}</small></span></div></td>
                                <td>{{ $employee->check_in_at ? \Illuminate\Support\Carbon::parse($employee->check_in_at)->format('H:i') : '—' }}</td>
                                <td>{{ $employee->check_out_at ? \Illuminate\Support\Carbon::parse($employee->check_out_at)->format('H:i') : '—' }}</td>
                                <td>{{ $duration !== null ? floor($duration / 60).'j '.($duration % 60).'m' : '—' }}</td>
                                <td><span class="ops-badge is-{{ $status }}">{{ ['present' => 'Hadir', 'late' => 'Terlambat', 'leave' => 'Cuti', 'absent' => 'Tidak hadir', 'unrecorded' => 'Belum hadir'][$status] ?? $status }}</span>@if ($employee->late_minutes > 0)<small class="ops-block">{{ $employee->late_minutes }} menit</small>@endif</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </article>
        <article class="dashboard-card ops-month-card">
            <div class="card-heading"><div><h2>Rekap bulanan</h2><p>{{ $date->locale('id')->translatedFormat('F Y') }} · seluruh tim</p></div></div>
            @php
                $denominator = max(1, (int) $monthSummary->recorded);
                $monthlyRate = $monthSummary->recorded > 0
                    ? round(((int) $monthSummary->present + (int) $monthSummary->late) / $denominator * 100, 1)
                    : 0;
            @endphp
            <div class="monthly-attendance-summary"><strong>{{ number_format($monthlyRate, 1, ',', '.') }}%</strong><span>{{ number_format((int) $monthSummary->recorded, 0, ',', '.') }} catatan absensi</span></div>
            <div class="attendance-month-stats">
                <div><span>Hadir</span><strong>{{ (int) $monthSummary->present }}</strong></div>
                <div><span>Terlambat</span><strong>{{ (int) $monthSummary->late }}</strong></div>
                <div><span>Cuti</span><strong>{{ (int) $monthSummary->leave_count }}</strong></div>
                <div><span>Tidak hadir</span><strong>{{ (int) $monthSummary->absent }}</strong></div>
            </div>
            <p class="ops-help">Persentase menunjukkan kehadiran (hadir dan terlambat) dibandingkan seluruh catatan absensi pada bulan ini.</p>
        </article>
    </section>
@endsection
