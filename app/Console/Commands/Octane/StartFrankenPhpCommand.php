<?php

namespace App\Console\Commands\Octane;

use Laravel\Octane\Commands\StartFrankenPhpCommand as BaseStartFrankenPhpCommand;

class StartFrankenPhpCommand extends BaseStartFrankenPhpCommand
{
    public function getSubscribedSignals(): array
    {
        if (! function_exists('pcntl_signal')) {
            return [];
        }

        return parent::getSubscribedSignals();
    }
}
