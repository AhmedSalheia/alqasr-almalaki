<?php

namespace App\Services;

use App\Models\AppState;
use App\Services\Firebase\FirebaseClient;
use App\Services\Firebase\FirebaseException;
use App\Services\Firebase\OfflineException;

/**
 * The signed-in user of this device, persisted in the local database so the
 * app keeps working offline (and after the app is closed/reopened).
 */
class AuthSession
{
    public const KEY = 'auth';

    public function __construct(protected FirebaseClient $firebase) {}

    public function user(): ?array
    {
        return AppState::get(self::KEY);
    }

    /** Signed in AND the device is approved. */
    public function check(): bool
    {
        $u = $this->user();

        return $u !== null && ($u['device_status'] ?? null) === 'approved';
    }

    public function pendingDevice(): bool
    {
        $u = $this->user();

        return $u !== null && ($u['device_status'] ?? null) === 'pending';
    }

    public function isAdmin(): bool
    {
        return $this->check() && ($this->user()['role'] ?? null) === 'admin';
    }

    public function uid(): ?string
    {
        return $this->user()['uid'] ?? null;
    }

    public function name(): string
    {
        $u = $this->user() ?? [];

        return (string) ($u['name'] ?? $u['email'] ?? '');
    }

    public function store(array $user): void
    {
        AppState::put(self::KEY, $user);
    }

    public function update(array $changes): void
    {
        if ($u = $this->user()) {
            $this->store(array_merge($u, $changes));
        }
    }

    /**
     * A valid Firebase ID token, refreshed when it is about to expire.
     *
     * @throws OfflineException|FirebaseException
     */
    public function token(): string
    {
        $u = $this->user();
        if (! $u || empty($u['refresh_token'])) {
            throw new FirebaseException('NOT_SIGNED_IN');
        }

        if (! empty($u['id_token']) && ($u['expires_at'] ?? 0) > time() + 120) {
            return $u['id_token'];
        }

        $res = $this->firebase->refresh($u['refresh_token']);
        $this->update([
            'id_token' => $res['id_token'],
            'refresh_token' => $res['refresh_token'],
            'expires_at' => time() + (int) $res['expires_in'],
        ]);

        return $res['id_token'];
    }

    /**
     * Sign out and remove this device's local copy of the reservations
     * (they are downloaded again on the next sign in).
     */
    public function logout(): void
    {
        AppState::forget(self::KEY, 'sync.cursor', 'sync.last_success_at', 'sync.last_error', 'sync.last_attempt_at', 'sync.online');
        \App\Models\Event::query()->delete();
    }
}
