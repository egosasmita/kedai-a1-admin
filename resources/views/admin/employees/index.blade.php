@extends('layouts.admin')

@section('page-title', 'Data Karyawan')
@section('page-subtitle', 'Kelola profil, jabatan, dan status kerja tim Kedai A1')
@section('active', 'karyawan')

@section('content')
    @if (session('status'))
        <div class="menu-flash" role="status">{{ session('status') }}</div>
    @endif
    <x-admin.validation-errors />
    <section class="ops-toolbar">
        <form class="menu-filters" action="{{ route('admin.employees.index') }}" method="GET">
            <label class="menu-search"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 5 5"/></svg><input type="search" name="search" value="{{ request('search') }}" placeholder="Cari nama, jabatan, atau email..." aria-label="Cari karyawan"></label>
            <select name="status" aria-label="Filter status"><option value="">Semua status</option><option value="active" @selected(request('status') === 'active')>Aktif</option><option value="inactive" @selected(request('status') === 'inactive')>Nonaktif</option><option value="resigned" @selected(request('status') === 'resigned')>Resign</option></select>
            <button class="filter-submit" type="submit">Cari</button>
        </form>
        <a class="button button-primary" href="#form-karyawan">+ Tambah karyawan</a>
    </section>
    <section class="stats-grid ops-stats" aria-label="Ringkasan karyawan">
        <article class="stat-card"><div class="stat-topline"><span>Total karyawan</span><span class="stat-icon">♙</span></div><div class="stat-bottomline"><strong>{{ number_format((int) $counts->total, 0, ',', '.') }}</strong><span>terdaftar</span></div></article>
        <article class="stat-card"><div class="stat-topline"><span>Karyawan aktif</span><span class="stat-icon">✓</span></div><div class="stat-bottomline"><strong>{{ number_format((int) $counts->active, 0, ',', '.') }}</strong><span>siap bertugas</span></div></article>
        <article class="stat-card"><div class="stat-topline"><span>Nonaktif</span><span class="stat-icon">–</span></div><div class="stat-bottomline"><strong>{{ number_format((int) $counts->inactive, 0, ',', '.') }}</strong><span>sementara</span></div></article>
        <article class="stat-card stat-card-dark"><div class="stat-topline"><span>Resign</span><span class="stat-icon">↗</span></div><div class="stat-bottomline"><strong>{{ number_format((int) $counts->resigned, 0, ',', '.') }}</strong><span>akun nonaktif permanen</span></div></article>
    </section>

    <section class="ops-two-column employee-workspace">
        <article class="dashboard-card ops-table-card">
            <div class="menu-list-heading"><div><h2>Daftar karyawan</h2><p>{{ $employees->total() }} akun karyawan</p></div></div>
            @if ($employees->isEmpty())
                <div class="menu-empty-state"><span class="menu-empty-icon">♙</span><h3>Belum ada karyawan</h3><p>Buat profil untuk menambahkan anggota tim Kedai A1.</p></div>
            @else
                <div class="menu-table-scroll">
                    <table class="menu-table ops-table employee-table">
                        <thead><tr><th>KARYAWAN</th><th>ID / JABATAN</th><th>BERGABUNG</th><th>GAJI POKOK</th><th>STATUS</th></tr></thead>
                        <tbody>
                        @foreach ($employees as $employee)
                            @php
                                $status = $employee->employment_status ?: 'active';
                                $initials = collect(explode(' ', trim($employee->name)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
                            @endphp
                            <tr>
                                <td><div class="ops-person"><span class="employee-avatar">{{ $initials }}</span><span class="employee-info"><strong>{{ $employee->name }}</strong><small>{{ $employee->email }}</small></span></div></td>
                                <td><strong>{{ $employee->employee_number ?: '—' }}</strong><small class="ops-block">{{ $employee->position ?: 'Profil belum dilengkapi' }}</small></td>
                                <td>{{ $employee->hired_at ? \Illuminate\Support\Carbon::parse($employee->hired_at)->locale('id')->translatedFormat('d M Y') : '—' }}</td>
                                <td>{{ $employee->base_salary !== null ? 'Rp '.number_format((float) $employee->base_salary, 0, ',', '.') : '—' }}</td>
                                <td><span class="ops-badge is-{{ $status }}">{{ ['active' => 'Aktif', 'inactive' => 'Nonaktif', 'resigned' => 'Resign'][$status] ?? $status }}</span></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="menu-pagination"><span>Menampilkan {{ $employees->firstItem() }}–{{ $employees->lastItem() }} dari {{ $employees->total() }}</span>{{ $employees->onEachSide(1)->links('pagination::simple-tailwind') }}</div>
            @endif
        </article>

        <aside class="dashboard-card ops-editor-card" id="form-karyawan">
            <div class="menu-editor-heading"><div><h2>Tambah karyawan</h2><p>Akun login baru dengan akses karyawan</p></div></div>
            <form class="menu-editor-form" action="{{ route('admin.employees.store') }}" method="POST">
                @csrf
                <label class="menu-form-field"><span>Nama lengkap</span><input name="name" value="{{ old('name') }}" autocomplete="name" required>@error('name')<small class="field-error">{{ $message }}</small>@enderror</label>
                <label class="menu-form-field"><span>Email login</span><input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required>@error('email')<small class="field-error">{{ $message }}</small>@enderror</label>
                <div class="menu-form-row">
                    <label class="menu-form-field"><span>Jabatan</span><input name="position" value="{{ old('position') }}" placeholder="Barista" required maxlength="100">@error('position')<small class="field-error">{{ $message }}</small>@enderror</label>
                    <label class="menu-form-field"><span>ID karyawan</span><input name="employee_number" value="{{ old('employee_number') }}" maxlength="30">@error('employee_number')<small class="field-error">{{ $message }}</small>@enderror</label>
                </div>
                <div class="menu-form-row">
                    <label class="menu-form-field"><span>Tanggal bergabung</span><input type="date" name="hired_at" value="{{ old('hired_at') }}" max="{{ today()->toDateString() }}">@error('hired_at')<small class="field-error">{{ $message }}</small>@enderror</label>
                    <label class="menu-form-field"><span>Gaji pokok (Rp)</span><input type="number" name="base_salary" value="{{ old('base_salary') }}" min="0" step="1000" required>@error('base_salary')<small class="field-error">{{ $message }}</small>@enderror</label>
                </div>
                <label class="menu-form-field"><span>Kata sandi sementara</span><input type="password" name="password" autocomplete="new-password" minlength="12" required><small class="menu-field-hint">Minimal 12 karakter. Bagikan secara aman kepada karyawan.</small>@error('password')<small class="field-error">{{ $message }}</small>@enderror</label>
                <label class="menu-form-field"><span>Ulangi kata sandi</span><input type="password" name="password_confirmation" autocomplete="new-password" minlength="12" required></label>
                <button class="button button-primary menu-save-button" type="submit">Simpan karyawan</button>
            </form>
        </aside>
    </section>
@endsection
