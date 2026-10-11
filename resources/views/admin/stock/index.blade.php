@extends('layouts.admin')

@section('page-title', 'Kelola Stok')
@section('page-subtitle', 'Pantau bahan baku dan catat alur keluar-masuk persediaan')
@section('active', 'stok')

@section('content')
    @if (session('status'))
        <div class="menu-flash" role="status">{{ session('status') }}</div>
    @endif
    <x-admin.validation-errors />

    <section class="ops-toolbar">
        <form class="menu-filters" action="{{ route('admin.stock.index') }}" method="GET">
            <label class="menu-search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 5 5"/></svg>
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Cari bahan baku..." aria-label="Cari bahan baku">
            </label>
            <select name="condition" aria-label="Filter kondisi">
                <option value="">Semua kondisi</option>
                <option value="safe" @selected(request('condition') === 'safe')>Aman</option>
                <option value="low" @selected(request('condition') === 'low')>Menipis</option>
                <option value="empty" @selected(request('condition') === 'empty')>Habis</option>
            </select>
            <button class="filter-submit" type="submit">Terapkan</button>
        </form>
        <div class="ops-toolbar-actions">
            <details class="ops-popover">
                <summary class="button button-light">+ Bahan baku</summary>
                <form class="ops-form" action="{{ route('admin.stock.items.store') }}" method="POST">
                    @csrf
                    <h3>Tambah bahan baku</h3>
                    <label class="menu-form-field"><span>Nama bahan baku</span><input name="name" value="{{ old('name') }}" required maxlength="160">@error('name')<small class="field-error">{{ $message }}</small>@enderror</label>
                    <div class="menu-form-row">
                        <label class="menu-form-field"><span>Satuan</span><input name="unit" value="{{ old('unit') }}" placeholder="kg, liter, pcs" required maxlength="30">@error('unit')<small class="field-error">{{ $message }}</small>@enderror</label>
                        <label class="menu-form-field"><span>SKU (opsional)</span><input name="sku" value="{{ old('sku') }}" maxlength="50">@error('sku')<small class="field-error">{{ $message }}</small>@enderror</label>
                    </div>
                    <div class="menu-form-row">
                        <label class="menu-form-field"><span>Stok awal</span><input type="number" name="quantity" value="{{ old('quantity', 0) }}" min="0" step="0.001" required>@error('quantity')<small class="field-error">{{ $message }}</small>@enderror</label>
                        <label class="menu-form-field"><span>Batas minimum</span><input type="number" name="minimum_quantity" value="{{ old('minimum_quantity', 0) }}" min="0" step="0.001" required>@error('minimum_quantity')<small class="field-error">{{ $message }}</small>@enderror</label>
                    </div>
                    <label class="menu-form-field"><span>Biaya per satuan (Rp)</span><input type="number" name="unit_cost" value="{{ old('unit_cost', 0) }}" min="0" step="0.01" required>@error('unit_cost')<small class="field-error">{{ $message }}</small>@enderror</label>
                    <button class="button button-primary menu-save-button" type="submit">Simpan bahan</button>
                </form>
            </details>
            <details class="ops-popover">
                <summary class="button button-primary">Catat transaksi</summary>
                <form class="ops-form" action="{{ route('admin.stock.transactions.store') }}" method="POST">
                    @csrf
                    <h3>Transaksi stok</h3>
                    <label class="menu-form-field"><span>Bahan baku</span><select name="stock_item_id" required><option value="">Pilih bahan</option>@foreach ($stockOptions as $option)<option value="{{ $option->id }}" @selected((string) old('stock_item_id') === (string) $option->id)>{{ $option->name }} ({{ number_format((float) $option->quantity, 3, ',', '.') }} {{ $option->unit }})</option>@endforeach</select>@error('stock_item_id')<small class="field-error">{{ $message }}</small>@enderror</label>
                    <div class="menu-form-row">
                        <label class="menu-form-field"><span>Jenis</span><select name="type" required><option value="in" @selected(old('type') === 'in')>Stok masuk</option><option value="out" @selected(old('type') === 'out')>Pemakaian / keluar</option><option value="adjustment" @selected(old('type') === 'adjustment')>Penyesuaian stok</option></select></label>
                        <label class="menu-form-field"><span>Jumlah</span><input type="number" name="quantity" value="{{ old('quantity') }}" min="0.001" step="0.001" required>@error('quantity')<small class="field-error">{{ $message }}</small>@enderror</label>
                    </div>
                    <p class="ops-help">Untuk penyesuaian, masukkan jumlah stok akhir yang benar.</p>
                    <label class="menu-form-field"><span>Biaya per satuan baru (opsional)</span><input type="number" name="unit_cost" value="{{ old('unit_cost') }}" min="0" step="0.01"></label>
                    <label class="menu-form-field"><span>Referensi</span><input name="reference" value="{{ old('reference') }}" placeholder="Nomor nota atau pemakaian" maxlength="100"></label>
                    <label class="menu-form-field"><span>Catatan</span><textarea name="notes" maxlength="2000">{{ old('notes') }}</textarea></label>
                    <button class="button button-primary menu-save-button" type="submit">Simpan transaksi</button>
                </form>
            </details>
        </div>
    </section>

    <section class="stats-grid ops-stats" aria-label="Ringkasan persediaan">
        <article class="stat-card"><div class="stat-topline"><span>Total bahan baku</span><span class="stat-icon">◈</span></div><div class="stat-bottomline"><strong>{{ number_format($itemCount, 0, ',', '.') }}</strong><span>item aktif</span></div></article>
        <article class="stat-card"><div class="stat-topline"><span>Nilai persediaan</span><span class="stat-icon">Rp</span></div><div class="stat-bottomline"><strong>Rp {{ number_format((float) $stockValue, 0, ',', '.') }}</strong><span>berdasarkan biaya satuan</span></div></article>
        <article class="stat-card"><div class="stat-topline"><span>Stok aman</span><span class="stat-icon">✓</span></div><div class="stat-bottomline"><strong>{{ number_format($safeStockCount, 0, ',', '.') }}</strong><span>dari {{ $itemCount }} bahan</span></div></article>
        <article class="stat-card stat-card-dark"><div class="stat-topline"><span>Perlu restok</span><span class="stat-icon">!</span></div><div class="stat-bottomline"><strong>{{ number_format($lowStockCount, 0, ',', '.') }}</strong><span>di bawah batas minimum</span></div></article>
    </section>

    <section class="ops-two-column">
        <article class="dashboard-card ops-table-card">
            <div class="menu-list-heading">
                <div><h2>Daftar bahan baku</h2><p>Stok diperbarui otomatis setiap transaksi</p></div>
                @if (request()->hasAny(['search', 'condition']))<a class="menu-clear-filter" href="{{ route('admin.stock.index') }}">Hapus filter</a>@endif
            </div>
            @if ($items->isEmpty())
                <div class="menu-empty-state"><span class="menu-empty-icon">◈</span><h3>Belum ada bahan baku</h3><p>Tambahkan persediaan pertama untuk mulai memantau stok.</p></div>
            @else
                <div class="menu-table-scroll">
                    <table class="menu-table ops-table">
                        <thead><tr><th>BAHAN BAKU</th><th>STOK SAAT INI</th><th>MINIMUM</th><th>NILAI</th><th>KONDISI</th></tr></thead>
                        <tbody>
                        @foreach ($items as $item)
                            @php
                                $quantity = (float) $item->quantity;
                                $minimum = (float) $item->minimum_quantity;
                                $condition = $quantity <= 0 ? 'empty' : ($quantity <= $minimum ? 'low' : 'safe');
                                $ratio = $minimum > 0 ? min(100, (int) round(($quantity / ($minimum * 1.5)) * 100)) : ($quantity > 0 ? 100 : 0);
                            @endphp
                            <tr>
                                <td><div class="ops-stock-name"><strong>{{ $item->name }}</strong><small>{{ $item->sku ?: 'Tanpa SKU' }}</small><span class="ops-stock-track"><i class="level-{{ $condition }}" style="width: {{ $ratio }}%"></i></span></div></td>
                                <td><strong class="ops-number">{{ number_format($quantity, 3, ',', '.') }}</strong> <small>{{ $item->unit }}</small></td>
                                <td>{{ number_format($minimum, 3, ',', '.') }} {{ $item->unit }}</td>
                                <td>Rp {{ number_format($quantity * (float) $item->unit_cost, 0, ',', '.') }}</td>
                                <td><span class="ops-badge is-{{ $condition }}">{{ ['safe' => 'Aman', 'low' => 'Menipis', 'empty' => 'Habis'][$condition] }}</span></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="menu-pagination"><span>Menampilkan {{ $items->firstItem() }}–{{ $items->lastItem() }} dari {{ $items->total() }} bahan</span>{{ $items->onEachSide(1)->links('pagination::simple-tailwind') }}</div>
            @endif
        </article>

        <article class="dashboard-card ops-history-card">
            <div class="card-heading"><div><h2>Riwayat stok</h2><p>Transaksi terbaru</p></div><form method="GET" action="{{ route('admin.stock.index') }}"><input type="hidden" name="search" value="{{ request('search') }}"><input type="hidden" name="condition" value="{{ request('condition') }}"><select class="ops-compact-select" name="history" aria-label="Filter riwayat" onchange="this.form.submit()"><option value="all">Semua</option><option value="in" @selected(request('history') === 'in')>Masuk</option><option value="out" @selected(request('history') === 'out')>Keluar</option></select></form></div>
            @forelse ($transactions as $transaction)
                <div class="ops-history-row">
                    <span class="ops-history-icon type-{{ $transaction->type }}">{{ $transaction->type === 'in' ? '↙' : ($transaction->type === 'out' ? '↗' : '↻') }}</span>
                    <span class="ops-history-copy"><strong>{{ $transaction->item_name }}</strong><small>{{ $transaction->type === 'in' ? 'Stok masuk' : ($transaction->type === 'out' ? 'Pemakaian stok' : 'Penyesuaian') }} · {{ \Illuminate\Support\Carbon::parse($transaction->occurred_at)->format('d M, H:i') }}</small></span>
                    <strong class="ops-history-amount {{ $transaction->type === 'in' ? 'positive' : 'negative' }}">{{ $transaction->type === 'out' ? '−' : '+' }}{{ number_format((float) $transaction->quantity, 3, ',', '.') }} {{ $transaction->unit }}</strong>
                </div>
            @empty
                <p class="dashboard-empty">Belum ada transaksi stok.</p>
            @endforelse
        </article>
    </section>
@endsection
