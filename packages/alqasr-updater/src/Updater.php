<?php

namespace Alqasr\Updater;

/**
 * PHP wrapper around the Updater.* bridge functions (Android only).
 */
class Updater
{
    public static function available(): bool
    {
        if (! function_exists('nativephp_call')) {
            return false;
        }
        $onDevice = (new \ReflectionFunction('nativephp_call'))->isInternal();
        if (! $onDevice && getenv('JUMP_BRIDGE_PORT') === false) {
            return false;
        }
        if (function_exists('nativephp_can')) {
            return (bool) nativephp_can('Updater.Download');
        }

        return true;
    }

    public static function download(string $url, string $version, bool $unmeteredOnly = false): array
    {
        return static::call('Updater.Download', ['url' => $url, 'version' => $version, 'unmeteredOnly' => $unmeteredOnly]);
    }

    public static function status(): array
    {
        return static::call('Updater.Status');
    }

    public static function install(): array
    {
        return static::call('Updater.Install');
    }

    protected static function call(string $method, array $params = []): array
    {
        if (! static::available()) {
            return [];
        }
        $raw = nativephp_call($method, json_encode($params ?: new \stdClass));
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (! is_array($decoded)) {
            return [];
        }

        return is_array($decoded['data'] ?? null) ? $decoded['data'] : $decoded;
    }
}
