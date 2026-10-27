<?php

declare(strict_types=1);

use App\Http\Controllers\Api\TacticAnalysisController;
use Illuminate\Support\Facades\Route;

Route::prefix('tactics')->name('api.tactics.')->group(function (): void {
    Route::post('/analyze', [TacticAnalysisController::class, 'analyze'])->name('analyze');
    Route::get('/status/{uuid}', [TacticAnalysisController::class, 'status'])->name('status');
    Route::get('/logs/{id}', [TacticAnalysisController::class, 'showLog'])->whereNumber('id')->name('show');
    Route::get('/history', [TacticAnalysisController::class, 'history'])->name('history');
});

