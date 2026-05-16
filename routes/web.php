<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ProjectController;
// use App\Http\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;

use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Clients\Create as CreateClient;
use App\Livewire\Clients\Edit as EditClient;
use App\Livewire\Invoices\Create as CreateInvoice;
use App\Livewire\Invoices\InvoiceList;

use App\Http\Controllers\PaymentController;


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
    Route::middleware('auth')->group(function () {
        Route::get('/dashboard', \App\Livewire\Dashboard::class)->name('dashboard');
        // ... other routes
    });

    // Route::middleware(['auth', 'role:admin'])->group(function () {
    //     Route::get('/admin/users', [AdminController::class, 'users']);
    // });

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


    Route::get('/invoices',               InvoiceList::class)->name('invoices.index');
    Route::get('/invoices/create',        CreateInvoice::class)->name('invoices.create');
    Route::get('/invoices/{invoice}/download', [InvoiceController::class, 'download'])->name('invoices.download');
    Route::get('/invoices/{invoice}/preview',  [InvoiceController::class, 'preview'])->name('invoices.preview');
    
    // Protect by permission
    Route::middleware(['auth', 'permission:send invoices'])->group(function () {
        Route::post('/invoices/{invoice}/send', [InvoiceController::class, 'send']);
    });
    
    Route::post('/invoices/{invoice}/paid',    [InvoiceController::class, 'markPaid'])->name('invoices.mark-paid');

    // Payment routes (public — clients do not need to log in to pay)
    Route::get('/invoices/{invoice}/pay',         [PaymentController::class, 'show'])->name('invoices.pay');
    Route::get('/invoices/{invoice}/pay/success', [PaymentController::class, 'success'])->name('invoices.pay.success');

    // Route::prefix('invoices')->name('invoices.')->group(function () {
    //     Route::get('/{invoice}/download', [InvoiceController::class, 'download'])->name('download');
    //     Route::get('/{invoice}/preview',  [InvoiceController::class, 'preview'])->name('preview');
    //     Route::post('/{invoice}/send',    [InvoiceController::class, 'send'])->name('send');
    //     Route::post('/{invoice}/paid',    [InvoiceController::class, 'markPaid'])->name('mark-paid');
    // });

    // Route::get('/invoices/{invoice}/pay',         [PaymentController::class, 'show'])->name('invoices.pay');
    // Route::get('/invoices/{invoice}/pay/success', [PaymentController::class, 'success'])->name('invoices.pay.success');
    // Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])
    //  ->name('stripe.webhook');

    // Route::middleware(['auth', 'role:admin|manager'])->group(function () {
    //     Route::get('/reports', [ReportController::class, 'index']);
    // });
});


