<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\SettingController;

Route::get('/menus', [MenuController::class, 'index']);
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/settings', [SettingController::class, 'index']);