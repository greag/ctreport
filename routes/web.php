<?php

use App\Http\Controllers\ReportController;
use App\Http\Controllers\UiController;
use App\Http\Controllers\OtpAuthController;
use App\Http\Controllers\EmployeeDirectoryController;
use App\Models\EmployeeDirectory;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/login', [OtpAuthController::class, 'show'])->name('otp.login');
Route::post('/otp/send', [OtpAuthController::class, 'send'])->name('otp.send');
Route::post('/otp/verify', [OtpAuthController::class, 'verify'])->name('otp.verify');
Route::post('/logout', [OtpAuthController::class, 'logout'])->name('otp.logout');

Route::middleware('otp.auth')->group(function () {
    Route::get('/', [UiController::class, 'index']);
    Route::get('/session-check', function (Illuminate\Http\Request $request) {
        $phone = (string) $request->session()->get('otp_phone', '');
        $isAdmin = false;
        if ($phone !== '') {
            $isAdmin = EmployeeDirectory::query()
                ->where('mobile_number', $phone)
                ->where('is_active', true)
                ->where('is_admin', true)
                ->exists();
        }
        return response()->json([
            'otp_authenticated' => (bool) $request->session()->get('otp_authenticated', false),
            'otp_phone' => $phone,
            'is_admin' => $isAdmin,
        ]);
    });
    Route::get('/reports', [ReportController::class, 'index']);
    Route::get('/reports/{reportId}', [ReportController::class, 'show']);
    Route::get('/employees', [EmployeeDirectoryController::class, 'index'])->name('employees.index');
    Route::post('/employees', [EmployeeDirectoryController::class, 'store'])->name('employees.store');
    Route::post('/employees/{employee}/toggle', [EmployeeDirectoryController::class, 'toggle'])->name('employees.toggle');
    Route::post('/employees/{employee}/toggle-admin', [EmployeeDirectoryController::class, 'toggleAdmin'])->name('employees.toggleAdmin');
});

Route::middleware(['otp.auth', 'otp.admin'])->group(function () {
    Route::post('/reports/{reportId}/delete', [ReportController::class, 'destroy'])->name('reports.destroy');
    Route::post('/reports/{reportId}/reprocess', [ReportController::class, 'reprocess'])->name('reports.reprocess');
    Route::get('/process/{token}/text', [\App\Http\Controllers\ConversionController::class, 'downloadText']);
    Route::get('/process/{token}/json', [\App\Http\Controllers\ConversionController::class, 'downloadJson']);
    Route::get('/process/{token}/xlsx', [\App\Http\Controllers\ConversionController::class, 'downloadExcel']);
    Route::get('/process/{token}/validate', [\App\Http\Controllers\ConversionController::class, 'validateResult']);
});
