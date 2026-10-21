<?php

namespace App\Livewire\Auth;

use App\Services\AuthFailed;
use App\Services\AuthService;
use App\Services\Firebase\OfflineException;
use App\Services\SyncScheduler;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.auth')]
class Login extends Component
{
    public string $login = '';

    public string $password = '';

    public ?string $error = null;

    public function submit(AuthService $auth, SyncScheduler $scheduler, bool $online = true)
    {
        $this->validate([
            'login' => ['required', 'string', 'max:190'],
            'password' => ['required', 'string', 'max:190'],
        ], [], [
            'login' => __('access.login'),
            'password' => __('access.password'),
        ]);

        $this->error = null;

        if (! $online) {
            $this->error = __('access.errors.offline');

            return null;
        }
        \App\Services\Firebase\FirebaseClient::markOnline(); // explicit sign in: try right away

        try {
            $status = $auth->login($this->login, $this->password);
        } catch (OfflineException) {
            $this->error = __('access.errors.offline');

            return null;
        } catch (AuthFailed $e) {
            $this->error = __('access.errors.'.$e->reason);

            return null;
        }

        $this->password = '';

        if ($status === 'pending') {
            return $this->redirectRoute('device.pending', navigate: true);
        }

        // First download of all reservations.
        $scheduler->runNow();

        return $this->redirectRoute('calendar', navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.login')->title(__('access.sign_in'));
    }
}
