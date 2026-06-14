<?php

namespace App\Console\Commands\Octane;

use Laravel\Octane\Commands\StartCommand as BaseStartCommand;

class StartCommand extends BaseStartCommand
{
    public function getSubscribedSignals(): array
    {
        if (! function_exists('pcntl_signal')) {
            return [];
        }

        return parent::getSubscribedSignals();
    }
}
