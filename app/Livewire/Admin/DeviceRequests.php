<?php

namespace App\Livewire\Admin;

use App\Livewire\Admin\Concerns\HandlesFirebaseErrors;
use App\Services\Firebase\FirebaseException;
use App\Services\Firebase\OfflineException;
use App\Services\UserDirectory;
use Livewire\Component;

class DeviceRequests extends Component
{
    use HandlesFirebaseErrors;

    public array $requests = [];

    public bool $loaded = false;

    public function load(UserDirectory $directory): void
    {
        $this->loadError = null;
        try {
            $this->requests = $directory->pendingRequests();
        } catch (OfflineException) {
            $this->loadError = __('app.requires_online');
        } catch (FirebaseException $e) {
            $this->loadError = $this->firebaseMessage($e);
        }
        $this->loaded = true;
    }

    public function approve(UserDirectory $directory, string $id): void
    {
        if ($this->attempt(fn () => $directory->approve($id), __('admin.approved'))) {
            $this->load($directory);
        }
    }

    public function deny(UserDirectory $directory, string $id): void
    {
        if ($this->attempt(fn () => $directory->deny($id), __('admin.denied'))) {
            $this->load($directory);
        }
    }

    public function render()
    {
        return view('livewire.admin.device-requests')->title(__('admin.requests_title'));
    }
}
