<?php

namespace App\Livewire\Concerns;

trait WithNotifications
{
    public function notifySuccess(string $message): void
    {
        $this->dispatch('notify', message: $message, type: 'success');
    }

    public function notifyError(string $message): void
    {
        $this->dispatch('notify', message: $message, type: 'error');
    }

    public function notifyWarning(string $message): void
    {
        $this->dispatch('notify', message: $message, type: 'warning');
    }

    public function notifyInfo(string $message): void
    {
        $this->dispatch('notify', message: $message, type: 'info');
    }
}