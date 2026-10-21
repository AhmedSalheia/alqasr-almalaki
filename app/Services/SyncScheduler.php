<?php

namespace App\Services;

use App\Jobs\SyncEvents;
use App\Models\AppState;
use App\Support\Native;
use Carbon\Carbon;
use Throwable;

/**
 * Decides HOW a sync runs:
 *  - on the phone: queued for NativePHP's background worker (UI never blocks);
 *    if the worker has not picked anything up for a while we fall back to inline.
 *  - in the browser (development): inline.
 */
class SyncScheduler
{
    public function __construct(protected SyncService $sync) {}

    public function request(bool $force = false): void
    {
        if (! $force && Native::isNative() && config('queue.default') !== 'sync' && ! $this->workerLooksStuck()) {
            try {
                if (! AppState::get('sync.queued_at')) {
                    AppState::put('sync.queued_at', now()->toIso8601String());
                }
                SyncEvents::dispatch();

                return;
            } catch (Throwable $e) {
                report($e);
            }
        }

        $this->runNow();
    }

    public function runNow(): array
    {
        (new SyncEvents)->handle($this->sync);
        AppState::forget('sync.queued_at');

        return AppState::get('sync.last_result', []);
    }

    protected function workerLooksStuck(): bool
    {
        $queuedAt = AppState::get('sync.queued_at');
        $lastAttempt = AppState::get('sync.last_attempt_at');

        if (! $queuedAt) {
            return false;
        }

        // The job ran after it was queued -> worker alive.
        if ($lastAttempt && Carbon::parse($lastAttempt)->gte(Carbon::parse($queuedAt))) {
            AppState::forget('sync.queued_at');

            return false;
        }

        return Carbon::parse($queuedAt)->lt(now()->subSeconds(max(45, config('alqasr.sync_interval') * 2)));
    }
}
