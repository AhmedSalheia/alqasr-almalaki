<?php

namespace App\Services;

use App\Services\Firebase\FirebaseClient;
use App\Services\Firebase\FirebaseException;
use App\Services\Firebase\OfflineException;

/**
 * Sign-in flow with device binding:
 *
 *  1. Email/password sign in with Firebase Authentication.
 *  2. The user's profile document users/{uid} must exist and be active.
 *  3. The device must be one of the user's tied devices. The very first device is tied
 *     automatically (configurable); any other device creates a request in
 *     deviceRequests/{uid}_{deviceId} that an admin approves from inside the app.
 */
class AuthService
{
    public function __construct(
        protected FirebaseClient $firebase,
        protected AuthSession $session,
        protected DeviceIdentity $device,
    ) {}

    public function normalizeLogin(string $login): string
    {
        $login = trim(mb_strtolower($login));

        return str_contains($login, '@') ? $login : $login.'@'.config('alqasr.username_domain');
    }

    /**
     * @return 'approved'|'pending'
     *
     * @throws AuthFailed|OfflineException
     */
    public function login(string $login, string $password): string
    {
        $email = $this->normalizeLogin($login);

        try {
            $auth = $this->firebase->signIn($email, $password);
        } catch (FirebaseException $e) {
            throw new AuthFailed(match (true) {
                $e->is('INVALID_LOGIN_CREDENTIALS', 'INVALID_PASSWORD', 'EMAIL_NOT_FOUND', 'INVALID_EMAIL') => 'invalid_credentials',
                $e->is('USER_DISABLED') => 'account_disabled',
                $e->is('TOO_MANY_ATTEMPTS') => 'too_many_attempts',
                $e->is('NOT_CONFIGURED') => 'not_configured',
                default => 'unknown',
            }, $e->errorCode);
        }

        $uid = $auth['localId'];
        $token = $auth['idToken'];

        $profile = $this->firebase->getDocument("users/{$uid}", $token);
        if (! $profile) {
            throw new AuthFailed('no_profile');
        }
        if (! ($profile['active'] ?? false)) {
            throw new AuthFailed('account_disabled');
        }

        $deviceId = $this->device->id();
        $devices = $profile['devices'] ?? [];
        $status = 'pending';

        if ($this->deviceIsTied($devices, $deviceId)) {
            $status = 'approved';
        } elseif ($devices === [] && config('alqasr.auto_bind_first_device')) {
            $this->firebase->patchDocument("users/{$uid}", [
                'devices' => [$this->deviceEntry()],
                'updatedAt' => now()->utc(),
            ], $token, ['devices', 'updatedAt']);
            $status = 'approved';
        } else {
            $this->requestDeviceApproval($uid, $profile, $token);
        }

        $this->session->store([
            'uid' => $uid,
            'email' => $email,
            'name' => $profile['name'] ?? $email,
            'role' => $profile['role'] ?? 'staff',
            'id_token' => $token,
            'refresh_token' => $auth['refreshToken'],
            'expires_at' => time() + (int) $auth['expiresIn'],
            'device_status' => $status,
            'signed_in_at' => now()->toIso8601String(),
        ]);

        return $status;
    }

    /**
     * Poll the approval request of a pending device.
     *
     * @return 'approved'|'pending'|'denied'
     */
    public function checkPendingDevice(): string
    {
        $user = $this->session->user();
        if (! $user) {
            return 'denied';
        }

        $token = $this->session->token();
        $profile = $this->firebase->getDocument("users/{$user['uid']}", $token);

        if (! $profile || ! ($profile['active'] ?? false)) {
            return 'denied';
        }

        if ($this->deviceIsTied($profile['devices'] ?? [], $this->device->id())) {
            $this->session->update([
                'device_status' => 'approved',
                'role' => $profile['role'] ?? 'staff',
                'name' => $profile['name'] ?? $user['name'],
            ]);

            return 'approved';
        }

        $request = $this->firebase->getDocument($this->requestPath($user['uid']), $token);

        return ($request['status'] ?? 'pending') === 'denied' ? 'denied' : 'pending';
    }

    public function deviceIsTied(array $devices, string $deviceId): bool
    {
        foreach ($devices as $d) {
            if ((is_array($d) ? ($d['id'] ?? null) : $d) === $deviceId) {
                return true;
            }
        }

        return false;
    }

    public function deviceEntry(): array
    {
        return [
            'id' => $this->device->id(),
            'name' => $this->device->name(),
            'addedAt' => now()->utc()->toIso8601String(),
        ];
    }

    public function requestPath(string $uid): string
    {
        return "deviceRequests/{$uid}_{$this->device->id()}";
    }

    protected function requestDeviceApproval(string $uid, array $profile, string $token): void
    {
        $this->firebase->patchDocument($this->requestPath($uid), [
            'uid' => $uid,
            'name' => $profile['name'] ?? '',
            'email' => $profile['email'] ?? '',
            'deviceId' => $this->device->id(),
            'deviceName' => $this->device->name(),
            'status' => 'pending',
            'createdAt' => now()->utc(),
        ], $token);
    }
}
