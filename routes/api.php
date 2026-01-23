<?php

use App\Http\Controllers\ServicesController;
use App\Http\Controllers\TimingController;
use App\Http\Controllers\InboundController;
use Illuminate\Support\Facades\Route;

Route::prefix('internal')->group(function () {

    // Health check: usado pelo BE para saber se o MO está no ar
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'service' => 'MO',
            'version' => '1.0'
        ]);
    });

});

Route::controller(ServicesController::class)->group(function () {
    Route::post('/services/test','devTeste'); //rota para testes durante o desenvolvimento.
});

Route::post('/pacemaker', [TimingController::class, 'pacemaker'])->name('pacemaker');


Route::post('/inboundSe', [InboundController::class, 'inboundSe'])->name('inboundSe');

Route::post('/inboundBe/{task}', [InboundController::class, 'inboundBe'])->name('inboundBe');
