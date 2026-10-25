<?php

namespace App\Services\Updates;

use Alqasr\Updater\Updater;

class NativeUpdateGateway implements UpdateGateway
{
    public function supported(): bool
    {
        return true;
    }

    public function download(string $url, string $version, bool $unmeteredOnly): array
    {
        return Updater::download($url, $version, $unmeteredOnly);
    }

    public function status(): array
    {
        return Updater::status();
    }

    public function install(): array
    {
        return Updater::install();
    }
}
