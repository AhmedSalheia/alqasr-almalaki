<?php

namespace App\Support;

/**
 * Where is the app running?
 *
 * On the phone NativePHP exports NATIVEPHP_RUNNING. In "Jump" mode (native:jump) PHP runs on
 * the developer machine and bridge calls are relayed to the phone (JUMP_BRIDGE_PORT is set).
 * Otherwise we are in a normal browser (php artisan serve) used for development.
 */
class Native
{
    public static function isNative(): bool
    {
        // On the phone nativephp_call() is provided by NativePHP's C extension (an internal
        // function); on a dev machine NativePHP defines a PHP polyfill with the same name.
        if (function_exists('nativephp_call') && (new \ReflectionFunction('nativephp_call'))->isInternal()) {
            return true;
        }

        return (bool) (getenv('NATIVEPHP_RUNNING') ?: ($_SERVER['NATIVEPHP_RUNNING'] ?? env('NATIVEPHP_RUNNING')))
            || getenv('JUMP_BRIDGE_PORT') !== false;
    }

    public static function can(string $method): bool
    {
        if (! static::isNative() || ! function_exists('nativephp_call')) {
            return false;
        }

        return function_exists('nativephp_can') ? (bool) nativephp_can($method) : true;
    }
}
