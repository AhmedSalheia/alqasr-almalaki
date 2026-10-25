<?php

namespace App\Services\Firebase;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Minimal Firebase REST client (Authentication + Cloud Firestore).
 *
 * The REST API is used on purpose: it runs inside NativePHP's embedded PHP runtime
 * without any native SDK, and the very same code runs in the browser for development.
 */
class FirebaseClient
{
    public const AUTH_URL = 'https://identitytoolkit.googleapis.com/v1';

    public const TOKEN_URL = 'https://securetoken.googleapis.com/v1/token';

    public const FIRESTORE_URL = 'https://firestore.googleapis.com/v1';

    public function __construct(
        protected ?string $apiKey,
        protected ?string $projectId,
        protected string $databaseId = '(default)',
        protected int $connectTimeout = 3,
        protected int $timeout = 10,
    ) {}

    public static function fromConfig(): static
    {
        $c = config('alqasr.firebase');

        return new static($c['api_key'], $c['project_id'], $c['database_id'] ?: '(default)', (int) $c['connect_timeout'], (int) $c['timeout']);
    }

    public function isConfigured(): bool
    {
        return filled($this->apiKey) && filled($this->projectId);
    }

    // ------------------------------------------------------------------ Auth

    /** @return array{idToken:string, refreshToken:string, localId:string, email:string, expiresIn:string} */
    public function signIn(string $email, string $password): array
    {
        return $this->send(fn (PendingRequest $h) => $h->post(self::AUTH_URL.'/accounts:signInWithPassword?key='.$this->apiKey, [
            'email' => $email,
            'password' => $password,
            'returnSecureToken' => true,
        ]));
    }

    /** Create a new Email/Password account. Returns the new user's uid. */
    public function signUp(string $email, string $password): string
    {
        $res = $this->send(fn (PendingRequest $h) => $h->post(self::AUTH_URL.'/accounts:signUp?key='.$this->apiKey, [
            'email' => $email,
            'password' => $password,
            'returnSecureToken' => false,
        ]));

        return $res['localId'];
    }

    /** @return array{id_token:string, refresh_token:string, expires_in:string, user_id:string} */
    public function refresh(string $refreshToken): array
    {
        return $this->send(fn (PendingRequest $h) => $h->asForm()->post(self::TOKEN_URL.'?key='.$this->apiKey, [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]));
    }

    public function changePassword(string $idToken, string $newPassword): array
    {
        return $this->send(fn (PendingRequest $h) => $h->post(self::AUTH_URL.'/accounts:update?key='.$this->apiKey, [
            'idToken' => $idToken,
            'password' => $newPassword,
            'returnSecureToken' => true,
        ]));
    }

    public function sendPasswordReset(string $email): void
    {
        $this->send(fn (PendingRequest $h) => $h->post(self::AUTH_URL.'/accounts:sendOobCode?key='.$this->apiKey, [
            'requestType' => 'PASSWORD_RESET',
            'email' => $email,
        ]));
    }

    // ------------------------------------------------------------- Firestore

    public function documentsRoot(): string
    {
        return "projects/{$this->projectId}/databases/{$this->databaseId}/documents";
    }

    public function documentName(string $path): string
    {
        return $this->documentsRoot().'/'.ltrim($path, '/');
    }

    /** Returns the decoded document or null when it does not exist. */
    public function getDocument(string $path, string $idToken): ?array
    {
        try {
            $doc = $this->send(fn (PendingRequest $h) => $h->withToken($idToken)
                ->get(self::FIRESTORE_URL.'/'.$this->documentName($path)));
        } catch (FirebaseException $e) {
            if ($e->status === 404 || $e->is('NOT_FOUND')) {
                return null;
            }
            throw $e;
        }

        return Firestore::decodeDocument($doc);
    }

    /**
     * Create/replace a document, or update only $mask fields when given.
     */
    public function patchDocument(string $path, array $data, string $idToken, ?array $mask = null): array
    {
        $query = '';
        if ($mask !== null) {
            $query = '?'.implode('&', array_map(fn ($f) => 'updateMask.fieldPaths='.rawurlencode($f), $mask));
        }

        $doc = $this->send(fn (PendingRequest $h) => $h->withToken($idToken)
            ->patch(self::FIRESTORE_URL.'/'.$this->documentName($path).$query, [
                'fields' => Firestore::encodeFields($data),
            ]));

        return Firestore::decodeDocument($doc);
    }

    public function deleteDocument(string $path, string $idToken): void
    {
        $this->send(fn (PendingRequest $h) => $h->withToken($idToken)
            ->delete(self::FIRESTORE_URL.'/'.$this->documentName($path)));
    }

    /** @return array<int, array> decoded documents */
    public function listDocuments(string $collection, string $idToken, int $pageSize = 300): array
    {
        $out = [];
        $pageToken = null;
        do {
            $res = $this->send(fn (PendingRequest $h) => $h->withToken($idToken)
                ->get(self::FIRESTORE_URL.'/'.$this->documentName($collection), array_filter([
                    'pageSize' => $pageSize,
                    'pageToken' => $pageToken,
                ])));
            foreach ($res['documents'] ?? [] as $doc) {
                $out[] = Firestore::decodeDocument($doc);
            }
            $pageToken = $res['nextPageToken'] ?? null;
        } while ($pageToken);

        return $out;
    }

    /**
     * Run a structured query. Returns decoded documents.
     *
     * @return array<int, array>
     */
    public function runQuery(array $structuredQuery, string $idToken): array
    {
        $res = $this->send(fn (PendingRequest $h) => $h->withToken($idToken)
            ->post(self::FIRESTORE_URL.'/'.$this->documentsRoot().':runQuery', [
                'structuredQuery' => $structuredQuery,
            ]));

        $docs = [];
        foreach ($res as $row) {
            if (isset($row['document'])) {
                $docs[] = Firestore::decodeDocument($row['document']);
            }
        }

        return $docs;
    }

    /** Atomic batch of writes (max 500). */
    public function commit(array $writes, string $idToken): array
    {
        return $this->send(fn (PendingRequest $h) => $h->withToken($idToken)
            ->post(self::FIRESTORE_URL.'/'.$this->documentsRoot().':commit', ['writes' => $writes]));
    }

    // -------------------------------------------------------------- Internals

    /** Seconds to skip network calls after a connection failure (keeps offline screens snappy). */
    public const OFFLINE_BACKOFF = 20;

    public static function markOffline(int $seconds = self::OFFLINE_BACKOFF): void
    {
        \App\Models\AppState::put('net.offline_until', time() + $seconds);
    }

    public static function markOnline(): void
    {
        \App\Models\AppState::forget('net.offline_until');
    }

    public static function recentlyOffline(): bool
    {
        return (int) \App\Models\AppState::get('net.offline_until', 0) > time();
    }

    protected function send(callable $call): array
    {
        // We just failed to reach the network: fail fast instead of blocking the screen again.
        if (static::recentlyOffline()) {
            throw new OfflineException('offline (waiting before retrying)');
        }

        if (! $this->isConfigured()) {
            throw new FirebaseException('NOT_CONFIGURED', 'Firebase is not configured. Set FIREBASE_API_KEY and FIREBASE_PROJECT_ID in .env');
        }

        try {
            /** @var Response $response */
            $response = $call(Http::acceptJson()
                ->connectTimeout($this->connectTimeout)
                ->timeout($this->timeout));
        } catch (ConnectionException $e) {
            static::markOffline();
            throw new OfflineException($e->getMessage(), 0, $e);
        } catch (\Illuminate\Http\Client\RequestException $e) {
            $response = $e->response;
        } catch (\GuzzleHttp\Exception\TransferException $e) {
            // Any other transport failure without an HTTP answer (TLS, reset, captive network):
            // treat it as offline so it never signs anyone out or blocks the screen.
            if (! ($e instanceof \GuzzleHttp\Exception\RequestException) || ! $e->hasResponse()) {
                static::markOffline();
                throw new OfflineException($e->getMessage(), 0, $e);
            }
            throw $e;
        }

        static::markOnline();

        if ($response->failed()) {
            $json = $response->json();
            $error = $json['error'] ?? $json[0]['error'] ?? [];
            $code = is_array($error)
                ? (string) ($error['message'] ?? $error['status'] ?? 'HTTP_'.$response->status())
                : (string) ($json['error_description'] ?? $error);

            // Firestore uses "status" for the machine code and "message" for prose.
            if (is_array($error) && isset($error['status']) && str_contains($code, ' ')) {
                $code = $error['status'];
            }

            throw new FirebaseException(strtoupper($code), $code, $response->status());
        }

        return $response->json() ?? [];
    }
}
