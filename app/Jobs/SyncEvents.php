<?php

namespace App\Jobs;

use App\Models\AppState;
use App\Services\SyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Runs a sync on NativePHP's background queue worker so the UI never waits on the network.
 */
class SyncEvents implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public int $uniqueFor = 60;

    public function handle(SyncService $sync): void
    {
        $result = $sync->sync();
        AppState::put('sync.last_result', $result);

        if (($result['pulled'] ?? 0) > 0 || ($result['pushed'] ?? 0) > 0 || ($result['status'] ?? '') === 'revoked') {
            AppState::put('sync.revision', (int) AppState::get('sync.revision', 0) + 1);
        }
    }
}
