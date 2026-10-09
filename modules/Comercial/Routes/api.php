<?php

use Illuminate\Http\Request;
use Modules\Comercial\Http\Controllers\EmpresasController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

//Route::middleware('auth:api')->get('/comercial', function (Request $request) {
//    return $request->user();
//});

// Opciones de empresas para selects
Route::get('empresas/options', [EmpresasController::class, 'options'])->name('empresas.options');
