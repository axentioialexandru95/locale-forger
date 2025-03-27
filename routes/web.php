<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ExportDownloadController;

Route::get('/', function () {
    return view('welcome');
});

// Export download routes
Route::get('/exports/{export}/download', [ExportDownloadController::class, 'download'])
    ->middleware(['auth'])
    ->name('exports.download');
