<?php

namespace App\Services\Updates;

/** Browser / development: updates are offered as a link to the GitHub release instead. */
class NullUpdateGateway implements UpdateGateway
{
    public function supported(): bool
    {
        return false;
    }

    public function download(string $url, string $version, bool $unmeteredOnly): array
    {
        return ['started' => false, 'reason' => 'unsupported'];
    }

    public function status(): array
    {
        return ['state' => 'idle'];
    }

    public function install(): array
    {
        return ['success' => false, 'error' => 'unsupported'];
    }
}
