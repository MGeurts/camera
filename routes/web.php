<?php

use Illuminate\Support\Facades\Route;

Route::controller(App\Http\Controllers\CameraController::class)->group(function (): void {
    Route::get('/',  'index')->name('cameras.index');

    Route::get('/cameras/{id}',  'show')->where('id', '[0-9]+')->name('cameras.show');

    Route::get('/cameras/{id}/snapshot', 'snapshot')->where('id', '[0-9]+')->name('cameras.snapshot');

    Route::prefix('api')->group(function () {
        Route::get('/cameras/status', 'allStatus');
        Route::get('/cameras/{id}/info', 'deviceInfo')->where('id', '[0-9]+');
    });
});
