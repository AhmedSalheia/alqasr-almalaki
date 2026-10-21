<?php

namespace App\Livewire;

use App\Models\AppState;
use App\Services\AuthSession;
use App\Services\SyncScheduler;
use App\Services\Reminders\ReminderService;
use App\Services\SyncService;
use Livewire\Component;

/**
 * Header indicator + the periodic sync timer (wire:poll).
 */
class SyncStatus extends Component
{
    public int $revision = 0;

    public bool $busy = false;

    public function mount(): void
    {
        $this->revision = (int) AppState::get('sync.revision', 0);
    }

    /** Called by wire:poll every N seconds and once on load. */
    public function tick(SyncScheduler $scheduler, AuthSession $session, ReminderService $reminders): void
    {
        if (! $session->check()) {
            $reminders->clear();
            $this->redirectRoute('login', navigate: true);

            return;
        }

        $scheduler->request();
        $this->checkRevision($session);

        // Keep the phone's reminder notifications in line with the (synced) calendar.
        $reminders->askPermissionOnce();
        $reminders->refresh();
    }

    public function syncNow(SyncScheduler $scheduler, AuthSession $session): void
    {
        $result = $scheduler->runNow();
        $this->checkRevision($session);

        $msg = match ($result['status'] ?? null) {
            'ok' => __('app.synced'),
            'offline' => __('app.offline'),
            default => null,
        };
        if ($msg) {
            $this->js('toast('.json_encode($msg).', '.json_encode(($result['status'] ?? '') === 'ok' ? 'success' : 'error').')');
        }
    }

    protected function checkRevision(AuthSession $session): void
    {
        if (! $session->check()) {
            app(ReminderService::class)->clear();
            session()->flash('toast', __('access.revoked'));
            session()->flash('toast_type', 'error');
            $this->redirectRoute('login');

            return;
        }

        $rev = (int) AppState::get('sync.revision', 0);
        if ($rev !== $this->revision) {
            $this->revision = $rev;
            $this->dispatch('synced');
        }
    }

    public function render(SyncService $sync)
    {
        return view('livewire.sync-status', [
            'status' => $sync->status(),
            'interval' => max(10, (int) config('alqasr.sync_interval')),
        ]);
    }
}
