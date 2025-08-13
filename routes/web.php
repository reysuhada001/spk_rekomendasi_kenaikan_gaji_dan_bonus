<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DivisionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KpiUmumController;
use App\Http\Controllers\AhpKpiUmumController;
use App\Http\Controllers\KpiUmumRealizationController;
use App\Http\Controllers\KpiDivisiController;
use App\Http\Controllers\AhpKpiDivisiController;
use App\Http\Controllers\KpiDivisiDistributionController;
use App\Http\Controllers\KpiDivisiKualitatifRealizationController;
use App\Http\Controllers\KpiDivisiKuantitatifRealizationController;
use App\Http\Controllers\KpiDivisiPersentaseRealizationController;
use App\Http\Controllers\KpiDivisiResponseRealizationController;
use App\Http\Controllers\KpiDivisiSkorKaryawanController;
use App\Http\Controllers\KpiDivisiSkorDivisiController;
use App\Http\Controllers\AspekController;
use App\Http\Controllers\PeerAssessmentController;
use App\Http\Controllers\PeerAssessmentAdminController;
use App\Http\Controllers\AhpGlobalController;
use App\Http\Controllers\BonusRecommendationController;
use App\Http\Controllers\SalaryRaiseRecommendationController;
use App\Http\Controllers\LeaderboardMonthlyController;
use App\Http\Controllers\LeaderboardDivisionController; 
use App\Http\Controllers\LeaderboardDivisionKpiController;  

Route::middleware('guest')->group(function () {
    Route::get('/', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

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

Route::middleware(['auth','role:owner,hr,leader'])->group(function () {
    Route::get('kpi-divisi', [KpiDivisiController::class,'index'])->name('kpi-divisi.index');
});

Route::middleware(['auth','role:hr'])->group(function () {
    Route::post('kpi-divisi', [KpiDivisiController::class,'store'])->name('kpi-divisi.store');
    Route::put('kpi-divisi/{kpiDivisi}', [KpiDivisiController::class,'update'])->name('kpi-divisi.update');
    Route::delete('kpi-divisi/{kpiDivisi}', [KpiDivisiController::class,'destroy'])->name('kpi-divisi.destroy');
});

Route::middleware(['auth','role:hr'])->group(function () {
    Route::get('/ahp/kpi-divisi', [AhpKpiDivisiController::class, 'index'])->name('ahp.kpi-divisi.index');
    Route::post('/ahp/kpi-divisi/hitung', [AhpKpiDivisiController::class, 'hitung'])->name('ahp.kpi-divisi.hitung');
});

Route::middleware(['auth'])->group(function () {
    // INDEX: daftar KPI kuantitatif per (divisi, bulan, tahun)
    Route::get('distribusi-kpi-divisi', [KpiDivisiDistributionController::class,'index'])
        ->name('distribusi-kpi-divisi.index');

    // LEADER: input distribusi untuk SATU KPI (pakai ?kpi_id=)
    Route::middleware('role:leader')->group(function () {
        Route::get('distribusi-kpi-divisi/create', [KpiDivisiDistributionController::class,'create'])
            ->name('distribusi-kpi-divisi.create');   // ?kpi_id=
        Route::post('distribusi-kpi-divisi/store', [KpiDivisiDistributionController::class,'store'])
            ->name('distribusi-kpi-divisi.store');    // body: kpi_id, alloc[]
    });

    // DETAIL per KPI (pakai ?distribution_id=&kpi_id=)
    Route::get('distribusi-kpi-divisi/show', [KpiDivisiDistributionController::class,'show'])
        ->name('distribusi-kpi-divisi.show');         // ?distribution_id=&kpi_id=

    // HR: ACC / Tolak distribusi (untuk seluruh periode/divisi)
    Route::middleware('role:hr')->group(function () {
        Route::post('distribusi-kpi-divisi/{distribution}/approve', [KpiDivisiDistributionController::class,'approve'])
            ->name('distribusi-kpi-divisi.approve');
        Route::post('distribusi-kpi-divisi/{distribution}/reject', [KpiDivisiDistributionController::class,'reject'])
            ->name('distribusi-kpi-divisi.reject');
    });
});

Route::middleware(['auth'])->group(function () {
    Route::get('realisasi-kpi-divisi-kuantitatif', [KpiDivisiKuantitatifRealizationController::class,'index'])
        ->name('realisasi-kpi-divisi-kuantitatif.index');

    // Leader input
    Route::middleware('role:leader')->group(function () {
        Route::get('realisasi-kpi-divisi-kuantitatif/create', [KpiDivisiKuantitatifRealizationController::class,'create'])
            ->name('realisasi-kpi-divisi-kuantitatif.create');
        Route::post('realisasi-kpi-divisi-kuantitatif/store', [KpiDivisiKuantitatifRealizationController::class,'store'])
            ->name('realisasi-kpi-divisi-kuantitatif.store');
    });

    // Detail & HR verif
    Route::get('realisasi-kpi-divisi-kuantitatif/{id}', [KpiDivisiKuantitatifRealizationController::class,'show'])
        ->name('realisasi-kpi-divisi-kuantitatif.show');

    Route::middleware('role:hr')->group(function () {
        Route::post('realisasi-kpi-divisi-kuantitatif/{id}/approve', [KpiDivisiKuantitatifRealizationController::class,'approve'])
            ->name('realisasi-kpi-divisi-kuantitatif.approve');
        Route::post('realisasi-kpi-divisi-kuantitatif/{id}/reject', [KpiDivisiKuantitatifRealizationController::class,'reject'])
            ->name('realisasi-kpi-divisi-kuantitatif.reject');
    });
});

Route::middleware(['auth'])->group(function () {

    Route::get('/realisasi-kpi-divisi-kualitatif', [KpiDivisiKualitatifRealizationController::class, 'index'])
        ->name('realisasi-kpi-divisi-kualitatif.index');

    Route::get('/realisasi-kpi-divisi-kualitatif/create', [KpiDivisiKualitatifRealizationController::class, 'create'])
        ->name('realisasi-kpi-divisi-kualitatif.create');

    Route::post('/realisasi-kpi-divisi-kualitatif', [KpiDivisiKualitatifRealizationController::class, 'store'])
        ->name('realisasi-kpi-divisi-kualitatif.store');

    Route::get('/realisasi-kpi-divisi-kualitatif/{id}', [KpiDivisiKualitatifRealizationController::class, 'show'])
        ->name('realisasi-kpi-divisi-kualitatif.show');

    Route::post('/realisasi-kpi-divisi-kualitatif/{id}/approve', [KpiDivisiKualitatifRealizationController::class, 'approve'])
        ->name('realisasi-kpi-divisi-kualitatif.approve');

    Route::post('/realisasi-kpi-divisi-kualitatif/{id}/reject', [KpiDivisiKualitatifRealizationController::class, 'reject'])
        ->name('realisasi-kpi-divisi-kualitatif.reject');
});

Route::middleware(['auth'])->group(function () {

    Route::get('/realisasi-kpi-divisi-response', [KpiDivisiResponseRealizationController::class, 'index'])
        ->name('realisasi-kpi-divisi-response.index');

    Route::get('/realisasi-kpi-divisi-response/create', [KpiDivisiResponseRealizationController::class, 'create'])
        ->name('realisasi-kpi-divisi-response.create');
    Route::post('/realisasi-kpi-divisi-response', [KpiDivisiResponseRealizationController::class, 'store'])
        ->name('realisasi-kpi-divisi-response.store');

    Route::get('/realisasi-kpi-divisi-response/{id}', [KpiDivisiResponseRealizationController::class, 'show'])
        ->name('realisasi-kpi-divisi-response.show');

    Route::post('/realisasi-kpi-divisi-response/{id}/approve', [KpiDivisiResponseRealizationController::class, 'approve'])
        ->name('realisasi-kpi-divisi-response.approve');

    Route::post('/realisasi-kpi-divisi-response/{id}/reject', [KpiDivisiResponseRealizationController::class, 'reject'])
        ->name('realisasi-kpi-divisi-response.reject');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/realisasi-kpi-divisi-persentase', [KpiDivisiPersentaseRealizationController::class, 'index'])
        ->name('realisasi-kpi-divisi-persentase.index');

    Route::get('/realisasi-kpi-divisi-persentase/create', [KpiDivisiPersentaseRealizationController::class, 'create'])
        ->name('realisasi-kpi-divisi-persentase.create'); 

    Route::post('/realisasi-kpi-divisi-persentase', [KpiDivisiPersentaseRealizationController::class, 'store'])
        ->name('realisasi-kpi-divisi-persentase.store');

    Route::get('/realisasi-kpi-divisi-persentase/{id}', [KpiDivisiPersentaseRealizationController::class, 'show'])
        ->name('realisasi-kpi-divisi-persentase.show');

    Route::post('/realisasi-kpi-divisi-persentase/{id}/approve', [KpiDivisiPersentaseRealizationController::class, 'approve'])
        ->name('realisasi-kpi-divisi-persentase.approve');

    Route::post('/realisasi-kpi-divisi-persentase/{id}/reject', [KpiDivisiPersentaseRealizationController::class, 'reject'])
        ->name('realisasi-kpi-divisi-persentase.reject');
});

Route::get('/kpi-divisi/skor', [KpiDivisiSkorKaryawanController::class, 'index'])
    ->middleware(['auth','role:owner,hr,leader,karyawan'])
    ->name('kpi-divisi.skor-karyawan.index');

Route::middleware(['auth','role:owner,hr,leader,karyawan'])->group(function () {
    Route::get('/kpi-divisi/skor-divisi', [KpiDivisiSkorDivisiController::class,'index'])
        ->name('kpi-divisi.skor-divisi.index');
});

Route::middleware(['auth','role:owner,hr,leader'])->group(function () {
    Route::get('aspek', [AspekController::class,'index'])->name('aspek.index');
});

Route::middleware(['auth','role:hr'])->group(function () {
    Route::post('aspek', [AspekController::class,'store'])->name('aspek.store');
    Route::put('aspek/{aspek}', [AspekController::class,'update'])->name('aspek.update');
    Route::delete('aspek/{aspek}', [AspekController::class,'destroy'])->name('aspek.destroy');
});

Route::middleware(['auth','role:karyawan'])->group(function () {
    Route::get('peer', [PeerAssessmentController::class,'index'])->name('peer.index');
    Route::get('peer/create', [PeerAssessmentController::class,'create'])->name('peer.create'); // ?assessee_id=&bulan=&tahun=
    Route::post('peer', [PeerAssessmentController::class,'store'])->name('peer.store');
});

Route::middleware(['auth','role:hr'])->group(function () {
    Route::get('peer-admin', [PeerAssessmentAdminController::class,'index'])->name('peer.admin.index');
    Route::get('peer-admin/{user}', [PeerAssessmentAdminController::class,'show'])->name('peer.admin.show'); // ?bulan=&tahun=
});

Route::middleware(['auth','role:hr'])->group(function () {
    Route::get('ahp/global', [AhpGlobalController::class,'index'])->name('ahp.global.index');
});

Route::middleware(['auth','role:hr'])->group(function () {
    Route::post('ahp/global/hitung', [AhpGlobalController::class,'hitung'])->name('ahp.global.hitung');
});

Route::middleware(['auth','role:owner,hr,leader,karyawan'])->group(function () {
    Route::get('bonus-rekomendasi', [BonusRecommendationController::class,'index'])->name('bonus.rekomendasi.index');
});

Route::middleware(['auth','role:owner,hr,leader,karyawan'])->group(function () {
    Route::get('kenaikan-gaji/rekomendasi', [SalaryRaiseRecommendationController::class,'index'])
        ->name('salary.raise.index');
});

Route::middleware(['auth','role:owner,hr,leader,karyawan'])->group(function () {
    Route::get('leaderboard/bulanan', [LeaderboardMonthlyController::class, 'index'])
        ->name('leaderboard.bulanan.index');
});


Route::middleware(['auth','role:owner,hr,leader,karyawan'])->group(function () {
    Route::get('leaderboard/divisi', [LeaderboardDivisionController::class, 'index'])
        ->name('leaderboard.divisi.index');
});

Route::middleware(['auth','role:owner,hr,leader,karyawan'])->group(function () {
    Route::get('leaderboard/divisi-kpi', [LeaderboardDivisionKpiController::class, 'index'])
        ->name('leaderboard.divisi-kpi.index');
});