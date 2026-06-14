<?php

// routes/web.php — complete verified structure

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProjectAnalyticsController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Middleware\EnsureTwoFactorAuthenticated;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\TwoFactorChallenge;
use App\Livewire\Clients\ClientList;
use App\Livewire\Clients\Create as CreateClient;
use App\Livewire\Clients\Edit as EditClient;
use App\Livewire\Dashboard;
use App\Livewire\Invoices\Create as CreateInvoice;
use App\Livewire\Invoices\InvoiceList;
use App\Livewire\Projects\Create as CreateProject;
use App\Livewire\Projects\Edit as EditProject;
use App\Livewire\Settings\Index as SettingsIndex;
use App\Livewire\Workspaces\Create as CreateWorkspace;
use App\Models\Workspace;
use Illuminate\Support\Facades\Route;

// -------------------------------------------------------
// Guest-only routes (redirect to dashboard if logged in)
// -------------------------------------------------------
Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/register', Register::class)->name('register');
});

// -------------------------------------------------------
// Logout
// -------------------------------------------------------
Route::post('/logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect('/login');
})->middleware('auth')->name('logout');

Route::get('/two-factor-challenge', TwoFactorChallenge::class)
    ->middleware('auth')
    ->name('two-factor.challenge');

// -------------------------------------------------------
// Stripe webhook — public, CSRF excluded
// -------------------------------------------------------
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])
    ->name('stripe.webhook');

// -------------------------------------------------------
// Payment pages — public (clients pay without logging in)
// -------------------------------------------------------
Route::get(
    '/invoices/{invoice}/pay',
    [PaymentController::class, 'show']
)->name('invoices.pay');
Route::get(
    '/invoices/{invoice}/pay/success',
    [PaymentController::class, 'success']
)->name('invoices.pay.success');

// -------------------------------------------------------
// Authenticated routes
// -------------------------------------------------------
Route::middleware(['auth', EnsureTwoFactorAuthenticated::class])->group(function () {

    // Dashboard
    Route::get('/dashboard', Dashboard::class)->name('dashboard');
    Route::get('/', fn () => redirect()->route('dashboard'));

    // Settings
    Route::get('/settings', SettingsIndex::class)->name('settings.index');

    // Workspace
    Route::get('/workspaces/create', CreateWorkspace::class)->name('workspaces.create');

    // Clients
    Route::get('/clients', ClientList::class)->name('clients.index');
    Route::get('/clients/create', CreateClient::class)->name('clients.create');
    Route::get('/clients/{client}', [ClientController::class, 'show'])->name('clients.show');
    Route::get('/clients/{client}/edit', EditClient::class)->name('clients.edit');

    // Projects
    Route::get('/projects/create', CreateProject::class)->name('projects.create');
    Route::get('/projects/{project}/edit', EditProject::class)->name('projects.edit');
    Route::get('/projects/{project}/analytics', [ProjectAnalyticsController::class, 'show'])->name('projects.analytics');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');

    // Invoices
    Route::get('/invoices', InvoiceList::class)->name('invoices.index');
    Route::get('/invoices/create', CreateInvoice::class)->name('invoices.create');
    Route::get('/invoices/{invoice}/download', [InvoiceController::class, 'download'])->name('invoices.download');
    Route::get('/invoices/{invoice}/preview', [InvoiceController::class, 'preview'])->name('invoices.preview');
    Route::post('/invoices/{invoice}/send', [InvoiceController::class, 'send'])->name('invoices.send');
    Route::post('/invoices/{invoice}/paid', [InvoiceController::class, 'markPaid'])->name('invoices.mark-paid');

    // Attachments
    Route::get(
        '/attachments/{attachment}/download',
        [AttachmentController::class, 'download']
    )->name('attachments.download');

});

if (! app()->isProduction()) {
    Route::get('/testing/set-workspace/{workspace}', function (Workspace $workspace) {
        abort_unless(auth()->user()?->hasWorkspaceAccess($workspace), 403);

        session(['current_workspace_id' => $workspace->id]);

        return response('OK');
    })->middleware('auth')->name('testing.set-workspace');
}
