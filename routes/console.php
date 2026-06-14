<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Telescope\Telescope;

// Check overdue invoices every morning at 7am
Schedule::command('invoice:check-overdue')
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->onFailure(function () {
        Log::error('invoice:check-overdue scheduler failed');
    })
    ->emailOutputOnFailure(config('mail.from.address'));

// Send payment reminders at 9am — invoices due in 3 days
Schedule::command('invoice:send-reminders --days=3')
    ->dailyAt('09:00')
    ->withoutOverlapping();

// Also remind for invoices due tomorrow
Schedule::command('invoice:send-reminders --days=1')
    ->dailyAt('09:00')
    ->withoutOverlapping();

// Monthly revenue report — 1st of every month at 8am
Schedule::command('reports:monthly-revenue')
    ->monthlyOn(1, '08:00')
    ->withoutOverlapping();

// Archive stale leads — every Sunday at midnight
Schedule::command('clients:archive-leads --days=90')
    ->weekly()
    ->sundays()
    ->at('00:00')
    ->withoutOverlapping();

// Clean up Livewire temporary uploads daily
Schedule::command('livewire:clean-uploads')
    ->daily();

Schedule::command('cache:warm')
    ->dailyAt('05:00')
    ->withoutOverlapping()
    ->runInBackground();

// Prune stale queue jobs older than 48 hours
Schedule::command('queue:prune-failed --hours=48')
    ->daily();

if (class_exists(Telescope::class)) {
    Schedule::command('telescope:prune --hours=48')
        ->daily()
        ->withoutOverlapping()
        ->when(fn () => app()->isLocal() || (bool) config('telescope.enabled'));
}

Schedule::command('horizon:snapshot')
    ->everyFiveMinutes()
    ->when(fn () => app()->bound('horizon'))
    ->withoutOverlapping();

Schedule::call(function () {
    if (! interface_exists(MasterSupervisorRepository::class)) {
        return;
    }

    $masters = app(MasterSupervisorRepository::class)->all();

    if (empty($masters)) {
        Log::critical('Horizon is not running.', [
            'checked_at' => now()->toIso8601String(),
        ]);
    }
})
    ->name('horizon-health-check')
    ->everyFiveMinutes()
    ->when(fn () => app()->environment('production'))
    ->withoutOverlapping();
