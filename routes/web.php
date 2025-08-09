<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DivisionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KpiUmumController;

Route::middleware('guest')->group(function () {
    Route::get('/', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

Route::get('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware('auth')
    ->name('dashboard.index');

Route::middleware(['auth','role:owner'])->get('/dashboard/owner', [DashboardController::class, 'owner'])->name('dashboard.owner');
Route::middleware(['auth','role:hr'])->get('/dashboard/hr', [DashboardController::class, 'hr'])->name('dashboard.hr');
Route::middleware(['auth','role:leader'])->get('/dashboard/leader', [DashboardController::class, 'leader'])->name('dashboard.leader');
Route::middleware(['auth','role:karyawan'])->get('/dashboard/karyawan', [DashboardController::class, 'karyawan'])->name('dashboard.karyawan');

Route::middleware(['auth','role:hr'])->group(function () {
    Route::resource('divisions', DivisionController::class)->except(['show']);
});

Route::middleware(['auth','role:owner,hr,leader'])->group(function () {
    Route::resource('users', UserController::class)->except(['show']);
});

Route::middleware(['auth','role:owner,hr,leader'])->group(function () {
    Route::get('kpi-umum', [KpiUmumController::class, 'index'])->name('kpi-umum.index');
});

Route::middleware(['auth','role:hr'])->group(function () {
    Route::post('kpi-umum', [KpiUmumController::class, 'store'])->name('kpi-umum.store');
    Route::put('kpi-umum/{kpi}', [KpiUmumController::class, 'update'])->name('kpi-umum.update');
    Route::delete('kpi-umum/{kpi}', [KpiUmumController::class, 'destroy'])->name('kpi-umum.destroy');
});