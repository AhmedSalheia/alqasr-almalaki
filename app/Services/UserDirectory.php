<?php

namespace App\Services;

use App\Services\Firebase\FirebaseClient;
use App\Services\Firebase\FirebaseException;

/**
 * Admin-only operations on app users and device approvals (online only).
 */
class UserDirectory
{
    public function __construct(
        protected FirebaseClient $firebase,
        protected AuthSession $session,
        protected AuthService $auth,
    ) {}

    /** @return array<int, array> */
    public function users(): array
    {
        $users = $this->firebase->listDocuments('users', $this->session->token());
        usort($users, fn ($a, $b) => [($a['role'] ?? '') !== 'admin', mb_strtolower($a['name'] ?? '')] <=> [($b['role'] ?? '') !== 'admin', mb_strtolower($b['name'] ?? '')]);

        return $users;
    }

    public function create(string $name, string $login, string $password, string $role): string
    {
        $email = $this->auth->normalizeLogin($login);
        $uid = $this->firebase->signUp($email, $password);

        $this->firebase->patchDocument("users/{$uid}", [
            'name' => $name,
            'email' => $email,
            'role' => $role === 'admin' ? 'admin' : 'staff',
            'active' => true,
            'devices' => [],
            'createdAt' => now()->utc(),
            'createdBy' => $this->session->uid(),
            'updatedAt' => now()->utc(),
        ], $this->session->token());

        return $uid;
    }

    public function update(string $uid, array $changes): void
    {
        if ($uid === $this->session->uid() && (array_key_exists('active', $changes) || array_key_exists('role', $changes))) {
            throw new FirebaseException('CANNOT_CHANGE_SELF');
        }

        $changes['updatedAt'] = now()->utc();
        $this->firebase->patchDocument("users/{$uid}", $changes, $this->session->token(), array_keys($changes));
    }

    public function removeDevice(string $uid, string $deviceId): void
    {
        $user = $this->firebase->getDocument("users/{$uid}", $this->session->token());
        $devices = array_values(array_filter($user['devices'] ?? [], fn ($d) => (is_array($d) ? ($d['id'] ?? null) : $d) !== $deviceId));
        $this->update($uid, ['devices' => $devices]);
    }

    public function sendPasswordReset(string $email): void
    {
        $this->firebase->sendPasswordReset($email);
    }

    /** @return array<int, array> */
    public function pendingRequests(): array
    {
        $docs = $this->firebase->runQuery([
            'from' => [['collectionId' => 'deviceRequests']],
            'where' => ['fieldFilter' => [
                'field' => ['fieldPath' => 'status'],
                'op' => 'EQUAL',
                'value' => ['stringValue' => 'pending'],
            ]],
            'limit' => 100,
        ], $this->session->token());

        usort($docs, fn ($a, $b) => strcmp((string) ($b['createdAt'] ?? ''), (string) ($a['createdAt'] ?? '')));

        return $docs;
    }

    public function approve(string $requestId): void
    {
        $token = $this->session->token();
        $req = $this->firebase->getDocument("deviceRequests/{$requestId}", $token);
        if (! $req) {
            return;
        }

        $user = $this->firebase->getDocument("users/{$req['uid']}", $token);
        if ($user) {
            $devices = $user['devices'] ?? [];
            if (! $this->auth->deviceIsTied($devices, $req['deviceId'])) {
                $devices[] = [
                    'id' => $req['deviceId'],
                    'name' => $req['deviceName'] ?? '',
                    'addedAt' => now()->utc()->toIso8601String(),
                ];
            }
            $this->update($req['uid'], ['devices' => $devices]);
        }

        $this->firebase->patchDocument("deviceRequests/{$requestId}", [
            'status' => 'approved',
            'decidedBy' => $this->session->uid(),
            'decidedAt' => now()->utc(),
        ], $token, ['status', 'decidedBy', 'decidedAt']);
    }

    public function deny(string $requestId): void
    {
        $this->firebase->patchDocument("deviceRequests/{$requestId}", [
            'status' => 'denied',
            'decidedBy' => $this->session->uid(),
            'decidedAt' => now()->utc(),
        ], $this->session->token(), ['status', 'decidedBy', 'decidedAt']);
    }
}
