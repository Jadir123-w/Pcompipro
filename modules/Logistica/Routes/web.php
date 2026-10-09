<?php
use Illuminate\Support\Facades\Route;
use Modules\Logistica\Http\Controllers\LogisticaController;

Route::middleware(['auth', 'redirect.module', 'locked.tenant'])
    ->prefix('logistica')
    ->group(function () {

        // 👉 SOLO VISTA (Blade)
        Route::get('dashboard', [LogisticaController::class, 'index'])->name('tenant.logistica.dashboard');
        
        // 👉 SOLO DATA (JSON – Notion)
        Route::get('dashboard/data', [LogisticaController::class, 'dashboardData'])
            ->name('tenant.logistica.dashboard.data');
    });
