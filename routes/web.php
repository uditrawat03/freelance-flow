<?php

use App\Http\Controllers\ClientController;
use Illuminate\Support\Facades\Route;

use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Clients\Create as CreateClient;
use App\Livewire\Clients\Edit as EditClient;

Route::get('/', function () {
    return view('welcome');
});

// Auth routes
Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/register', Register::class)->name('register');
});

Route::post('/logout', function () {
    auth()->guard('web')->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/login');
})->middleware('auth')->name('logout');

// Protected routes
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware('auth')->name('dashboard');

// Create client — Livewire full-page component
Route::get('/clients/create', CreateClient::class)
    ->name('clients.create')
    ->middleware('auth');

Route::get('/clients/{client}/edit', EditClient::class)
    ->name('clients.edit')
    ->middleware('auth');

// Clients list and detail — still handled by the controller
Route::resource('clients', ClientController::class)
    ->only(['index', 'show'])
    ->middleware('auth');


