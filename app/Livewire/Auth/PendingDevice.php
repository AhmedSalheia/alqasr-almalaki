<?php

namespace App\Livewire\Auth;

use App\Services\AuthService;
use App\Services\AuthSession;
use App\Services\DeviceIdentity;
use App\Services\Firebase\FirebaseException;
use App\Services\Firebase\OfflineException;
use App\Services\SyncScheduler;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.auth')]
class PendingDevice extends Component
{
    public string $state = 'pending';

    public function mount(AuthSession $session)
    {
        if ($session->check()) {
            return $this->redirectRoute('calendar');
        }
        if (! $session->pendingDevice()) {
            return $this->redirectRoute('login');
        }
    }

    public function check(AuthService $auth, SyncScheduler $scheduler)
    {
        try {
            $this->state = $auth->checkPendingDevice();
        } catch (OfflineException|FirebaseException) {
            return null;
        }

        if ($this->state === 'approved') {
            $scheduler->runNow();

            return $this->redirectRoute('calendar', navigate: true);
        }

        return null;
    }

    public function useOtherAccount(AuthSession $session)
    {
        $session->logout();

        return $this->redirectRoute('login', navigate: true);
    }

    public function render(AuthSession $session, DeviceIdentity $device)
    {
        return view('livewire.auth.pending-device', [
            'user' => $session->user(),
            'deviceName' => $device->name(),
        ])->title(__('access.pending_title'));
    }
}
