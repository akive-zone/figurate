<?php

namespace App\Events\Server\Notifications;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Notifications\Notification;

class RoutingNotificationChannels
{
    use Dispatchable;

    /**
     * @param  array<int, class-string>  $channels
     */
    public function __construct(
        public object $notifiable,
        public Notification $notification,
        public array $channels,
    ) {}
}
