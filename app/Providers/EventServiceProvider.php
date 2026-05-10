<?php

namespace App\Providers;

use App\Events\ProjectCreated;
use App\Listeners\LogProjectActivity;
// use App\Listeners\NotifyTeamOnSlack;
use App\Listeners\SendProjectNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        ProjectCreated::class => [
            LogProjectActivity::class,       // runs synchronously
            SendProjectNotification::class,  // runs in the queue (ShouldQueue)
            // NotifyTeamOnSlack::class,
        ],
    ];

    public function boot(): void
    {
        //
    }
}