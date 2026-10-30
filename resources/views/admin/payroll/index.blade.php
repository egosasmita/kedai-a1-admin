@extends('layouts.admin')

@section('page-title', 'Penggajian')
@section('page-subtitle', 'Kelola perhitungan gaji berdasarkan periode dan komponen upah')
@section('active', 'penggajian')

@section('content')
    @if (session('status'))
        <div class="menu-flash" role="status">{{ session('status') }}</div>
    @endif
    <x-admin.validation-errors />
    <section class="ops-toolbar payroll-toolbar">
        <form class="menu-filters" action="{{ route('admin.payroll.index') }}" method="GET">
            <label class="menu-form-field payroll-filter"><span class="sr-only">Periode penggajian</span><input type="month" name="month" value="{{ $month }}"></label>
            <select name="status" aria-label="Filter status"><option value="all">Semua status</option><option value="draft" @selected(request('status') === 'draft')>Draft</option><option value="review" @selected(request('status') === 'review')>Perlu ditinjau</option><option value="ready" @selected(request('status') === 'ready')>Siap dibayar</option><option value="paid" @selected(request('status') === 'paid')>Dibayar</option></select>
            <button class="filter-submit" type="submit">Terapkan</button>
        </form>
        <form action="{{ route('admin.payroll.process') }}" method="POST">
            @csrf
            <input type="hidden" name="month" value="{{ $month }}">
            <button class="button button-primary" type="submit">◉ Proses semua gaji</button>
        </form>
    </section>
    <section class="stats-grid ops-stats payroll-stats" aria-label="Ringkasan penggajian">
        <article class="stat-card stat-card-dark"><div class="stat-topline"><span>Total penggajian</span><span class="stat-icon">Rp</span></div><div class="stat-bottomline"><strong>Rp {{ number_format((float) $totals->net_total, 0, ',', '.') }}</strong><span>{{ (int) $totals->employee_count }} karyawan</span></div></article>
        <article class="stat-card"><div class="stat-topline"><span>Total gaji pokok</span><span class="stat-icon">▤</span></div><div class="stat-bottomline"><strong>Rp {{ number_format((float) $totals->base_total, 0, ',', '.') }}</strong><span>periode terpilih</span></div></article>
        <article class="stat-card"><div class="stat-topline"><span>Tambahan & lembur</span><span class="stat-icon">＋</span></div><div class="stat-bottomline"><strong>Rp {{ number_format((float) $totals->additions_total, 0, ',', '.') }}</strong><span>komponen upah</span></div></article>
        <article class="stat-card"><div class="stat-topline"><span>Total potongan</span><span class="stat-icon">−</span></div><div class="stat-bottomline"><strong>Rp {{ number_format((float) $totals->deductions_total, 0, ',', '.') }}</strong><span>periode terpilih</span></div></article>
    </section>

    <section class="dashboard-card ops-table-card">
            <div class="menu-list-heading">
                <div><h2>Daftar penggajian</h2><p>{{ $period?->name ?? 'Periode belum diproses' }} · {{ $period ? \Illuminate\Support\Carbon::parse($period->starts_on)->format('d').'–'.\Illuminate\Support\Carbon::parse($period->ends_on)->locale('id')->translatedFormat('d F Y') : 'belum ada data' }}</p></div>
                @if ($period)<span class="ops-badge is-{{ $period->status }}">{{ ['draft' => 'Draft', 'calculated' => 'Dihitung', 'approved' => 'Disetujui', 'paid' => 'Lunas'][$period->status] ?? $period->status }}</span>@endif
            </div>
            @if ($payrolls->isEmpty())
                <div class="menu-empty-state"><span class="menu-empty-icon">Rp</span><h3>Belum ada perhitungan gaji</h3><p>Proses periode ini untuk menyiapkan daftar gaji karyawan aktif.</p></div>
            @else
                <div class="menu-table-scroll">
                    <table class="menu-table ops-table payroll-table">
                        <thead><tr><th>KARYAWAN</th><th>HADIR</th><th>GAJI POKOK</th><th>POTONGAN</th><th>GAJI AKHIR</th><th>STATUS</th></tr></thead>
                        <tbody>
                        @foreach ($payrolls as $payroll)
                            <tr @class(['is-selected' => $selectedPayroll && $selectedPayroll->id === $payroll->id])>
                                <td><a class="payroll-person-link" href="{{ route('admin.payroll.index', ['month' => $month, 'status' => request('status', 'all'), 'payroll' => $payroll->id]) }}" aria-haspopup="dialog"><div class="ops-person"><span class="employee-avatar">{{ collect(explode(' ', $payroll->name))->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('') }}</span><span class="employee-info"><strong>{{ $payroll->name }}</strong><small>{{ $payroll->position ?: 'Karyawan' }}</small></span></div></a></td>
                                <td>{{ $payroll->attendance_days }} hari</td>
                                <td>Rp {{ number_format((float) $payroll->base_salary, 0, ',', '.') }}</td>
                                <td class="payroll-deduction">Rp {{ number_format((float) $payroll->deductions, 0, ',', '.') }}</td>
                                <td><strong>Rp {{ number_format((float) $payroll->net_pay, 0, ',', '.') }}</strong></td>
                                <td><span class="ops-badge is-{{ $payroll->status }}">{{ ['draft' => 'Draft', 'review' => 'Tinjau', 'ready' => 'Siap', 'paid' => 'Dibayar'][$payroll->status] ?? $payroll->status }}</span></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="menu-pagination"><span>Menampilkan {{ $payrolls->firstItem() }}–{{ $payrolls->lastItem() }} dari {{ $payrolls->total() }} karyawan</span>{{ $payrolls->onEachSide(1)->links('pagination::simple-tailwind') }}</div>
            @endif
            <p class="payroll-footnote">Gaji pokok dan jumlah kehadiran diambil dari data karyawan serta absensi periode terpilih. Tambahkan komponen upah untuk memperbarui gaji akhir.</p>
    </section>

    @if ($selectedPayroll)
        <dialog class="ops-dialog" data-open-on-load aria-labelledby="payroll-detail-title">
            <section class="dashboard-card payroll-detail-card">
                <div class="card-heading">
                    <div><h2 id="payroll-detail-title">Rincian gaji</h2><p>{{ $period->name }} · #{{ $selectedPayroll->id }}</p></div>
                    <div class="ops-toolbar-actions">
                        <span class="ops-badge is-{{ $selectedPayroll->status }}">{{ ['draft' => 'Draft', 'review' => 'Perlu ditinjau', 'ready' => 'Siap dibayar', 'paid' => 'Dibayar'][$selectedPayroll->status] ?? $selectedPayroll->status }}</span>
                        <a class="editor-close" href="{{ route('admin.payroll.index', ['month' => $month, 'status' => request('status', 'all')]) }}" aria-label="Tutup rincian gaji">×</a>
                    </div>
                </div>
                <div class="payroll-employee-summary"><span class="employee-avatar">{{ collect(explode(' ', $selectedPayroll->name))->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('') }}</span><span><strong>{{ $selectedPayroll->name }}</strong><small>{{ $selectedPayroll->position ?: 'Karyawan' }} · {{ $selectedPayroll->attendance_days }} hari hadir</small></span></div>
                <div class="payroll-breakdown">
                    <h3>Perhitungan gaji</h3>
                    <div><span>Gaji pokok</span><strong>Rp {{ number_format((float) $selectedPayroll->base_salary, 0, ',', '.') }}</strong></div>
                    <div><span>Tambahan & lembur</span><strong>+ Rp {{ number_format((float) $selectedPayroll->overtime_amount + (float) $selectedPayroll->allowances + (float) $selectedPayroll->bonus, 0, ',', '.') }}</strong></div>
                    <div><span>Potongan</span><strong class="payroll-deduction">− Rp {{ number_format((float) $selectedPayroll->deductions, 0, ',', '.') }}</strong></div>
                    <div class="payroll-net"><span>Gaji akhir</span><strong>Rp {{ number_format((float) $selectedPayroll->net_pay, 0, ',', '.') }}</strong></div>
                </div>
                @if ($payrollItems->isNotEmpty())
                    <div class="payroll-components">
                        <h3>Komponen</h3>
                        @foreach ($payrollItems as $item)
                            <div><span>{{ $item->label }} <small>{{ ['allowance' => 'Tunjangan', 'bonus' => 'Bonus', 'overtime' => 'Lembur', 'deduction' => 'Potongan'][$item->type] }}</small></span><strong class="{{ $item->type === 'deduction' ? 'payroll-deduction' : '' }}">{{ $item->type === 'deduction' ? '−' : '+' }} Rp {{ number_format((float) $item->amount, 0, ',', '.') }}</strong></div>
                        @endforeach
                    </div>
                @endif
                @if ($selectedPayroll->status !== 'paid' && $period->status !== 'paid')
                    <form class="payroll-component-form" action="{{ route('admin.payroll.items.store', $selectedPayroll->id) }}" method="POST">
                        @csrf
                        <input type="hidden" name="month" value="{{ $month }}">
                        <h3>Tambah komponen gaji</h3>
                        <div class="menu-form-row">
                            <label class="menu-form-field"><span>Jenis</span><select name="type"><option value="allowance">Tunjangan</option><option value="bonus">Bonus</option><option value="overtime">Lembur</option><option value="deduction">Potongan</option></select></label>
                            <label class="menu-form-field"><span>Jumlah (Rp)</span><input type="number" name="amount" min="1" step="1000" required></label>
                        </div>
                        <label class="menu-form-field"><span>Keterangan</span><input name="label" maxlength="120" placeholder="Contoh: Tunjangan transport" required></label>
                        @error('amount')<small class="field-error">{{ $message }}</small>@enderror
                        <button class="button button-light" type="submit">Tambah komponen</button>
                    </form>
                    @if (in_array($selectedPayroll->status, ['review', 'ready'], true))
                        <form class="payroll-payment-form" action="{{ route('admin.payroll.pay', $selectedPayroll->id) }}" method="POST">
                            @csrf
                            <input type="hidden" name="month" value="{{ $month }}">
                            <label class="menu-form-field"><span>Metode pembayaran</span><select name="method"><option value="bank_transfer">Transfer bank</option><option value="cash">Tunai</option><option value="e_wallet">E-wallet</option><option value="other">Lainnya</option></select></label>
                            <label class="menu-form-field"><span>Referensi (opsional)</span><input name="reference" maxlength="120"></label>
                            <button class="button button-primary menu-save-button" type="submit">Konfirmasi pembayaran · Rp {{ number_format((float) $selectedPayroll->net_pay, 0, ',', '.') }}</button>
                        </form>
                    @endif
                @else
                    <div class="payroll-paid-note">Pembayaran tercatat. Rincian gaji tidak dapat diubah.</div>
                    @foreach ($payments as $payment)
                        <div class="payroll-payment-history"><span>{{ ['cash' => 'Tunai', 'bank_transfer' => 'Transfer bank', 'e_wallet' => 'E-wallet', 'other' => 'Lainnya'][$payment->method] }} · {{ \Illuminate\Support\Carbon::parse($payment->paid_at)->format('d M Y, H:i') }}</span><strong>Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}</strong></div>
                    @endforeach
                @endif
            </section>
        </dialog>
    @endif
@endsection
