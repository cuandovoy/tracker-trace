<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\TruckController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/login', [AuthController::class, 'authenticate'])->middleware('throttle:login');
    Route::get('/forgot-password', [AuthController::class, 'forgot'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendReset'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'updatePassword'])->middleware('throttle:5,1')->name('password.update');
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/', function () {
        return redirect(auth()->user()->isSuper() ? '/admin/empresas' : '/camiones');
    })->name('home');
    Route::resource('camiones', TruckController::class)->parameters(['camiones' => 'truck'])->except('destroy');
    Route::post('/camiones/{truck}/archivo', [TruckController::class, 'archive'])->name('trucks.archive');
    Route::resource('choferes', DriverController::class)->parameters(['choferes' => 'driver'])->except('destroy');
    Route::post('/choferes/{driver}/archivo', [DriverController::class, 'archive'])->name('drivers.archive');
    Route::post('/choferes/{driver}/asignacion', [DriverController::class, 'assign'])->name('drivers.assign');
    Route::get('/choferes/{driver}/pdf', [DriverController::class, 'pdf'])->middleware('throttle:20,1')->name('drivers.pdf');
    Route::resource('viajes', TripController::class)->parameters(['viajes' => 'trip'])->except('destroy');
    Route::post('/viajes/{trip}/estado', [TripController::class, 'transition'])->name('trips.transition');
    Route::get('/ajustes', [CompanyController::class, 'settings'])->name('settings');
    Route::put('/ajustes', [CompanyController::class, 'saveSettings']);
    Route::resource('usuarios', UserController::class)->parameters(['usuarios' => 'member'])->except(['show', 'destroy']);
    Route::post('/usuarios/{member}/invitacion', [UserController::class, 'invite'])->middleware('throttle:5,1')->name('users.invite');
    Route::prefix('admin')->middleware('can:manage-platform')->group(function () {
        Route::resource('empresas', CompanyController::class)->parameters(['empresas' => 'company'])->names('companies')->except(['show', 'destroy']);
        Route::get('/empresas/{company}/usuarios/create', [UserController::class, 'create']);
        Route::post('/empresas/{company}/usuarios', [UserController::class, 'store']);
        Route::get('/empresas/{company}/usuarios/{member}/edit', [UserController::class, 'edit']);
        Route::put('/empresas/{company}/usuarios/{member}', [UserController::class, 'update']);
        Route::post('/empresas/{company}/usuarios/{member}/invitacion', [UserController::class, 'invite'])->middleware('throttle:5,1');
    });
});
