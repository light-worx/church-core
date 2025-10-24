<?php

use App\Http\Controllers\InstallController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (file_exists(storage_path('installed.lock'))) {
        return view('home'); // or dashboard
    } else {
        return redirect()->route('install.welcome');
    }
});
Route::get('/install', [InstallController::class, 'welcome'])->name('install.welcome');
Route::get('/install/environment', [InstallController::class, 'environment'])->name('install.environment');
Route::post('/install/environment', [InstallController::class, 'saveEnvironment'])->name('install.environment.save');
Route::get('/install/modules', [InstallController::class, 'modules'])->name('install.modules');
Route::post('/install/modules', [InstallController::class, 'installModules'])->name('install.modules.save');