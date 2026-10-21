<?php

namespace App\Services;

use RuntimeException;

class AuthFailed extends RuntimeException
{
    public function __construct(public readonly string $reason, string $detail = '')
    {
        parent::__construct($detail ?: $reason);
    }
}
