<?php

namespace App\Services\Updates;

interface UpdateGateway
{
    /** Can this build download + install APKs itself (Android with the updater plugin)? */
    public function supported(): bool;

    /** @return array{started?:bool, reason?:string, error?:string} */
    public function download(string $url, string $version, bool $unmeteredOnly): array;

    /** @return array{state?:string, progress?:int, bytes?:int, total?:int, error?:string, version?:string, canInstall?:bool, fileReady?:bool} */
    public function status(): array;

    /** @return array{success?:bool, needsPermission?:bool, error?:string} */
    public function install(): array;
}
