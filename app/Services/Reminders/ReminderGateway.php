<?php

namespace App\Services\Reminders;

interface ReminderGateway
{
    public function schedule(string $id, string $title, string $body, int $at): bool;

    public function cancel(string $id): bool;

    public function cancelAll(): bool;

    public function requestPermission(): bool;
}
