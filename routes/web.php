<?php

use App\Http\Controllers\ChiliVisionApiController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('chilivision');
});

// Kompatibilitas endpoint root langsung
Route::get('/health', [ChiliVisionApiController::class, 'health']);
Route::get('/models', [ChiliVisionApiController::class, 'models']);
Route::get('/cameras', [ChiliVisionApiController::class, 'cameras']);
Route::post('/predict', [ChiliVisionApiController::class, 'predictAndStoreLeaf']);
