<?php

use App\Http\Controllers\ConversionController;
use App\Http\Controllers\MobileLookupController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/process', [ConversionController::class, 'process']);
Route::get('/process/{token}', [ConversionController::class, 'metadata']);
Route::middleware(['web', 'otp.admin'])->group(function () {
    Route::get('/process/{token}/text', [ConversionController::class, 'downloadText']);
    Route::get('/process/{token}/json', [ConversionController::class, 'downloadJson']);
    Route::get('/process/{token}/xlsx', [ConversionController::class, 'downloadExcel']);
    Route::get('/process/{token}/validate', [ConversionController::class, 'validateResult']);
});
Route::post('/mobile-lookup', [MobileLookupController::class, 'lookup']);
Route::get('/mobile-key-status', [MobileLookupController::class, 'keyStatus']);
