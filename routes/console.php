<?php

use Illuminate\Support\Facades\Schedule;

// Check overdue invoices every morning at 7am
Schedule::command('invoice:check-overdue')
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('invoice:check-overdue scheduler failed');
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