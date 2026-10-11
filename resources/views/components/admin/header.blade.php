@props([
    'title' => 'Dashboard',
    'subtitle' => 'Ringkasan operasional Kedai A1 hari ini',
])

<header class="admin-header">
    <div class="header-heading">
        <h1>{{ $title }}</h1>
        <p>{{ $subtitle }}</p>
    </div>

    <div class="header-actions">
        <div class="header-date">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                <rect x="3.5" y="5" width="17" height="16" rx="2" />
                <path d="M7.5 3v4M16.5 3v4M4 9h16M8 13h2m4 0h2m-8 4h2" />
            </svg>
            <span>{{ now()->locale('id')->translatedFormat('l, j M Y') }}</span>
        </div>

        <button class="notification-button" type="button" aria-label="Notifikasi">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                <path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4" />
            </svg>
            <span class="notification-dot" aria-hidden="true"></span>
        </button>
    </div>
</header>
