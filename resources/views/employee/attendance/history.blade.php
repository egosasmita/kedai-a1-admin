@extends('layouts.employee')

@section('page-title', 'Riwayat Absensi')
@section('breadcrumb', 'Riwayat')
@section('active', 'history')

@section('content')
    @php
        $statusLabels = ['present' => 'Hadir', 'late' => 'Terlambat', 'leave' => 'Cuti', 'absent' => 'Tidak hadir'];
    @endphp
    <section class="capture-page-heading history-page-heading">
        <span class="employee-eyebrow">ABSENSI PRIBADI · {{ mb_strtoupper(auth()->user()->name) }}</span>
        <h1>Riwayat Absensi</h1>
        <p>Lihat catatan kehadiranmu dari periode ke periode.</p>
    </section>
    <section class="employee-stats-grid history-stats">
        <article><span>Total catatan</span><strong>{{ (int) $summary->total }}</strong><small>periode terpilih</small></article>
        <article><span>Hadir</span><strong>{{ (int) $summary->present_count }}</strong><small>hari</small></article>
        <article><span>Terlambat</span><strong>{{ (int) $summary->late_count }}</strong><small>kali</small></article>
    </section>
    <section class="employee-history-page-card">
        <div class="employee-history-toolbar">
            <div><h2>Riwayat pribadi</h2><p>{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->locale('id')->translatedFormat('F Y') }}</p></div>
            <form method="GET" action="{{ route('employee.attendance.history') }}">
                <label><span class="sr-only">Bulan</span><input type="month" name="month" value="{{ $month }}"></label>
                <select name="status" aria-label="Filter status">
                    <option value="all">Semua status</option>
                    @foreach ($statusLabels as $status => $label)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ $label }}</option>
                    @endforeach
                </select>
                <button type="submit">Terapkan</button>
            </form>
        </div>
        @if ($attendances->isEmpty())
            <div class="employee-history-empty"><span>◷</span><strong>Belum ada riwayat absensi</strong><p>Catatan absensi untuk periode ini akan tampil di sini.</p></div>
        @else
            <div class="employee-history-table-scroll">
                <table class="employee-history-table">
                    <thead><tr><th>TANGGAL</th><th>SHIFT</th><th>JAM MASUK</th><th>JAM PULANG</th><th>DURASI</th><th>STATUS</th><th>BUKTI</th></tr></thead>
                    <tbody>
                    @foreach ($attendances as $attendance)
                        @php
                            $duration = $attendance->check_in_at && $attendance->check_out_at
                                ? \Illuminate\Support\Carbon::parse($attendance->check_in_at)->diffInMinutes(\Illuminate\Support\Carbon::parse($attendance->check_out_at))
                                : null;
                        @endphp
                        <tr>
                            <td><strong>{{ \Illuminate\Support\Carbon::parse($attendance->attendance_date)->locale('id')->translatedFormat('D, j M Y') }}</strong></td>
                            <td>{{ $attendance->shift_name ?? '—' }}</td>
                            <td>{{ $attendance->check_in_at ? \Illuminate\Support\Carbon::parse($attendance->check_in_at)->format('H:i') : '—' }}</td>
                            <td>{{ $attendance->check_out_at ? \Illuminate\Support\Carbon::parse($attendance->check_out_at)->format('H:i') : '—' }}</td>
                            <td>{{ $duration !== null ? floor($duration / 60).'j '.($duration % 60).'m' : '—' }}</td>
                            <td><span class="employee-status-pill is-{{ $attendance->status }}">{{ $statusLabels[$attendance->status] ?? $attendance->status }}</span>@if ($attendance->late_minutes > 0)<small class="employee-late-minutes">{{ $attendance->late_minutes }} menit</small>@endif</td>
                            <td><span class="employee-proof-label">{{ $attendance->check_in_photo_path && $attendance->check_in_latitude !== null ? '✓ Masuk' : '—' }} · {{ $attendance->check_out_photo_path && $attendance->check_out_latitude !== null ? '✓ Pulang' : '—' }}</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="employee-history-pagination"><span>Menampilkan {{ $attendances->firstItem() }}–{{ $attendances->lastItem() }} dari {{ $attendances->total() }} catatan</span>{{ $attendances->onEachSide(1)->links('pagination::simple-tailwind') }}</div>
        @endif
    </section>
@endsection
