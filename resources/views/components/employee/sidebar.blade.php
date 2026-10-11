@props(['active' => 'home'])

@php
    $profile = \Illuminate\Support\Facades\DB::table('employee_profiles')->where('user_id', auth()->id())->first();
    $shift = \Illuminate\Support\Facades\DB::table('attendances')
        ->join('shifts', 'shifts.id', '=', 'attendances.shift_id')
        ->where('attendances.user_id', auth()->id())
        ->whereDate('attendances.attendance_date', today())
        ->first(['shifts.name', 'shifts.starts_at', 'shifts.ends_at']);
    if ($shift === null) {
        $currentTime = now()->format('H:i:s');
        $shift = \Illuminate\Support\Facades\DB::table('shifts')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->first(fn (object $candidate): bool => $candidate->starts_at <= $candidate->ends_at
                ? $currentTime >= $candidate->starts_at && $currentTime < $candidate->ends_at
                : $currentTime >= $candidate->starts_at || $currentTime < $candidate->ends_at);
    }
    $items = [
        ['key' => 'home', 'label' => 'Beranda', 'icon' => '⌂', 'route' => 'employee.dashboard'],
        ['key' => 'check-in', 'label' => 'Absen Masuk', 'icon' => '↪', 'route' => 'employee.attendance.check-in'],
        ['key' => 'check-out', 'label' => 'Absen Pulang', 'icon' => '⇥', 'route' => 'employee.attendance.check-out'],
        ['key' => 'history', 'label' => 'Riwayat', 'icon' => '◷', 'route' => 'employee.attendance.history'],
    ];
@endphp

<aside class="employee-sidebar">
    <a class="employee-brand" href="{{ route('employee.dashboard') }}">
        <span class="employee-brand-mark" aria-hidden="true">☕</span>
        <span><strong>Kedai A1</strong><small>PORTAL KARYAWAN</small></span>
    </a>
    <span class="employee-nav-label">RUANG KERJA SAYA</span>
    <nav class="employee-nav" aria-label="Navigasi karyawan">
        @foreach ($items as $item)
            <a class="employee-nav-link {{ $active === $item['key'] ? 'is-active' : '' }}" href="{{ route($item['route']) }}" @if ($active === $item['key']) aria-current="page" @endif>
                <span aria-hidden="true">{{ $item['icon'] }}</span>{{ $item['label'] }}
            </a>
        @endforeach
    </nav>
    <div class="employee-sidebar-bottom">
        <section class="employee-shift-card">
            <span>{{ $shift?->name ?? 'JADWAL SHIFT' }}</span>
            <strong>{{ $shift ? \Illuminate\Support\Carbon::parse($shift->starts_at)->format('H:i').' — '.\Illuminate\Support\Carbon::parse($shift->ends_at)->format('H:i') : 'Belum dijadwalkan' }}</strong>
            <small>Kedai A1 · {{ config('app.locale') === 'id' ? 'Kediri' : 'Kediri' }}</small>
        </section>
        <div class="employee-profile-row">
            <span class="employee-sidebar-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
            <span><strong>{{ auth()->user()->name }}</strong><small>{{ $profile?->position ?? 'Karyawan' }}{{ $profile?->employee_number ? ' • '.$profile->employee_number : '' }}</small></span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" aria-label="Keluar dari akun karyawan" title="Keluar">⇥</button>
            </form>
        </div>
    </div>
</aside>
