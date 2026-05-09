<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ProjectController;
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
// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    // Livewire form components
    Route::get('/clients/create', CreateClient::class)->name('clients.create');
    Route::get('/clients/{client}/edit', EditClient::class)->name('clients.edit');

    // Controller handles list and show
    Route::resource('clients', ClientController::class)->only(['index', 'show']);

    // Project create with optional client pre-selection via query string
    Route::get('/projects/create', \App\Livewire\Projects\Create::class)
        ->name('projects.create');

    Route::get('/projects/{project}/edit', \App\Livewire\Projects\Edit::class)
        ->name('projects.edit');

    Route::get('/attachments/{attachment}/download', [AttachmentController::class, 'download'])
     ->name('attachments.download');

    // Projects — controller for show, Livewire for forms (Day 17)
    Route::resource('projects', ProjectController::class)->only(['show']);
});


