<?php

namespace App\Services\Reminders;

/** Used in the browser: there is nothing to notify. */
class NullReminderGateway implements ReminderGateway
{
    public function schedule(string $id, string $title, string $body, int $at): bool
    {
        return true;
    }

    public function cancel(string $id): bool
    {
        return true;
    }

    public function cancelAll(): bool
    {
        return true;
    }

    public function requestPermission(): bool
    {
        return false;
    }
}
