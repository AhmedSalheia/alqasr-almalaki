<?php

namespace Alqasr\Reminders;

/**
 * Thin PHP wrapper around the Reminders.* bridge functions.
 * Every method is a no-op (returns false) when not running on a phone.
 */
class Reminders
{
    public static function available(): bool
    {
        if (! function_exists('nativephp_call')) {
            return false;
        }
        // On the phone nativephp_call() is the C extension; on a dev machine it is a PHP
        // polyfill that only works while `native:jump` relays calls to a phone.
        $internal = (new \ReflectionFunction('nativephp_call'))->isInternal();
        if (! $internal && getenv('JUMP_BRIDGE_PORT') === false) {
            return false;
        }
        if (function_exists('nativephp_can')) {
            return (bool) nativephp_can('Reminders.Schedule');
        }

        return true;
    }

    /** @param  int  $at  Unix timestamp (seconds) */
    public static function schedule(string $id, string $title, string $body, int $at): bool
    {
        return static::call('Reminders.Schedule', ['id' => $id, 'title' => $title, 'body' => $body, 'at' => $at * 1000]);
    }

    public static function cancel(string $id): bool
    {
        return static::call('Reminders.Cancel', ['id' => $id]);
    }

    public static function cancelAll(): bool
    {
        return static::call('Reminders.CancelAll');
    }

    public static function requestPermission(): bool
    {
        if (! static::available()) {
            return false;
        }
        $res = json_decode((string) nativephp_call('Reminders.RequestPermission', '{}'), true) ?: [];
        $data = $res['data'] ?? $res;

        return (bool) ($data['granted'] ?? false);
    }

    protected static function call(string $method, array $params = []): bool
    {
        if (! static::available()) {
            return false;
        }
        $res = json_decode((string) nativephp_call($method, json_encode($params ?: new \stdClass)), true) ?: [];
        $data = $res['data'] ?? $res;

        return (bool) ($data['success'] ?? false);
    }
}
