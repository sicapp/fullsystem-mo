<?php

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
