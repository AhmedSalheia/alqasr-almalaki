<?php

namespace App\Services\Firebase;

use RuntimeException;

/**
 * Firebase answered with an error (wrong password, permission denied, ...).
 */
class FirebaseException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message = '', public readonly int $status = 0)
    {
        parent::__construct($message ?: $errorCode, $status);
    }

    public function is(string ...$codes): bool
    {
        foreach ($codes as $code) {
            if (str_starts_with($this->errorCode, $code)) {
                return true;
            }
        }

        return false;
    }
}
