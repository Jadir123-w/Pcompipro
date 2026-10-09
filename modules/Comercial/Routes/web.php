<?php

use Illuminate\Support\Facades\Route;
use Modules\Comercial\Http\Controllers\ContactosController;
use Modules\Comercial\Http\Controllers\EmpresasController;
use Modules\Comercial\Http\Controllers\LeadsController;
use Modules\Comercial\Http\Controllers\LogsController;
use Modules\Comercial\Http\Controllers\ProyectoProductoImageController;
use Modules\Comercial\Http\Controllers\ProyectosController;
use Modules\Comercial\Http\Controllers\RequerimientosController;

Route::middleware(['auth', 'redirect.module', 'locked.tenant', 'check.email.verified'])
    ->prefix('comercial')
    ->group(function () {

        // DEBUG TEMP: ver conexión/BD real en web (tenant) + revisar configurations
        Route::get('_debug/db', function () {
            return response()->json([
                'default_connection' => config('database.default'),
                'connection_name' => \DB::connection()->getName(),
                'database' => \DB::connection()->getDatabaseName(),
                'configurations_count' => \DB::table('configurations')->count(),
                'configurations_ids' => \DB::table('configurations')->orderBy('id')->pluck('id'),
            ]);
        });


        /*
        |--------------------------------------------------------------------------
        | EMPRESAS  (/comercial/empresas/...)
        |--------------------------------------------------------------------------
        */
        Route::prefix('empresas')->name('tenant.comercial.empresas.')->group(function () {
            Route::redirect('/', '/comercial/empresas/listado');

            Route::get('options', [EmpresasController::class, 'options'])->name('empresas.options');


            Route::get('listado', [EmpresasController::class, 'index'])->name('index');
            Route::get('columns', [EmpresasController::class, 'columns'])->name('columns');
            Route::get('records', [EmpresasController::class, 'records'])->name('records');
            Route::get('tables',  [EmpresasController::class, 'tables'])->name('tables');

            Route::post('/', [EmpresasController::class, 'store'])->name('store');
            Route::get('record/{id}', [EmpresasController::class, 'record'])->name('record');
            Route::delete('record/{id}', [EmpresasController::class, 'destroy'])->name('destroy');

            Route::get('search', [EmpresasController::class, 'searchEmpresas'])->name('search-empresas');
        });

        /*
        |--------------------------------------------------------------------------
        | CONTACTOS  (/comercial/contactos/...)
        |--------------------------------------------------------------------------
        */
        Route::prefix('contactos')->name('tenant.comercial.contactos.')->group(function () {
            Route::redirect('/', '/comercial/contactos/listado');

            Route::get('listado', [ContactosController::class, 'index'])->name('index');
            Route::get('columns', [ContactosController::class, 'columns'])->name('columns');
            Route::get('tables',  [ContactosController::class, 'tables'])->name('tables');
            Route::get('records', [ContactosController::class, 'records'])->name('records');

            Route::post('/', [ContactosController::class, 'store'])->name('store');
            Route::get('record/{id}', [ContactosController::class, 'record'])->name('record');
            Route::delete('record/{id}', [ContactosController::class, 'destroy'])->name('destroy');

            Route::post('disableMassive', [ContactosController::class, 'disableMassive']);
            Route::post('enableMassive',  [ContactosController::class, 'enableMassive']);
            Route::post('destroyMassive', [ContactosController::class, 'destroyMassive']);

            Route::get('empresa/{empresaId}', [ContactosController::class, 'empresa']);

                        // Interacciones de contacto
            Route::get('{id}/interacciones', [ContactosController::class, 'interacciones'])->name('interacciones');
            Route::post('{id}/interaccion', [ContactosController::class, 'storeInteraccion'])->name('interaccion.store');
            Route::put('{id}/interaccion/{interaccionId}', [ContactosController::class, 'updateInteraccion'])->name('interaccion.update');
        });

        /*
        |--------------------------------------------------------------------------
        | LEADS  (/comercial/leads/...)
        |--------------------------------------------------------------------------
        */
        Route::prefix('leads')->name('tenant.comercial.leads.')->group(function () {
            Route::redirect('/', '/comercial/leads/listado');

            Route::get('listado', [LeadsController::class, 'index'])->name('index');
            Route::get('columns', [LeadsController::class, 'columns'])->name('columns');
            Route::get('tables',  [LeadsController::class, 'tables'])->name('tables');
            Route::get('records', [LeadsController::class, 'records'])->name('records');

            Route::post('/', [LeadsController::class, 'store'])->name('store');

            Route::get('record/{id}', [LeadsController::class, 'record'])->name('record');
            Route::get('review/{id}', [LeadsController::class, 'review']);

            Route::post('store/empresa/{id}',   [LeadsController::class, 'storeEmpresa']);
            Route::post('store/contacto/{id}',  [LeadsController::class, 'storeContacto']);
            Route::post('convert-to-requirement/{id}', [LeadsController::class, 'convertToRequirement']);
        });

        /*
        |--------------------------------------------------------------------------
        | REQUERIMIENTOS  (/comercial/requerimientos/...)
        |--------------------------------------------------------------------------
        */
        Route::prefix('requerimientos')->name('tenant.comercial.requerimientos.')->group(function () {

            // Vista principal
            Route::redirect('/', '/comercial/requerimientos/listado');
            Route::get('listado', [RequerimientosController::class, 'index'])->name('index');

            // Usuarios activos para select
            Route::get('usuarios-activos', [RequerimientosController::class, 'usuariosActivos'])->name('usuarios-activos');

            // API records para tabla
            Route::get('records', [RequerimientosController::class, 'list'])->name('records');

            // Crear requerimiento
            Route::post('', [RequerimientosController::class, 'store'])->name('store');

            // Kanban + KPIs
            Route::get('kanban', [RequerimientosController::class, 'kanban'])->name('kanban');
            Route::get('kpis',   [RequerimientosController::class, 'kpis'])->name('kpis');

            // Detalle + acciones del requerimiento
            Route::get('{id}', [RequerimientosController::class, 'show'])->name('show');
            Route::put('{id}', [RequerimientosController::class, 'update'])->name('update');
            Route::post('{id}/cambiar-etapa', [RequerimientosController::class, 'cambiarEtapa'])->name('cambiar-etapa');

            // Interacciones
            Route::get('{id}/interacciones', [RequerimientosController::class, 'interacciones'])->name('interacciones');
            Route::post('{id}/interaccion', [RequerimientosController::class, 'storeInteraccion'])->name('interaccion.store');
            Route::put('{id}/interaccion/{interaccionId}', [RequerimientosController::class, 'updateInteraccion'])->name('interaccion.update');


            // Cotizaciones
            Route::get('{id}/cotizaciones', [RequerimientosController::class, 'cotizaciones'])->name('cotizaciones');
            Route::post('{id}/cotizacion', [RequerimientosController::class, 'subirCotizacion'])->name('cotizacion.store');

            // Historial (legacy)
            Route::get('{id}/historial', [RequerimientosController::class, 'historial'])->name('historial');
            Route::get('{id}/logs', [RequerimientosController::class, 'logs'])->name('logs');

            // Logs de auditoría
            Route::get('{id}/logs', [RequerimientosController::class, 'logs'])->name('logs');

            // Crear proyecto desde requerimiento
            Route::post('{id}/crear-proyecto', [RequerimientosController::class, 'crearProyecto'])->name('crear-proyecto');

            // Prioridad
            Route::post('{id}/calcular-prioridad', [RequerimientosController::class, 'calcularPrioridad']);
        });

        /*
        |--------------------------------------------------------------------------
        | PROYECTOS  (/comercial/proyectos/...)
        |--------------------------------------------------------------------------
        */
        Route::prefix('proyectos')->name('tenant.comercial.proyectos.')->group(function () {
            Route::redirect('/', '/comercial/proyectos/listado');

            // Vista principal (Blade → Vue)
            Route::get('listado', [ProyectosController::class, 'index'])->name('index');

            // API: listado y KPIs
            Route::get('records', [ProyectosController::class, 'records'])->name('records');
            Route::get('kpis', [ProyectosController::class, 'kpis'])->name('kpis');

            // API: datos para selects del formulario
            Route::get('tables', [ProyectosController::class, 'tables'])->name('tables');

            // API: contactos de una empresa (para select en form)
            Route::get('contactos-empresa/{empresaId}', [ProyectosController::class, 'contactosEmpresa'])->name('contactos-empresa');

            // API: búsqueda de proveedores (para evaluación post-venta)
            Route::get('search-proveedores', [ProyectosController::class, 'searchProveedores'])->name('search-proveedores');

            // API: CRUD de proyecto
            Route::post('record', [ProyectosController::class, 'store'])->name('store');
            Route::get('record/{id}', [ProyectosController::class, 'record'])->name('record');
            Route::put('record/{id}', [ProyectosController::class, 'update'])->name('update');
            Route::delete('record/{id}', [ProyectosController::class, 'destroy'])->name('destroy');

            // API: logs de auditoría
            Route::get('logs/{id}', [ProyectosController::class, 'logs'])->name('logs');

            // API: Archivo PDF de OC por razón social
            Route::post('record/{id}/razon-social/{rsId}/oc-upload', [ProyectosController::class, 'uploadOcArchivo'])->name('oc.upload');
            Route::get('record/{id}/razon-social/{rsId}/oc-download', [ProyectosController::class, 'downloadOcArchivo'])->name('oc.download');
            Route::delete('record/{id}/razon-social/{rsId}/oc-archivo', [ProyectosController::class, 'deleteOcArchivo'])->name('oc.delete');

            // API: Bitácora "Cambios e indicaciones" (M3)
            Route::get('record/{id}/bitacora', [ProyectosController::class, 'listBitacora'])->name('bitacora.list');
            Route::post('record/{id}/bitacora', [ProyectosController::class, 'storeBitacora'])->name('bitacora.store');
            Route::put('record/{id}/bitacora/{bitacoraId}', [ProyectosController::class, 'updateBitacora'])->name('bitacora.update');
        });

        /*
        |--------------------------------------------------------------------------
        | PROYECTO · IMAGEN POR PRODUCTO  (/comercial/proyecto-productos/{id}/imagen)
        |--------------------------------------------------------------------------
        | Subir / servir / quitar la imagen de referencia de cada producto del
        | proyecto. Replica el patrón de Marketing (QuotationImageController).
        */
        Route::name('tenant.comercial.proyecto-productos.')->group(function () {
            Route::post('proyecto-productos/{id}/imagen', [ProyectoProductoImageController::class, 'upload'])->name('imagen.upload');
            Route::get('proyecto-productos/{id}/imagen', [ProyectoProductoImageController::class, 'show'])->name('imagen.show');
            Route::delete('proyecto-productos/{id}/imagen', [ProyectoProductoImageController::class, 'destroy'])->name('imagen.destroy');
        });

        /*
        |--------------------------------------------------------------------------
        | LOGS / AUDITORÍA  (/comercial/logs/...)
        |--------------------------------------------------------------------------
        */
        Route::prefix('logs')->name('tenant.comercial.logs.')->group(function () {
            Route::redirect('/', '/comercial/logs/listado');
            Route::get('listado', [LogsController::class, 'index'])->name('index');
            Route::get('columns', [LogsController::class, 'columns'])->name('columns');
            Route::get('records', [LogsController::class, 'records'])->name('records');
            Route::get('{id}', [LogsController::class, 'show'])->name('show');
        });

        /*
        |--------------------------------------------------------------------------
        | API LOGS  (/comercial/api/logs/...)
        |--------------------------------------------------------------------------
        */
        Route::prefix('api/logs')->name('tenant.comercial.api.logs.')->group(function () {
            Route::get('contactos', [LogsController::class, 'contactos'])->name('contactos');
            Route::get('stats', [LogsController::class, 'stats'])->name('stats');
            Route::get('{id}', [LogsController::class, 'show'])->name('show');
        });

        /*
        |--------------------------------------------------------------------------
        | COM-07 — Recuperación y remarketing comercial  (/comercial/com07/...)
        |--------------------------------------------------------------------------
        |
        | Las pantallas viven bajo Comercial porque su usuaria es la ejecutiva
        | comercial: así el acceso lo gobierna el permiso del módulo Comercial y
        | no requiere habilitar Marketing, que expondría los demás agentes y sus
        | pantallas de configuración.
        |
        | La implementación (controladores, servicios y modelos) permanece en el
        | módulo Marketing, junto al resto del COM-07; lo que cambia es el punto
        | de entrada, que es lo que determina el permiso.
        |
        | Cada pantalla exige además su propio sub-permiso (module_level), de modo
        | que el panel de la ejecutiva y la vista de supervisión se habiliten por
        | separado desde la ficha del usuario.
        */
        Route::prefix('com07')->name('tenant.comercial.com07.')->group(function () {

            // Panel de Trabajo de la ejecutiva
            Route::get('/panel', [\Modules\Marketing\Http\Controllers\MarketingController::class, 'com07PanelIndex'])
                ->name('panel');

            Route::get('/oportunidades', [\Modules\Marketing\Http\Controllers\Api\Com07PanelController::class, 'index'])
                ->name('oportunidades.list');

            // Registro de la gestión (Pantalla 2)
            Route::get('/oportunidades/{id}', [\Modules\Marketing\Http\Controllers\Api\Com07GestionController::class, 'show'])
                ->where('id', '[0-9]+')
                ->name('oportunidades.show');

            Route::post('/oportunidades/{id}/gestiones', [\Modules\Marketing\Http\Controllers\Api\Com07GestionController::class, 'store'])
                ->where('id', '[0-9]+')
                ->name('gestiones.store');

            Route::post('/oportunidades/{id}/cancelar-seguimiento', [\Modules\Marketing\Http\Controllers\Api\Com07GestionController::class, 'cancelarSeguimiento'])
                ->where('id', '[0-9]+')
                ->name('seguimiento.cancelar');

            // Vista de Supervisión Comercial (Pantalla 3)
            Route::get('/supervision', [\Modules\Marketing\Http\Controllers\MarketingController::class, 'com07SupervisionIndex'])
                ->name('supervision');

            Route::get('/supervision/oportunidades', [\Modules\Marketing\Http\Controllers\Api\Com07SupervisionController::class, 'index'])
                ->name('supervision.list');
        });

    });
