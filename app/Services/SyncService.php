<?php

namespace App\Services;

use App\Models\AppState;
use App\Support\Venue;
use App\Models\Event;
use App\Services\Firebase\FirebaseClient;
use App\Services\Firebase\FirebaseException;
use App\Services\Firebase\OfflineException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Two-way sync between the local SQLite copy and Cloud Firestore.
 *
 *  push: every local row with sync_status = pending is written to events/{id}
 *        (batched commit, `updatedAt` set by the Firestore server clock).
 *  pull: every document whose `updatedAt` >= the last cursor is downloaded and
 *        upserted locally. A row that still has un-pushed local edits is kept.
 *
 * Deletes are soft (deleted = true) so they reach every device.
 * The app calls sync() on a timer while it is open (see config alqasr.sync_interval).
 */
class SyncService
{
    public const PAGE = 300;

    public function __construct(
        protected FirebaseClient $firebase,
        protected AuthSession $session,
        protected DeviceIdentity $device,
    ) {}

    /**
     * @return array{status:string, pushed:int, pulled:int, message?:string}
     */
    public function sync(): array
    {
        if (! $this->session->check()) {
            return ['status' => 'signed_out', 'pushed' => 0, 'pulled' => 0];
        }

        $lock = Cache::lock('alqasr-sync', 60);
        if (! $lock->get()) {
            return ['status' => 'busy', 'pushed' => 0, 'pulled' => 0];
        }

        AppState::put('sync.last_attempt_at', now()->toIso8601String());

        try {
            $token = $this->session->token();
            $this->verifyMembership($token);
            if (! $this->session->check()) {
                return ['status' => 'revoked', 'pushed' => 0, 'pulled' => 0];
            }

            $pushed = $this->push($token);
            $pulled = $this->pull($token);
            try {
                $this->syncVenue($token);
            } catch (Throwable $e) {
                // Never let the venue details block reservations (e.g. Firestore rules not updated yet).
                AppState::put(Venue::PULLED_AT, now()->toIso8601String());
                \Illuminate\Support\Facades\Log::warning('[sync] venue details: '.($e instanceof FirebaseException ? $e->errorCode : $e->getMessage()));
            }

            AppState::put('sync.online', true);
            AppState::put('sync.last_success_at', now()->toIso8601String());
            AppState::forget('sync.last_error');

            return ['status' => 'ok', 'pushed' => $pushed, 'pulled' => $pulled];
        } catch (OfflineException $e) {
            AppState::put('sync.online', false);

            return ['status' => 'offline', 'pushed' => 0, 'pulled' => 0];
        } catch (FirebaseException $e) {
            AppState::put('sync.online', true);
            AppState::put('sync.last_error', $e->errorCode);

            // Refresh token revoked / account deleted -> sign out of this device.
            if ($e->is('TOKEN_EXPIRED', 'USER_NOT_FOUND', 'USER_DISABLED', 'INVALID_REFRESH_TOKEN')) {
                $this->session->logout();

                return ['status' => 'revoked', 'pushed' => 0, 'pulled' => 0];
            }

            return ['status' => 'error', 'pushed' => 0, 'pulled' => 0, 'message' => $e->errorCode];
        } catch (Throwable $e) {
            report($e);
            AppState::put('sync.last_error', class_basename($e));

            return ['status' => 'error', 'pushed' => 0, 'pulled' => 0, 'message' => $e->getMessage()];
        } finally {
            $lock->release();
        }
    }

    /** Make sure this user is still active and this device is still tied to them. */
    protected function verifyMembership(string $token): void
    {
        $uid = $this->session->uid();
        $profile = $this->firebase->getDocument("users/{$uid}", $token);

        $tied = false;
        foreach (($profile['devices'] ?? []) as $d) {
            if ((is_array($d) ? ($d['id'] ?? null) : $d) === $this->device->id()) {
                $tied = true;
                break;
            }
        }

        if (! $profile || ! ($profile['active'] ?? false) || ! $tied) {
            $this->session->logout();

            return;
        }

        $this->session->update([
            'role' => $profile['role'] ?? 'staff',
            'name' => $profile['name'] ?? $this->session->name(),
        ]);
    }

    /** Push this admin's venue edit, and re-read the shared venue details now and then. */
    public function syncVenue(string $token, bool $force = false): void
    {
        if (Venue::dirty() && $this->session->isAdmin()) {
            $this->firebase->patchDocument('settings/venue', Venue::toRemote(), $token);
            AppState::forget(Venue::DIRTY);
            AppState::put(Venue::PULLED_AT, now()->toIso8601String());

            return;
        }

        $last = AppState::get(Venue::PULLED_AT);
        $every = (int) config('alqasr.venue.refresh_minutes', 10);
        if (! $force && $last && \Carbon\Carbon::parse($last)->diffInMinutes(now()) < $every) {
            return;
        }

        $doc = $this->firebase->getDocument('settings/venue', $token);
        AppState::put(Venue::PULLED_AT, now()->toIso8601String());
        if ($doc) {
            Venue::applyRemote($doc);
        }
    }

    public function push(string $token): int
    {
        $pending = Event::query()->where('sync_status', Event::PENDING)->orderBy('updated_at')->get();
        if ($pending->isEmpty()) {
            return 0;
        }

        foreach ($pending->chunk(400) as $chunk) {
            $writes = $chunk->map(fn (Event $e) => [
                'update' => [
                    'name' => $this->firebase->documentName('events/'.$e->id),
                    'fields' => $e->toFirestoreFields() + [
                        'createdAt' => ['timestampValue' => $e->created_at->utc()->format('Y-m-d\TH:i:s\Z')],
                    ],
                ],
                'updateTransforms' => [
                    ['fieldPath' => 'updatedAt', 'setToServerValue' => 'REQUEST_TIME'],
                ],
            ])->values()->all();

            $result = $this->firebase->commit($writes, $token);
            $results = $result['writeResults'] ?? [];

            // Only mark rows that were not edited again while we were pushing.
            foreach ($chunk->values() as $i => $e) {
                $serverTime = $results[$i]['transformResults'][0]['timestampValue'] ?? null;
                Event::query()->whereKey($e->id)
                    ->where('updated_at', $e->getRawOriginal('updated_at'))
                    ->update(array_filter([
                        'sync_status' => Event::SYNCED,
                        'remote_updated_at' => $serverTime ? static::normalizeTimestamp($serverTime) : null,
                    ]));
            }
        }

        return $pending->count();
    }

    public function pull(string $token): int
    {
        $cursor = AppState::get('sync.cursor');
        $count = 0;
        $guard = 0;

        do {
            $query = [
                'from' => [['collectionId' => 'events']],
                'orderBy' => [['field' => ['fieldPath' => 'updatedAt'], 'direction' => 'ASCENDING']],
                'limit' => self::PAGE,
            ];
            if ($cursor) {
                $query['where'] = ['fieldFilter' => [
                    'field' => ['fieldPath' => 'updatedAt'],
                    'op' => 'GREATER_THAN_OR_EQUAL',
                    'value' => ['timestampValue' => $cursor],
                ]];
            }

            $docs = $this->firebase->runQuery($query, $token);
            $previous = $cursor;

            DB::transaction(function () use ($docs, &$cursor, &$count) {
                foreach ($docs as $doc) {
                    $doc['updatedAt'] = isset($doc['updatedAt']) ? static::normalizeTimestamp($doc['updatedAt']) : null;
                    $cursor = max((string) $cursor, (string) $doc['updatedAt']);
                    $local = Event::query()->find($doc['id']);

                    if ($local && $local->sync_status === Event::PENDING) {
                        continue; // local edit wins; it is pushed on the next cycle
                    }
                    if ($local && $local->remote_updated_at === ($doc['updatedAt'] ?? null)) {
                        continue; // already have this version
                    }

                    $attrs = Event::attributesFromFirestore($doc) + [
                        'sync_status' => Event::SYNCED,
                        'remote_updated_at' => $doc['updatedAt'] ?? null,
                    ];

                    if ($local) {
                        $local->fill($attrs)->saveQuietly();
                    } else {
                        $e = new Event(['id' => $doc['id']] + $attrs);
                        $e->saveQuietly();
                    }
                    $count++;
                }
            });

            AppState::put('sync.cursor', $cursor);
            $guard++;
        // Another full page with an advancing cursor means there may be more.
        } while (count($docs) >= self::PAGE && $cursor !== $previous && $guard < 50);

        return $count;
    }

    /**
     * RFC3339 timestamps from Firestore have a variable number of fraction digits;
     * normalise to 9 digits so they compare correctly as strings.
     */
    public static function normalizeTimestamp(string $ts): string
    {
        if (! preg_match('/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})(?:\.(\d+))?Z$/', $ts, $m)) {
            return $ts;
        }

        return $m[1].'.'.str_pad(substr($m[2] ?? '', 0, 9), 9, '0').'Z';
    }

    public function pendingCount(): int
    {
        return Event::query()->where('sync_status', Event::PENDING)->count();
    }

    /** @return array{online:bool|null, last_success_at:?string, pending:int, error:?string} */
    public function status(): array
    {
        return [
            'online' => AppState::get('sync.online'),
            'last_success_at' => AppState::get('sync.last_success_at'),
            'pending' => $this->pendingCount(),
            'error' => AppState::get('sync.last_error'),
        ];
    }
}
