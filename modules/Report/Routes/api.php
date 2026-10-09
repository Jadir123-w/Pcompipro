<?php


$hostname = app(Hyn\Tenancy\Contracts\CurrentHostname::class);
if ($hostname) {
    Route::domain($hostname->fqdn)->group(function () {
        Route::middleware(['auth:api', 'locked.tenant'])->group(function () {
            Route::prefix('reports')->group(function(){
                Route::get('quotations', 'Api\ReportController@quotations');
                Route::get('general-items', 'Api\ReportController@generalItems');
                Route::get('documents', 'Api\ReportController@documents');
            });
        });

    });

}