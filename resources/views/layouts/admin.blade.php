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
</body>
</html>
