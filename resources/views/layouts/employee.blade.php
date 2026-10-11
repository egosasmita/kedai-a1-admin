<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f4f0df">
    <title>@yield('page-title', 'Portal Karyawan') - Kedai A1</title>
    @vite(['resources/css/employee.css', 'resources/js/employee-attendance.js'])
</head>
<body class="employee-app">
    <x-employee.sidebar :active="trim($__env->yieldContent('active', 'home'))" />
    <main class="employee-main">
        <header class="employee-header">
            <div class="employee-breadcrumb"><span>Portal Karyawan</span><i>/</i><strong>@yield('breadcrumb', 'Beranda')</strong></div>
            <div class="employee-header-actions">
                <span>{{ now()->locale('id')->translatedFormat('l, j F Y') }}</span>
                <span class="employee-header-bell" aria-hidden="true">♧</span>
                <span class="employee-header-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
            </div>
        </header>
        <div class="employee-content">
            @if (session('status'))
                <div class="employee-flash" role="status">{{ session('status') }}</div>
            @endif
            <x-admin.validation-errors />
            @yield('content')
        </div>
    </main>
</body>
</html>
