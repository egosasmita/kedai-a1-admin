<?php

use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\PayrollController;
use App\Http\Controllers\Admin\StockController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Employee\AttendancePortalController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'store'])->middleware('guest')->name('login.store');
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/stok', [StockController::class, 'index'])->name('stock.index');
    Route::post('/stok/items', [StockController::class, 'storeItem'])->name('stock.items.store');
    Route::post('/stok/transaksi', [StockController::class, 'storeTransaction'])->name('stock.transactions.store');
    Route::get('/karyawan', [EmployeeController::class, 'index'])->name('employees.index');
    Route::post('/karyawan', [EmployeeController::class, 'store'])->name('employees.store');
    Route::get('/absensi', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('/absensi/check-in', [AttendanceController::class, 'checkIn'])->name('attendance.check-in');
    Route::post('/absensi/check-out', [AttendanceController::class, 'checkOut'])->name('attendance.check-out');
    Route::get('/penggajian', [PayrollController::class, 'index'])->name('payroll.index');
    Route::post('/penggajian/proses', [PayrollController::class, 'process'])->name('payroll.process');
    Route::post('/penggajian/{payroll}/komponen', [PayrollController::class, 'addItem'])->name('payroll.items.store');
    Route::post('/penggajian/{payroll}/bayar', [PayrollController::class, 'pay'])->name('payroll.pay');

    Route::get('/menus', [MenuController::class, 'index'])->name('menus.index');
    Route::get('/menus/create', [MenuController::class, 'index'])->name('menus.create');
    Route::post('/menus', [MenuController::class, 'store'])->name('menus.store');
    Route::get('/menus/{menu}/edit', [MenuController::class, 'index'])->name('menus.edit');
    Route::put('/menus/{menu}', [MenuController::class, 'update'])->name('menus.update');
    Route::delete('/menus/{menu}', [MenuController::class, 'destroy'])->name('menus.destroy');
});

Route::get('/dashboard', DashboardController::class)
    ->middleware('auth')
    ->name('dashboard');

Route::middleware(['auth', 'employee'])->prefix('karyawan')->name('employee.')->group(function () {
    Route::get('/dashboard', [AttendancePortalController::class, 'dashboard'])->name('dashboard');
    Route::get('/absen-masuk', [AttendancePortalController::class, 'checkInPage'])->name('attendance.check-in');
    Route::post('/absen-masuk', [AttendancePortalController::class, 'checkIn'])->name('attendance.check-in.store');
    Route::get('/absen-pulang', [AttendancePortalController::class, 'checkOutPage'])->name('attendance.check-out');
    Route::post('/absen-pulang', [AttendancePortalController::class, 'checkOut'])->name('attendance.check-out.store');
    Route::get('/riwayat', [AttendancePortalController::class, 'history'])->name('attendance.history');
});
