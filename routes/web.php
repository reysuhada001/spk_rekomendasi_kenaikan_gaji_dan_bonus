<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DivisionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KpiUmumController;
use App\Http\Controllers\AhpKpiUmumController;
use App\Http\Controllers\KpiUmumRealizationController;

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

Route::middleware(['auth','role:hr'])->group(function () {
    Route::get('/ahp/kpi-umum', [AhpKpiUmumController::class, 'index'])->name('ahp.kpi-umum.index');
    Route::post('/ahp/kpi-umum/hitung', [AhpKpiUmumController::class, 'hitung'])->name('ahp.kpi-umum.hitung');
});

Route::middleware(['auth','role:owner,hr,leader,karyawan'])->group(function () {
    Route::get('realisasi-kpi-umum', [KpiUmumRealizationController::class,'index'])->name('realisasi-kpi-umum.index');
    Route::get('realisasi-kpi-umum/{user}/create', [KpiUmumRealizationController::class,'create'])
        ->middleware('role:leader') // leader input
        ->name('realisasi-kpi-umum.create');
    Route::post('realisasi-kpi-umum/{user}', [KpiUmumRealizationController::class,'store'])
        ->middleware('role:leader')
        ->name('realisasi-kpi-umum.store');

    Route::get('realisasi-kpi-umum/{realization}', [KpiUmumRealizationController::class,'show'])
        ->name('realisasi-kpi-umum.show');

    Route::post('realisasi-kpi-umum/{realization}/approve', [KpiUmumRealizationController::class,'approve'])
        ->middleware('role:hr')
        ->name('realisasi-kpi-umum.approve');

    Route::post('realisasi-kpi-umum/{realization}/reject', [KpiUmumRealizationController::class,'reject'])
        ->middleware('role:hr')
        ->name('realisasi-kpi-umum.reject');
});