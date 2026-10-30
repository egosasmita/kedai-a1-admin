@extends('layouts.admin')

@section('page-title', 'Dashboard')
@section('page-subtitle', 'Ringkasan operasional Kedai A1 hari ini')
@section('active', 'dashboard')

@section('content')
    <section class="dashboard-welcome">
        <div>
            <h2>Selamat {{ $greeting }}, {{ explode(' ', trim(auth()->user()->name))[0] }}.</h2>
            <p>Ringkasan operasional berdasarkan data Kedai A1.</p>
        </div>
        <div class="welcome-actions">
            <a class="button button-light" href="#stock-health">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 8 9 5 9-5M3 8v9l9 5 9-5V8M12 13v9"/></svg>
                Lihat stok
            </a>
            <a class="button button-primary" href="{{ route('admin.menus.create') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                Tambah menu
            </a>
        </div>
    </section>

    <section class="stats-grid" aria-label="Ringkasan operasional">
        <x-admin.stat-card label="Total Menu" :value="$menuCount" :detail="$availableMenuCount.' tersedia'" icon="grid" />
        <x-admin.stat-card label="Total Karyawan" :value="$employeeCount" detail="Akun karyawan aktif" icon="users" />
        <x-admin.stat-card label="Absensi Hari Ini" :value="$todayAttendance" :detail="$todayAttendanceRate.'% dari karyawan'" icon="calendar" />
        <x-admin.stat-card label="Stok Menipis" :value="$lowStockCount" detail="Perlu tindakan" icon="warning" :dark="true" />
    </section>

    <section class="dashboard-grid dashboard-grid-attendance">
        <article class="dashboard-card attendance-card attendance-card-featured" id="absensi">
            <div class="card-heading">
                <div>
                    <h2>Absensi 7 hari</h2>
                    <p>{{ $weekStart->format('d M') }} — {{ $today->format('d M Y') }}</p>
                </div>
                <a class="text-link" href="#absensi">Detail <span aria-hidden="true">→</span></a>
            </div>

            <div class="attendance-summary">
                <strong>{{ $employeeCount > 0 ? $attendanceAverage.'%' : '—' }}</strong>
                @if ($employeeCount > 0)
                    <span class="trend-badge"><span aria-hidden="true">•</span> {{ $attendanceChange > 0 ? '+' : '' }}{{ $attendanceChange }}% vs 7 hari lalu</span>
                @endif
            </div>

            <svg class="attendance-chart" viewBox="0 0 500 170" role="img" aria-label="Grafik kehadiran tujuh hari terakhir">
                <path class="chart-gridline" d="M8 42H492M8 96H492M8 150H492"/>
                @if ($employeeCount > 0)
                    <path class="attendance-line" d="{{ $attendancePath }}"/>
                    @foreach ($attendancePoints as $point)
                        <circle class="chart-point" cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="4"/>
                    @endforeach
                @endif
            </svg>
            <div class="chart-days" aria-hidden="true">
                @foreach ($attendanceDays as $day)
                    <span>{{ $day['label'] }}</span>
                @endforeach
            </div>
        </article>
    </section>

    <section class="dashboard-grid dashboard-grid-support">
        <article class="dashboard-card stock-card" id="stock-health">
            <div class="card-heading">
                <div>
                    <h2>Kesehatan stok</h2>
                    <p>Persediaan dibanding batas minimum</p>
                </div>
                <a class="text-link" href="#stock-health">Total {{ $lowStockCount }} perlu restok <span aria-hidden="true">→</span></a>
            </div>

            <div class="stock-chart" role="img" aria-label="Persediaan enam bahan terdekat dengan batas minimum">
                @forelse ($stockItems as $stock)
                    <div class="stock-column">
                        <span class="stock-percent {{ $stock['state'] }}">{{ $stock['level'] }}%</span>
                        <div class="stock-track">
                            <span class="stock-fill {{ $stock['state'] }}" style="height: {{ $stock['level'] }}%"></span>
                        </div>
                        <span class="stock-name" title="{{ $stock['name'] }}">{{ \Illuminate\Support\Str::limit($stock['name'], 12) }}</span>
                        <span class="stock-quantity">{{ rtrim(rtrim(number_format($stock['quantity'], 3, ',', '.'), '0'), ',') }} {{ $stock['unit'] }}</span>
                    </div>
                @empty
                    <p class="dashboard-empty">Belum ada data bahan baku.</p>
                @endforelse
            </div>
        </article>

        <article class="dashboard-card activity-card" id="recent-activity">
            <div class="card-heading activity-heading">
                <div>
                    <h2>Aktivitas terbaru</h2>
                    <p>Absensi dan transaksi stok terbaru</p>
                </div>
                <a class="text-link" href="#absensi">Buka absensi <span aria-hidden="true">→</span></a>
            </div>
            <div class="activity-list">
                @forelse ($recentActivities as $activity)
                    <div class="activity-row">
                        <time datetime="{{ $activity['time']->toIso8601String() }}">{{ $activity['time']->isToday() ? $activity['time']->format('H.i') : $activity['time']->format('d.m') }}</time>
                        <span class="employee-avatar">{{ mb_strtoupper(mb_substr($activity['actor'], 0, 1)) }}</span>
                        <span class="employee-info"><strong>{{ $activity['actor'] }}</strong><small>{{ $activity['detail'] }}</small></span>
                        <span class="activity-status {{ $activity['status_class'] }}"><i></i> {{ $activity['status'] }}</span>
                    </div>
                @empty
                    <p class="dashboard-empty activity-empty">Belum ada aktivitas absensi atau stok.</p>
                @endforelse
            </div>
        </article>
    </section>

    <section class="dashboard-grid dashboard-grid-quick">
        <article class="dashboard-card quick-card">
            <div class="card-heading">
                <div>
                    <h2>Aksi cepat</h2>
                    <p>Jalan pintas operasional</p>
                </div>
            </div>
            <div class="quick-actions">
                <a class="quick-action" href="{{ route('admin.menus.create') }}"><span class="quick-icon">+</span>Tambah menu</a>
                <a class="quick-action" href="#stock-health"><span class="quick-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 8 9 5 9-5M3 8v9l9 5 9-5V8M12 13v9"/></svg></span>Lihat stok</a>
                <a class="quick-action" href="#recent-activity"><span class="quick-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path d="M9 12.2 11 14l4-4"/></svg></span>Aktivitas terbaru</a>
                <a class="quick-action" href="#absensi"><span class="quick-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="6" y="3" width="12" height="18" rx="2"/><path d="M9 7h6M9 11h6M9 15h2"/></svg></span>Rekap absensi</a>
            </div>
        </article>
    </section>
@endsection
