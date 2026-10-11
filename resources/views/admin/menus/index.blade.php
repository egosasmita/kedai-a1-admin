@extends('layouts.admin')

@section('page-title', 'Kelola Menu')
@section('page-subtitle', 'Atur katalog, harga, kategori, foto, dan ketersediaan menu')
@section('active', 'menu')

@section('content')
    @php
        $isEditing = $editingMenu !== null;
        $isCreating = request()->routeIs('admin.menus.create');
        $showForm = $isEditing || $isCreating;
    @endphp

    @if (session('status'))
        <div class="menu-flash" role="status">{{ session('status') }}</div>
    @endif

    <section class="menu-toolbar" aria-label="Pencarian dan filter menu">
        <form class="menu-filters" action="{{ route('admin.menus.index') }}" method="GET">
            <label class="menu-search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 5 5"/></svg>
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Cari nama atau kode menu..." aria-label="Cari menu">
            </label>
            <select name="category" aria-label="Filter kategori" onchange="this.form.submit()">
                <option value="">Semua kategori</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) request('category') === (string) $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <select name="status" aria-label="Filter status" onchange="this.form.submit()">
                <option value="">Semua status</option>
                <option value="available" @selected(request('status') === 'available')>Tersedia</option>
                <option value="unavailable" @selected(request('status') === 'unavailable')>Habis</option>
            </select>
            <button class="filter-submit" type="submit">Cari</button>
        </form>
        <a class="button button-primary menu-add-button" href="{{ route('admin.menus.create') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            Tambah menu
        </a>
    </section>

    <section class="menu-workspace">
        <article class="dashboard-card menu-list-card">
            <div class="menu-list-heading">
                <div>
                    <h2>Daftar menu</h2>
                    <p>{{ $menuCount }} menu <span aria-hidden="true">•</span> diperbarui {{ now()->diffForHumans() }}</p>
                </div>
                @if (request()->hasAny(['search', 'category', 'status']))
                    <a class="menu-clear-filter" href="{{ route('admin.menus.index') }}">Hapus filter</a>
                @endif
            </div>

            @if ($menus->isEmpty())
                <div class="menu-empty-state">
                    <span class="menu-empty-icon" aria-hidden="true">☕</span>
                    <h3>Menu belum ditemukan</h3>
                    <p>Coba ubah kata pencarian atau filter, atau tambahkan menu baru.</p>
                    <a class="button button-primary" href="{{ route('admin.menus.create') }}">Tambah menu</a>
                </div>
            @else
                <div class="menu-table-scroll">
                    <table class="menu-table">
                        <thead>
                            <tr>
                                <th scope="col">MENU</th>
                                <th scope="col">KATEGORI</th>
                                <th scope="col">HARGA</th>
                                <th scope="col">STATUS</th>
                                <th scope="col">TAG</th>
                                <th scope="col"><span class="sr-only">Aksi</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($menus as $menu)
                                @php
                                    $discountActive = $menu->discount_price !== null
                                        && ($menu->discount_end_time === null || $menu->discount_end_time->isFuture());
                                    $imageUrl = $menu->image_url
                                        ? (\Illuminate\Support\Str::startsWith($menu->image_url, ['http://', 'https://']) ? $menu->image_url : \Illuminate\Support\Facades\Storage::disk('public')->url($menu->image_url))
                                        : null;
                                @endphp
                                <tr @class(['is-selected' => $editingMenu?->is($menu)])>
                                    <td>
                                        <div class="menu-item-cell">
                                            @if ($imageUrl)
                                                <img class="menu-thumbnail" src="{{ $imageUrl }}" alt="" loading="lazy">
                                            @else
                                                <span class="menu-thumbnail menu-thumbnail-placeholder" aria-hidden="true">☕</span>
                                            @endif
                                            <span class="menu-item-copy">
                                                <strong>{{ $menu->name }}</strong>
                                                <small>{{ $menu->slug }}</small>
                                            </span>
                                        </div>
                                    </td>
                                    <td>{{ $menu->category?->name ?? 'Tanpa kategori' }}</td>
                                    <td>
                                        <span class="menu-price {{ $discountActive ? 'has-discount' : '' }}">Rp {{ number_format((float) ($discountActive ? $menu->discount_price : $menu->price), 0, ',', '.') }}</span>
                                        @if ($discountActive)
                                            <small class="menu-old-price">Rp {{ number_format((float) $menu->price, 0, ',', '.') }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="menu-status {{ $menu->is_available ? 'is-available' : 'is-unavailable' }}">
                                            <i></i>{{ $menu->is_available ? 'Tersedia' : 'Habis' }}
                                        </span>
                                    </td>
                                    <td>{{ $menu->tag ?: '—' }}</td>
                                    <td>
                                        <a class="menu-row-action" href="{{ route('admin.menus.edit', $menu) }}" aria-label="Edit {{ $menu->name }}">···</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="menu-pagination">
                    <span>Menampilkan {{ $menus->firstItem() }}–{{ $menus->lastItem() }} dari {{ $menus->total() }} menu</span>
                    {{ $menus->onEachSide(1)->links('pagination::simple-tailwind') }}
                </div>
            @endif
        </article>

        @if ($showForm)
            @php
                $formAction = $isEditing ? route('admin.menus.update', $editingMenu) : route('admin.menus.store');
                $currentImage = $editingMenu?->image_url
                    ? (\Illuminate\Support\Str::startsWith($editingMenu->image_url, ['http://', 'https://']) ? $editingMenu->image_url : \Illuminate\Support\Facades\Storage::disk('public')->url($editingMenu->image_url))
                    : null;
            @endphp
            <aside class="dashboard-card menu-editor-card">
                <div class="menu-editor-heading">
                    <div>
                        <h2>{{ $isEditing ? 'Edit menu' : 'Tambah menu' }}</h2>
                        <p>{{ $isEditing ? 'Perubahan disimpan setelah dikonfirmasi' : 'Lengkapi detail menu baru' }}</p>
                    </div>
                    <a class="editor-close" href="{{ route('admin.menus.index') }}" aria-label="Tutup form">×</a>
                </div>

                <form class="menu-editor-form" action="{{ $formAction }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @if ($isEditing)
                        @method('PUT')
                    @endif

                    <label class="menu-image-upload" for="menu-image">
                        <img
                            id="menu-image-preview"
                            src="{{ $currentImage ?: '' }}"
                            alt="Pratinjau foto menu"
                            @if (! $currentImage) hidden @endif
                        >
                        <span class="menu-image-placeholder" id="menu-image-placeholder" @if ($currentImage) hidden @endif>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
                            <strong>Pilih foto menu</strong>
                            <small>JPG, PNG, atau WEBP · maks. 4 MB</small>
                        </span>
                        <span class="menu-image-change">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 16V4m-4 4 4-4 4 4"/><path d="M4 16v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/></svg>
                            {{ $currentImage ? 'Ganti foto' : 'Pilih foto' }}
                        </span>
                        <input id="menu-image" class="sr-only" type="file" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                    </label>
                    @error('image') <p class="field-error">{{ $message }}</p> @enderror

                    <label class="menu-form-field">
                        <span>NAMA MENU <b>*</b></span>
                        <input type="text" name="name" value="{{ old('name', $editingMenu?->name) }}" maxlength="160" placeholder="Contoh: Aren Latte" required>
                        @error('name') <small class="field-error">{{ $message }}</small> @enderror
                    </label>

                    <div class="menu-form-row">
                        <label class="menu-form-field">
                            <span>KATEGORI <b>*</b></span>
                            <select name="category_id" required>
                                <option value="">Pilih kategori</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" @selected((string) old('category_id', $editingMenu?->category_id) === (string) $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                            @error('category_id') <small class="field-error">{{ $message }}</small> @enderror
                        </label>
                        <label class="menu-form-field">
                            <span>TAG</span>
                            <input type="text" name="tag" value="{{ old('tag', $editingMenu?->tag) }}" maxlength="50" placeholder="Contoh: MINUMAN">
                            @error('tag') <small class="field-error">{{ $message }}</small> @enderror
                        </label>
                    </div>

                    <div class="menu-form-row">
                        <label class="menu-form-field">
                            <span>HARGA NORMAL (RP) <b>*</b></span>
                            <input type="number" name="price" value="{{ old('price', $editingMenu?->price) }}" min="1" max="9999999999.99" step="0.01" placeholder="28000" required>
                            @error('price') <small class="field-error">{{ $message }}</small> @enderror
                        </label>
                        <label class="menu-form-field">
                            <span>HARGA DISKON (RP)</span>
                            <input type="number" name="discount_price" value="{{ old('discount_price', $editingMenu?->discount_price) }}" min="1" step="0.01" placeholder="Opsional">
                            @error('discount_price') <small class="field-error">{{ $message }}</small> @enderror
                        </label>
                    </div>

                    <label class="menu-form-field">
                        <span>DISKON BERAKHIR</span>
                        <input type="datetime-local" name="discount_end_time" value="{{ old('discount_end_time', $editingMenu?->discount_end_time?->format('Y-m-d\TH:i')) }}">
                        <small class="menu-field-hint">Isi bersama harga diskon. Harga normal kembali setelah waktu ini.</small>
                        @error('discount_end_time') <small class="field-error">{{ $message }}</small> @enderror
                    </label>

                    <label class="menu-form-field">
                        <span>DESKRIPSI</span>
                        <textarea name="description" rows="3" maxlength="5000" placeholder="Ceritakan bahan atau rasa menu...">{{ old('description', $editingMenu?->description) }}</textarea>
                        @error('description') <small class="field-error">{{ $message }}</small> @enderror
                    </label>

                    <label class="menu-availability">
                        <span>
                            <strong>Status ketersediaan</strong>
                            <small>Tampilkan menu untuk pelanggan</small>
                        </span>
                        <select name="is_available" aria-label="Status ketersediaan">
                            <option value="1" @selected((string) old('is_available', $editingMenu?->is_available ?? true) === '1')>Tersedia</option>
                            <option value="0" @selected((string) old('is_available', $editingMenu?->is_available ?? true) === '0')>Habis</option>
                        </select>
                    </label>

                    <label class="menu-featured">
                        <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $editingMenu?->is_featured ?? false))>
                        <span>Tandai sebagai menu unggulan</span>
                    </label>

                    <div class="menu-editor-actions">
                        @if ($isEditing)
                            <button
                                class="menu-delete-button"
                                type="submit"
                                form="delete-menu-{{ $editingMenu->id }}"
                                aria-label="Hapus menu"
                                onclick="return confirm('Hapus menu {{ addslashes($editingMenu->name) }}?')"
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7h16M10 11v6m4-6v6M5.5 7l1 14h11l1-14M9 7V4h6v3"/></svg>
                            </button>
                        @endif
                        <button class="button button-primary menu-save-button" type="submit">
                            {{ $isEditing ? 'Simpan perubahan' : 'Simpan menu' }}
                        </button>
                    </div>
                </form>
                @if ($isEditing)
                    <form id="delete-menu-{{ $editingMenu->id }}" action="{{ route('admin.menus.destroy', $editingMenu) }}" method="POST" hidden>
                        @csrf
                        @method('DELETE')
                    </form>
                @endif
            </aside>
        @endif
    </section>

    <script>
        const menuImageInput = document.querySelector("#menu-image");

        menuImageInput?.addEventListener("change", () => {
            const file = menuImageInput.files?.[0];
            if (!file) return;

            const preview = document.querySelector("#menu-image-preview");
            preview.src = URL.createObjectURL(file);
            preview.hidden = false;
            document.querySelector("#menu-image-placeholder").hidden = true;
        });
    </script>
@endsection
