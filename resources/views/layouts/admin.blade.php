<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f7f2df">
    <title>@yield('page-title', 'Dashboard') - Kedai A1</title>
    @vite('resources/css/dashboard.css')
</head>
<body class="admin-app">
    <x-admin.sidebar :active="trim($__env->yieldContent('active', 'dashboard'))" />

    <main class="admin-main">
        <x-admin.header
            :title="trim($__env->yieldContent('page-title', 'Dashboard'))"
            :subtitle="trim($__env->yieldContent('page-subtitle', 'Ringkasan operasional Kedai A1 hari ini'))"
        />
        <div class="dashboard-content">
            @yield('content')
        </div>
    </main>
    <script>
        document.addEventListener('click', (event) => {
            const opener = event.target.closest('[data-dialog-open]');
            if (opener) {
                document.getElementById(opener.dataset.dialogOpen)?.showModal();
            }

            const closer = event.target.closest('[data-dialog-close]');
            if (closer) {
                closer.closest('dialog')?.close();
            }

            if (event.target instanceof HTMLDialogElement && event.target.classList.contains('ops-dialog')) {
                event.target.close();
            }
        });

        document.querySelectorAll('dialog[data-open-on-load]').forEach((dialog) => dialog.showModal());
    </script>
</body>
</html>
