<?php

namespace App\Services\Reminders;

use Alqasr\Reminders\Reminders;

/** Local notifications through the alqasr/mobile-reminders plugin (packages/alqasr-reminders). */
class NativeReminderGateway implements ReminderGateway
{
    public function schedule(string $id, string $title, string $body, int $at): bool
    {
        return Reminders::schedule($id, $title, $body, $at);
    }

    public function cancel(string $id): bool
    {
        return Reminders::cancel($id);
    }

    public function cancelAll(): bool
    {
        return Reminders::cancelAll();
    }

    public function requestPermission(): bool
    {
        return Reminders::requestPermission();
    }
}
