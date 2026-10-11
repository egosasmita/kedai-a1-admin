@extends('layouts.employee')

@section('page-title', 'Dashboard Karyawan')
@section('breadcrumb', 'Dashboard Karyawan')
@section('active', 'home')

@section('content')
    @php
        $initials = collect(explode(' ', trim(auth()->user()->name)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
        $statusLabels = ['present' => 'Hadir', 'late' => 'Terlambat', 'leave' => 'Cuti', 'absent' => 'Tidak hadir'];
        $statusClass = $attendance?->status ?? 'unrecorded';
        $workHours = intdiv($workMinutes, 60);
        $remainingMinutes = $workMinutes % 60;
    @endphp
    <section class="employee-welcome">
        <div class="employee-greeting-avatar">{{ $initials }}</div>
        <div>
            <span class="employee-eyebrow">SELAMAT {{ now()->hour < 11 ? 'PAGI' : (now()->hour < 15 ? 'SIANG' : 'SORE') }}</span>
            <h1>{{ auth()->user()->name }}</h1>
            <p>{{ $profile?->position ?? 'Karyawan' }}{{ $profile?->employee_number ? ' • '.$profile->employee_number : '' }}</p>
        </div>
        @if ($attendance?->shift_name || $currentShift)
            <span class="employee-shift-chip">● {{ $attendance?->shift_name ?? $currentShift->name }}</span>
        @endif
    </section>
    <p class="employee-now">{{ now()->locale('id')->translatedFormat('l, j F Y') }} · {{ now()->format('H:i') }} WIB</p>

    <section class="employee-dashboard-top">
        <article class="employee-today-card">
            <div class="employee-today-topline"><span>STATUS HARI INI</span><span aria-hidden="true">◉</span></div>
            <h2>{{ $attendance ? ($statusLabels[$attendance->status] ?? 'Absensi tercatat') : 'Belum Absen' }}</h2>
            <p>
                @if (! $attendance)
                    Siap memulai hari? Catat kehadiran sebelum mulai bekerja.
                @elseif (! $attendance->check_out_at)
                    Absen masuk tercatat. Jangan lupa absen pulang setelah shift selesai.
                @else
                    Terima kasih, absensi hari ini sudah lengkap.
                @endif
            </p>
            <div class="employee-time-grid">
                <div><span>Jam masuk</span><strong>{{ $attendance?->check_in_at ? \Illuminate\Support\Carbon::parse($attendance->check_in_at)->format('H:i') : '—' }}</strong></div>
                <div><span>Jam pulang</span><strong>{{ $attendance?->check_out_at ? \Illuminate\Support\Carbon::parse($attendance->check_out_at)->format('H:i') : '—' }}</strong></div>
                <div><span>Durasi</span><strong>{{ $attendance?->check_in_at && $attendance?->check_out_at ? floor(\Illuminate\Support\Carbon::parse($attendance->check_in_at)->diffInMinutes(\Illuminate\Support\Carbon::parse($attendance->check_out_at)) / 60).'j '.(\Illuminate\Support\Carbon::parse($attendance->check_in_at)->diffInMinutes(\Illuminate\Support\Carbon::parse($attendance->check_out_at)) % 60).'m' : '—' }}</strong></div>
            </div>
            <div class="employee-today-footer">
                @if ($attendance?->shift_name)
                    {{ $attendance->shift_name }} · {{ \Illuminate\Support\Carbon::parse($attendance->starts_at)->format('H:i') }}–{{ \Illuminate\Support\Carbon::parse($attendance->ends_at)->format('H:i') }} WIB
                @elseif ($currentShift)
                    {{ $currentShift->name }} · {{ \Illuminate\Support\Carbon::parse($currentShift->starts_at)->format('H:i') }}–{{ \Illuminate\Support\Carbon::parse($currentShift->ends_at)->format('H:i') }} WIB
                @else
                    Shift hari ini belum ditentukan
                @endif
                @if ($attendance?->status === 'late' && $attendance->late_minutes > 0)
                    <span class="employee-late-label">Terlambat {{ $attendance->late_minutes }} menit</span>
                @endif
            </div>
        </article>
        <article class="employee-quick-card">
            <h2>Shift saya hari ini</h2>
            <div class="employee-shift-detail"><span>Jadwal</span><strong>{{ $attendance?->shift_name ?? $currentShift?->name ?? 'Belum dijadwalkan' }}</strong></div>
            <div class="employee-shift-detail"><span>Waktu</span><strong>{{ $attendance?->starts_at && $attendance?->ends_at ? \Illuminate\Support\Carbon::parse($attendance->starts_at)->format('H:i').'–'.\Illuminate\Support\Carbon::parse($attendance->ends_at)->format('H:i').' WIB' : ($currentShift ? \Illuminate\Support\Carbon::parse($currentShift->starts_at)->format('H:i').'–'.\Illuminate\Support\Carbon::parse($currentShift->ends_at)->format('H:i').' WIB' : '—') }}</strong></div>
            <h3>Aksi cepat</h3>
            @if (! $attendance)
                <a class="employee-action-link" href="{{ route('employee.attendance.check-in') }}">↪ <span>Absen Masuk</span></a>
                <button class="employee-action-link is-disabled" type="button" disabled>⇥ <span>Absen Pulang</span></button>
                <small>Absen pulang tersedia setelah Absen Masuk.</small>
            @elseif (! $attendance->check_out_at)
                <span class="employee-action-complete">✓ <span>Absen Masuk tercatat</span></span>
                <a class="employee-action-link" href="{{ route('employee.attendance.check-out') }}">⇥ <span>Absen Pulang</span></a>
                <small>Pastikan foto dan lokasi tersedia sebelum menyimpan.</small>
            @else
                <span class="employee-action-complete">✓ <span>Absensi hari ini lengkap</span></span>
                <a class="employee-action-link is-secondary" href="{{ route('employee.attendance.history') }}">◷ <span>Lihat Riwayat</span></a>
            @endif
        </article>
    </section>

    <section class="employee-month-section">
        <div class="employee-section-heading"><h2>Bulan ini</h2><span>{{ now()->locale('id')->translatedFormat('F Y') }}</span></div>
        <div class="employee-stats-grid">
            <article><span>Total hadir bulan ini</span><strong>{{ $monthCount }}</strong><small>hari</small></article>
            <article><span>Total terlambat</span><strong>{{ $monthLateCount }}</strong><small>kali</small></article>
            <article><span>Jam kerja bulan ini</span><strong>{{ $workHours }}j {{ $remainingMinutes }}m</strong><small>{{ now()->locale('id')->translatedFormat('F') }}</small></article>
        </div>
    </section>

    <section class="employee-bottom-grid">
        <article class="employee-history-card">
            <div class="employee-section-heading"><div><h2>Riwayat pribadi</h2><p>{{ $recentAttendances->first() ? 'Terakhir hadir · '.\Illuminate\Support\Carbon::parse($recentAttendances->first()->attendance_date)->locale('id')->translatedFormat('l, j F Y') : 'Belum ada catatan absensi' }}</p></div><a href="{{ route('employee.attendance.history') }}" aria-label="Lihat semua riwayat">↗</a></div>
            @forelse ($recentAttendances->take(1) as $recent)
                <div class="employee-recent-punch"><span>Masuk → Pulang</span><strong>{{ $recent->check_in_at ? \Illuminate\Support\Carbon::parse($recent->check_in_at)->format('H:i') : '—' }} → {{ $recent->check_out_at ? \Illuminate\Support\Carbon::parse($recent->check_out_at)->format('H:i') : '—' }}</strong></div>
                <div class="employee-recent-punch"><span>Durasi kerja</span><strong>{{ $recent->check_in_at && $recent->check_out_at ? floor(\Illuminate\Support\Carbon::parse($recent->check_in_at)->diffInMinutes(\Illuminate\Support\Carbon::parse($recent->check_out_at)) / 60).'j '.(\Illuminate\Support\Carbon::parse($recent->check_in_at)->diffInMinutes(\Illuminate\Support\Carbon::parse($recent->check_out_at)) % 60).'m' : 'Belum selesai' }}</strong></div>
                <div class="employee-history-card-footer"><span class="employee-status-pill is-{{ $recent->status }}">{{ $statusLabels[$recent->status] ?? $recent->status }}</span><a href="{{ route('employee.attendance.history') }}">Lihat riwayat →</a></div>
            @empty
                <p class="employee-empty">Setelah melakukan absensi, ringkasan terbarumu akan tampil di sini.</p>
            @endforelse
        </article>
        <article class="employee-quote-card">
            <span class="employee-quote-icon">☕</span>
            <strong>Hari baik dimulai dari kita.</strong>
            <p>Terima kasih sudah menjadi bagian dari Kedai A1.</p>
        </article>
    </section>
@endsection
