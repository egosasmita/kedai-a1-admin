@props(['active' => 'dashboard'])

@php
    $shift = request()->attributes->get('sidebarShift', [
        'name' => 'Shift belum dijadwalkan',
        'starts_at' => null,
        'ends_at' => null,
        'employee_count' => 0,
    ]);
@endphp

<aside class="admin-sidebar">
    <a class="sidebar-brand" href="{{ route('dashboard') }}" aria-label="Kedai A1, dashboard">
        <span class="sidebar-brand-mark" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.35">
                <path d="M5 8h12v8a4 4 0 0 1-4 4H9a4 4 0 0 1-4-4V8Z" />
                <path d="M17 10h1.5a2.5 2.5 0 0 1 0 5H17M8 4v2m4-3v3" />
            </svg>
        </span>
        <span class="sidebar-brand-copy">
            <span>Kedai A1</span>
            <small>ADMIN KEDAI</small>
        </span>
    </a>

    <div class="sidebar-section-label">OPERASIONAL</div>
    <nav class="sidebar-navigation" aria-label="Navigasi admin">
        @php
            $navigationItems = [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'grid', 'href' => route('dashboard')],
                ['key' => 'menu', 'label' => 'Kelola Menu', 'icon' => 'menu', 'href' => route('admin.menus.index')],
                ['key' => 'stok', 'label' => 'Kelola Stok', 'icon' => 'box', 'href' => route('admin.stock.index')],
                ['key' => 'karyawan', 'label' => 'Data Karyawan', 'icon' => 'users', 'href' => route('admin.employees.index')],
                ['key' => 'absensi', 'label' => 'Absensi', 'icon' => 'calendar', 'href' => route('admin.attendance.index')],
                ['key' => 'penggajian', 'label' => 'Penggajian', 'icon' => 'wallet', 'href' => route('admin.payroll.index')],
                ['key' => 'pengaturan', 'label' => 'Pengaturan', 'icon' => 'settings', 'href' => '#pengaturan'],
            ];
        @endphp

        @foreach ($navigationItems as $item)
            <a
                class="sidebar-link {{ $active === $item['key'] ? 'is-active' : '' }}"
                href="{{ $item['href'] }}"
                @if ($active === $item['key']) aria-current="page" @endif
            >
                @switch($item['icon'])
                    @case('grid')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/></svg>
                        @break
                    @case('menu')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="6" y="3" width="12" height="18" rx="2"/><path d="M9 7h6M9 11h6M9 15h6M9 19h6"/></svg>
                        @break
                    @case('box')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 8 9 5 9-5M3 8v9l9 5 9-5V8M12 13v9"/></svg>
                        @break
                    @case('users')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3.5 20v-1.5a5.5 5.5 0 0 1 11 0V20M16 5.5a3 3 0 0 1 0 5.8M17 14a5 5 0 0 1 3.5 4.8V20"/></svg>
                        @break
                    @case('calendar')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="3.5" y="5" width="17" height="16" rx="2"/><path d="M7.5 3v4M16.5 3v4M4 9h16M8 13h3M8 17h2"/></svg>
                        @break
                    @case('wallet')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="3" y="5" width="18" height="15" rx="2"/><path d="M3 9h18M7 5V3h11v2M16 14h2"/></svg>
                        @break
                    @case('settings')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="m19.4 15 .1.1 1.1.9-1.2 2.1-1.4-.5a7.8 7.8 0 0 1-1.7 1l-.3 1.5h-2.4l-.3-1.5a7.8 7.8 0 0 1-1.8-1l-1.3.5L7 16l1.1-1a7 7 0 0 1 0-2l-1.1-1L8.2 10l1.4.5a7.8 7.8 0 0 1 1.7-1l.3-1.5H14l.3 1.5a7.8 7.8 0 0 1 1.8 1l1.3-.5 1.2 2.1-1.1 1a7 7 0 0 1-.1 1.9Z"/></svg>
                        @break
                @endswitch
                <span>{{ $item['label'] }}</span>
                @if ($active === $item['key'])
                    <span class="active-indicator" aria-hidden="true"></span>
                @endif
            </a>
        @endforeach
    </nav>

    <div class="sidebar-bottom">
        <section class="shift-card" aria-label="Informasi shift aktif">
            <span class="shift-label">{{ $shift['name'] }}</span>
            <strong>
                @if ($shift['starts_at'] && $shift['ends_at'])
                    {{ $shift['starts_at'] }} <span>—</span> {{ $shift['ends_at'] }}
                @else
                    —
                @endif
            </strong>
            <span class="shift-staff">{{ $shift['employee_count'] }} karyawan bertugas</span>
        </section>

        <div class="sidebar-profile">
            <span class="profile-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
            <span class="profile-copy">
                <strong>{{ auth()->user()->name }}</strong>
                <small>{{ auth()->user()->is_admin ? 'Pemilik Kedai' : 'Karyawan' }}</small>
            </span>
            <form class="sidebar-logout-form" method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="sidebar-logout-button" type="submit" aria-label="Keluar dari akun admin" title="Keluar">⇥</button>
            </form>
        </div>
    </div>
</aside>
