<?php

namespace App\Livewire\Admin;

use App\Livewire\Admin\Concerns\HandlesFirebaseErrors;
use App\Services\AuthSession;
use App\Services\Firebase\FirebaseClient;
use App\Services\Firebase\FirebaseException;
use App\Services\Firebase\OfflineException;
use App\Services\UserDirectory;
use Livewire\Component;

class Users extends Component
{
    use HandlesFirebaseErrors;

    public array $users = [];

    public bool $loaded = false;

    public bool $creating = false;

    public string $name = '';

    public string $login = '';

    public string $password = '';

    public string $role = 'staff';

    public ?string $expanded = null;

    public function load(UserDirectory $directory, bool $online = true, bool $retry = false): void
    {
        $this->loadError = null;
        $this->loaded = true;

        if (! $online) {
            $this->loadError = __('app.requires_online');

            return;
        }
        if ($retry) {
            FirebaseClient::markOnline(); // the user asked: try right away
        }

        try {
            $this->users = $directory->users();
        } catch (OfflineException) {
            $this->loadError = __('app.requires_online');
        } catch (FirebaseException $e) {
            $this->loadError = $this->firebaseMessage($e);
        }
        $this->loaded = true;
    }

    public function create(UserDirectory $directory): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:80'],
            'login' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9._%+\-@]+$/'],
            'password' => ['required', 'string', 'min:6', 'max:100'],
            'role' => ['required', 'in:admin,staff'],
        ], [], [
            'name' => __('admin.name'),
            'login' => __('admin.login'),
            'password' => __('admin.password'),
        ]);

        $ok = $this->attempt(fn () => $directory->create($this->name, $this->login, $this->password, $this->role), __('admin.created'));
        if ($ok) {
            $this->reset('name', 'login', 'password', 'role', 'creating');
            $this->load($directory);
        }
    }

    public function toggleActive(UserDirectory $directory, string $uid): void
    {
        $user = collect($this->users)->firstWhere('id', $uid);
        if (! $user) {
            return;
        }
        if ($this->attempt(fn () => $directory->update($uid, ['active' => ! ($user['active'] ?? false)]), __('app.saved'))) {
            $this->load($directory);
        }
    }

    public function toggleRole(UserDirectory $directory, string $uid): void
    {
        $user = collect($this->users)->firstWhere('id', $uid);
        if (! $user) {
            return;
        }
        $role = ($user['role'] ?? 'staff') === 'admin' ? 'staff' : 'admin';
        if ($this->attempt(fn () => $directory->update($uid, ['role' => $role]), __('app.saved'))) {
            $this->load($directory);
        }
    }

    public function removeDevice(UserDirectory $directory, string $uid, string $deviceId): void
    {
        if ($this->attempt(fn () => $directory->removeDevice($uid, $deviceId), __('app.saved'))) {
            $this->load($directory);
        }
    }

    public function sendReset(UserDirectory $directory, string $email): void
    {
        $this->attempt(fn () => $directory->sendPasswordReset($email), __('admin.reset_sent'));
    }

    public function render(AuthSession $session)
    {
        return view('livewire.admin.users', ['me' => $session->uid()])->title(__('admin.users_title'));
    }
}
