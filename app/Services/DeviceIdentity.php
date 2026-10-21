<?php

namespace App\Services;

use App\Models\AppState;
use Illuminate\Support\Str;
use Throwable;

/**
 * Stable identifier of the phone/tablet the app runs on.
 *
 * On a device we ask NativePHP for the native device id (Android: ANDROID_ID, which is
 * stable for this app across reinstalls). In the browser (development) a random id is
 * generated once and kept in local storage.
 */
class DeviceIdentity
{
    public static function isNative(): bool
    {
        return \App\Support\Native::isNative();
    }

    public function id(): string
    {
        $stored = AppState::get('device_id');
        if ($stored) {
            return $stored;
        }

        $id = null;
        if (static::isNative()) {
            try {
                $id = \Native\Mobile\Facades\Device::getId();
            } catch (Throwable) {
                $id = null;
            }
        }

        $id = $id ? preg_replace('/[^A-Za-z0-9_-]/', '', (string) $id) : null;
        $id = $id ?: 'web-'.Str::lower(Str::random(20));

        AppState::put('device_id', $id);

        return $id;
    }

    /** Human friendly device name shown to the admin when approving devices. */
    public function name(): string
    {
        $stored = AppState::get('device_name');
        if ($stored) {
            return $stored;
        }

        $name = null;
        if (static::isNative()) {
            try {
                $info = json_decode((string) \Native\Mobile\Facades\Device::getInfo(), true) ?: [];
                $name = trim(($info['manufacturer'] ?? '').' '.($info['model'] ?? $info['name'] ?? ''));
                if (! empty($info['platform'])) {
                    $name .= ' ('.$info['platform'].' '.($info['osVersion'] ?? '').')';
                }
            } catch (Throwable) {
                $name = null;
            }
        }

        $name = trim((string) $name) ?: $this->browserName();
        AppState::put('device_name', $name);

        return $name;
    }

    protected function browserName(): string
    {
        $ua = (string) request()->userAgent();

        return match (true) {
            str_contains($ua, 'Android') => 'Android (browser)',
            str_contains($ua, 'iPhone') => 'iPhone (browser)',
            str_contains($ua, 'Windows') => 'Windows (browser)',
            str_contains($ua, 'Mac') => 'Mac (browser)',
            default => 'Browser',
        };
    }
}
