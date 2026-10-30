@extends('layouts.employee')

@php
    $isCheckIn = $actionType === 'check-in';
@endphp

@section('page-title', $isCheckIn ? 'Absen Masuk' : 'Absen Pulang')
@section('breadcrumb', $isCheckIn ? 'Absen Masuk' : 'Absen Pulang')
@section('active', $isCheckIn ? 'check-in' : 'check-out')

@section('content')
@php
    $pageTitle = $isCheckIn ? 'Absen Masuk' : 'Absen Pulang';
        $submitText = $isCheckIn ? 'Simpan Absen Masuk' : 'Simpan Absen Pulang';
    @endphp
    <section class="capture-page-heading">
        <span class="employee-eyebrow">ABSENSI PRIBADI · {{ mb_strtoupper(auth()->user()->name) }}</span>
        <h1>{{ $pageTitle }}</h1>
        <p>{{ $isCheckIn ? 'Awali shift dengan mengirim foto kehadiranmu.' : 'Akhiri shift dan catat waktu pulangmu.' }}</p>
        <span class="capture-required-note">◷ Foto kamera dan lokasi GPS wajib digunakan</span>
    </section>

    <section class="capture-layout" data-attendance-capture>
        <div class="capture-main-column">
            <div class="capture-steps" aria-label="Langkah absensi">
                <span class="is-active" data-step-camera>1&nbsp; Ambil foto</span>
                <span data-step-location>2&nbsp; Ambil lokasi</span>
                <span data-step-save>3&nbsp; Simpan</span>
            </div>
            <div class="camera-panel">
                <video data-camera-video autoplay playsinline muted aria-label="Pratinjau kamera depan"></video>
                <img data-camera-photo alt="Foto kehadiran yang diambil">
                <div class="camera-placeholder" data-camera-placeholder>
                    <span class="camera-face-icon">♙</span>
                    <strong>Pratinjau kamera depan</strong>
                    <small>Izinkan akses kamera untuk mengambil foto absensi.</small>
                </div>
                <span class="camera-label">◉ Kamera depan</span>
                <span class="camera-simulation-label">KAMERA PERANGKAT</span>
            </div>
            <p class="capture-tip">◉ Posisikan wajah di dalam bingkai dengan cahaya yang cukup. Foto hanya diambil saat kamu menekan tombol.</p>
            <div class="capture-privacy-card">
                <strong>KEHADIRAN YANG TERJAGA</strong>
                <p>Foto dan lokasi hanya digunakan sebagai bukti absensi pribadimu.</p>
                <small>Foto disimpan privat dan tidak memiliki tautan publik.</small>
            </div>
        </div>
        <aside class="capture-info-card">
            <h2>Informasi absensi</h2>
            <span class="capture-info-label">Jam sekarang</span>
            <strong class="capture-current-time" data-live-clock>{{ now()->format('H:i') }}</strong>
            <small>WIB · Waktu perangkat server</small>
            <div class="capture-date-row"><span>Tanggal sekarang</span><strong>{{ now()->locale('id')->translatedFormat('j F Y') }}</strong></div>
            @if ($isCheckIn)
                <div class="capture-location-card">
                    <strong>Shift tetap</strong>
                    <span>{{ $assignedShift->name }} · {{ \Illuminate\Support\Carbon::parse($assignedShift->starts_at)->format('H:i') }}–{{ \Illuminate\Support\Carbon::parse($assignedShift->ends_at)->format('H:i') }} WIB</span>
                    <small>Absen masuk dibuka 15 menit sebelum shift. Lebih dari 15 menit setelah mulai akan tercatat terlambat.</small>
                </div>
            @else
                <div class="capture-location-card">
                    <strong>✓ Absen masuk hari ini</strong>
                    <span>{{ \Illuminate\Support\Carbon::parse($attendance->check_in_at)->format('H:i') }} WIB · {{ $attendance->shift_name ?? 'Shift tidak ditentukan' }}</span>
                    <small>Absen pulang dapat dilakukan selama shift hingga 15 menit setelah shift berakhir.</small>
                </div>
            @endif
            <div class="capture-location-card" data-location-summary>
                <strong>⌖ Lokasi GPS</strong>
                <span data-location-label>Belum diambil</span>
                <small data-location-accuracy>Lokasi diminta dari perangkatmu.</small>
            </div>
            <button class="capture-camera-button" type="button" data-camera-start>◎ <span>Aktifkan Kamera</span></button>
            <button class="capture-camera-button is-secondary" type="button" data-camera-capture disabled>Ambil Foto</button>
            <button class="capture-location-button" type="button" data-location-capture disabled>⌖ <span>Ambil Lokasi</span></button>
            <form id="attendance-capture-form" class="capture-submit-form" data-capture-form method="POST" enctype="multipart/form-data" action="{{ $isCheckIn ? route('employee.attendance.check-in.store') : route('employee.attendance.check-out.store') }}">
                @csrf
                <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" data-photo-input hidden>
                <input type="hidden" name="latitude" data-latitude>
                <input type="hidden" name="longitude" data-longitude>
                <input type="hidden" name="location_accuracy" data-location-accuracy-input>
                <button class="capture-save-button" type="submit" data-attendance-submit disabled>✓ <span>{{ $submitText }}</span></button>
            </form>
            <small class="capture-submit-hint" data-capture-hint>Ambil foto kamera dan lokasi GPS untuk mengaktifkan tombol simpan.</small>
        </aside>
    </section>
@endsection
