<?php

namespace Tests;

use App\Models\AppState;
use App\Services\Firebase\FirebaseClient;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected const DOCS = 'https://firestore.googleapis.com/v1/projects/alqasr-test/databases/(default)/documents';

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Http::preventStrayRequests();
    }

    protected function signIn(string $role = 'admin', string $uid = 'u1'): void
    {
        AppState::put('device_id', 'device-A');
        AppState::put('device_name', 'Test phone');
        AppState::put('auth', [
            'uid' => $uid,
            'email' => 'admin@alqasr-almalaky.app',
            'name' => 'Admin',
            'role' => $role,
            'id_token' => 'token-1',
            'refresh_token' => 'refresh-1',
            'expires_at' => time() + 3600,
            'device_status' => 'approved',
        ]);
    }

    /** Firestore REST document JSON. */
    protected function doc(string $path, array $data, ?string $updateTime = null): array
    {
        $fields = (array) \App\Services\Firebase\Firestore::encodeFields($data);

        return [
            'name' => 'projects/alqasr-test/databases/(default)/documents/'.$path,
            'fields' => $fields,
            'createTime' => '2026-10-01T10:00:00.000000Z',
            'updateTime' => $updateTime ?? '2026-10-01T10:00:00.000000Z',
        ];
    }
}
