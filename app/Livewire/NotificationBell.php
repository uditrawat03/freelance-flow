<?php

namespace App\Livewire;

use Illuminate\Notifications\DatabaseNotification;
use Livewire\Attributes\On;
use Livewire\Component;

class NotificationBell extends Component
{
    public bool $open = false;

    public function toggleOpen(): void
    {
        $this->open = ! $this->open;

        // Mark all as read when the panel opens
        if ($this->open) {
            auth()->user()
                ->unreadNotifications
                ->markAsRead();
        }
    }

    public function dismiss(string $notificationId): void
    {
        $notification = auth()->user()
            ->notifications()
            ->find($notificationId);

        $notification?->delete();
    }

    public function clearAll(): void
    {
        auth()->user()->notifications()->delete();
        $this->open = false;
    }

    public function render()
    {
        $notifications = auth()->user()
            ->notifications()
            ->latest()
            ->limit(15)
            ->get();

        $unreadCount = auth()->user()
            ->unreadNotifications()
            ->count();

        return view('livewire.notification-bell', compact('notifications', 'unreadCount'));
    }
}