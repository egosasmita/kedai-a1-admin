<?php

return [
    'name' => env('ADMIN_NAME', 'Admin Kedai A1'),
    'email' => env('ADMIN_EMAIL', 'admin@kedaia1.id'),
    'password' => env('ADMIN_PASSWORD'),
    'employee' => [
        'name' => env('EMPLOYEE_NAME', 'Karyawan Kedai A1'),
        'email' => env('EMPLOYEE_EMAIL', 'karyawan@kedaia1.id'),
        'password' => env('EMPLOYEE_PASSWORD'),
    ],
];
