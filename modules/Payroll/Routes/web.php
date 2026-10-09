<?php

use Hyn\Tenancy\Contracts\CurrentHostname;
use Illuminate\Support\Facades\Route;
use Modules\Payroll\Http\Controllers\ConfigurationController;
use Modules\Payroll\Http\Controllers\EmployeeController;
use Modules\Payroll\Http\Controllers\PayrollController;
use Modules\Payroll\Http\Controllers\ReportController;

//$current_hostname = app(CurrentHostname::class);
//if ($current_hostname) {
//    Route::domain($current_hostname->fqdn)->group(function () {
        Route::middleware(['auth', 'redirect.module', 'locked.tenant','check.email.verified'])->group(function () {

            // Planillas
            Route::prefix('payroll-main')->group(function() {
              Route::redirect('/', '/payroll-main/list');
              Route::get('/list', [PayrollController::class, 'index'])->name('tenant.payroll.index');
              Route::get('/tables', [PayrollController::class, 'tables']);    
              Route::post('/store', [PayrollController::class, 'store']);
            //   Route::get('/create', [PayrollController::class, 'create'])->name('tenant.payroll.create');
            //   Route::post('/create', [PayrollController::class, 'store'])->name('tenant.payroll.store');
              Route::get('/detail/{id}', [PayrollController::class, 'show'])->name('tenant.payroll.show')->where('id', '[0-9]+');
            //   Route::get('/report/{periodo}', [PayrollController::class, 'report'])->name('tenant.payroll.report');

              Route::get('/columns', [PayrollController::class, 'columns']);
              Route::get('/records', [PayrollController::class, 'records']);
              Route::get('/record/{id}', [PayrollController::class, 'record']);
              Route::delete('/record/{id}', [PayrollController::class, 'destroy']);
              Route::post('/procesar/{id}', [PayrollController::class, 'procesar']);
              Route::post('/aprobar/{id}', [PayrollController::class, 'aprobar']);
              Route::get('/imprimir-boleta/{planillaId}/{detalleId}', [PayrollController::class, 'imprimirBoleta']);
              Route::get('/imprimir-planilla/{id}', [PayrollController::class, 'imprimirPlanilla']);
              Route::post('/enviar-boletas/{id}', [PayrollController::class, 'enviarBoletas']);
              Route::put('/actualizar-detalle/{planillaId}/{detalleId}', [PayrollController::class, 'actualizarDetalle']);
              Route::get('/reporte', [PayrollController::class, 'reporte']);
              Route::post('/add-employee/{id}', [PayrollController::class, 'addEmployee']);
              Route::delete('/remove-employee/{id}/{detalleId}', [PayrollController::class, 'removeEmployee']);
              Route::get('/available-employees/{id}', [PayrollController::class, 'availableEmployees']);

            });


            // Rutas de empleados
            Route::prefix('payroll/employees')->name('tenant.payroll.employees.')->group(function () {
                Route::redirect('/', '/payroll/employees/list');
                Route::get('/list', [EmployeeController::class, 'index'])->name('index');
                Route::get('/search-users', [EmployeeController::class, 'searchUsers'])->name('search-users');
                Route::get('/tables', [EmployeeController::class, 'tables']);
                Route::get('/columns', [EmployeeController::class, 'columns']);
                Route::get('/records', [EmployeeController::class, 'records']);
                Route::post('/store', [EmployeeController::class, 'store']);
                Route::get('/record/{id}', [EmployeeController::class, 'record']);
                Route::delete('/record/{id}', [EmployeeController::class, 'destroy']);

                //     Route::get('/create', [EmployeeController::class, 'create'])->name('create');
            //     Route::post('/create', [EmployeeController::class, 'store'])->name('store');
            //     Route::get('/{id}', [EmployeeController::class, 'show'])->name('show');
            //     Route::get('/{id}/edit', [EmployeeController::class, 'edit'])->name('edit');
            //     Route::put('/{id}', [EmployeeController::class, 'update'])->name('update');
            //     Route::delete('/{id}', [EmployeeController::class, 'destroy'])->name('destroy');
            });

        
            // // Rutas de configuración
            Route::prefix('payroll/configuration')->name('tenant.payroll.configuration.')->group(function () {
                Route::get('/list', [ConfigurationController::class, 'index'])->name('index');
                Route::post('/update', [ConfigurationController::class, 'update'])->name('update');
            });

            Route::prefix('payroll/reports')->name('tenant.payroll.reports.')->group(function () {
                Route::any('/list', [ReportController::class, 'index'])->name('index');
                Route::get('/employee/{employeeId}', [ReportController::class, 'employeeReport'])->name('employee');
                Route::post('/export', [ReportController::class, 'exportReport'])->name('export');
            });
        });

//    });

//}
