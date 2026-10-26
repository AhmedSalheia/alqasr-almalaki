<?php

namespace App\Support;

use App\Models\AppState;

/**
 * Venue details shown on the event card (location, main contact) and the venue's own DJ.
 * Admins edit them in Settings; they sync to every phone through Firestore "settings/venue".
 */
class Venue
{
    public const STATE = 'venue';

    public const DIRTY = 'venue.dirty';

    public const PULLED_AT = 'venue.pulled_at';

    public const FIELDS = ['location', 'phone', 'dj_name', 'dj_phone'];

    /** @return array{location:string, phone:string, dj_name:string, dj_phone:string, updated_at:?string} */
    public static function get(): array
    {
        $defaults = (array) config('alqasr.venue');
        $saved = (array) AppState::get(static::STATE, []);
        $out = [];
        foreach (static::FIELDS as $f) {
            $out[$f] = (string) (array_key_exists($f, $saved) ? $saved[$f] : ($defaults[$f] ?? ''));
        }
        $out['updated_at'] = $saved['updated_at'] ?? null;

        return $out;
    }

    public static function value(string $field): string
    {
        return static::get()[$field] ?? '';
    }

    /** Save on this phone and queue the change for the next sync. */
    public static function save(array $values): void
    {
        $data = array_intersect_key($values, array_flip(static::FIELDS));
        $data = array_map(fn ($v) => trim((string) $v), $data);
        AppState::put(static::STATE, $data + ['updated_at' => now()->utc()->toIso8601String()]);
        AppState::put(static::DIRTY, true);
    }

    public static function dirty(): bool
    {
        return (bool) AppState::get(static::DIRTY, false);
    }

    /** Fields as stored in Firestore. */
    public static function toRemote(): array
    {
        $v = static::get();

        return [
            'location' => $v['location'],
            'phone' => $v['phone'],
            'djName' => $v['dj_name'],
            'djPhone' => $v['dj_phone'],
            'updatedAt' => $v['updated_at'] ?? now()->utc()->toIso8601String(),
        ];
    }

    /** Apply the Firestore copy unless this phone holds a newer, unsynced edit. */
    public static function applyRemote(array $doc): bool
    {
        $local = static::get();
        $remoteAt = (string) ($doc['updatedAt'] ?? '');
        if (static::dirty() && $local['updated_at'] && strcmp($local['updated_at'], $remoteAt) > 0) {
            return false;
        }

        AppState::put(static::STATE, [
            'location' => (string) ($doc['location'] ?? $local['location']),
            'phone' => (string) ($doc['phone'] ?? $local['phone']),
            'dj_name' => (string) ($doc['djName'] ?? $local['dj_name']),
            'dj_phone' => (string) ($doc['djPhone'] ?? $local['dj_phone']),
            'updated_at' => $remoteAt ?: null,
        ]);
        AppState::forget(static::DIRTY);

        return true;
    }
}
