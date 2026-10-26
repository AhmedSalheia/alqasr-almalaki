<?php

namespace App\Livewire;

use App\Services\AuthSession;
use App\Services\DeviceIdentity;
use App\Services\Firebase\FirebaseClient;
use App\Services\Firebase\FirebaseException;
use App\Services\Firebase\OfflineException;
use App\Services\Reminders\ReminderService;
use App\Services\SyncScheduler;
use App\Services\SyncService;
use App\Support\Phone;
use App\Support\Prefs;
use App\Support\Venue;
use Livewire\Component;

class Settings extends Component
{
    public bool $confirmingLogout = false;

    public string $newPassword = '';

    public bool $changingPassword = false;

    public bool $editingVenue = false;

    public string $venueLocation = '';

    public string $venuePhone = '';

    public string $venueDjName = '';

    public string $venueDjPhoneCode = Phone::DEFAULT_CODE;

    public string $venueDjPhone = '';

    public function editVenue(AuthSession $session): void
    {
        abort_unless($session->isAdmin(), 403);
        $v = Venue::get();
        $this->venueLocation = $v['location'];
        $this->venuePhone = $v['phone'];
        $this->venueDjName = $v['dj_name'];
        [$this->venueDjPhoneCode, $this->venueDjPhone] = Phone::split($v['dj_phone']);
        $this->resetErrorBag();
        $this->editingVenue = ! $this->editingVenue;
    }

    public function saveVenue(AuthSession $session, SyncScheduler $scheduler): void
    {
        abort_unless($session->isAdmin(), 403);
        $this->venuePhone = Phone::normalize($this->venuePhone);
        $this->venueDjPhone = Phone::digits($this->venueDjPhone);

        $this->validate([
            'venueLocation' => ['required', 'string', 'max:80'],
            'venuePhone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{6,30}$/'],
            'venueDjName' => ['required', 'string', 'max:120'],
            'venueDjPhoneCode' => ['required', \Illuminate\Validation\Rule::in(Phone::CODES)],
            'venueDjPhone' => ['required', 'string', 'regex:/^[0-9]{6,15}$/'],
        ], [], [
            'venueLocation' => __('app.venue_location'),
            'venuePhone' => __('app.venue_phone'),
            'venueDjName' => __('app.venue_dj_name'),
            'venueDjPhone' => __('app.venue_dj_phone'),
        ]);

        Venue::save([
            'location' => $this->venueLocation,
            'phone' => $this->venuePhone,
            'dj_name' => $this->venueDjName,
            'dj_phone' => Phone::join($this->venueDjPhoneCode, $this->venueDjPhone),
        ]);
        $scheduler->request();
        $this->editingVenue = false;
        $this->js('toast('.json_encode(__('app.venue_saved')).')');
    }

    public function setLocale(string $locale)
    {
        Prefs::setLocale($locale);

        return $this->redirectRoute('settings');
    }

    public function setTheme(string $theme): void
    {
        Prefs::setTheme($theme);
        $this->js("document.documentElement.dataset.theme = '".Prefs::theme()."'; document.documentElement.classList.toggle('dark', '".Prefs::theme()."' === 'dark' || ('".Prefs::theme()."' === 'system' && matchMedia('(prefers-color-scheme: dark)').matches));");
    }

    public function syncNow(SyncScheduler $scheduler): void
    {
        FirebaseClient::markOnline(); // explicit request: try right away
        $result = $scheduler->runNow();
        $ok = ($result['status'] ?? '') === 'ok';
        $this->js('toast('.json_encode($ok ? __('app.synced') : __('app.offline')).', '.json_encode($ok ? 'success' : 'error').')');
        $this->dispatch('synced');
    }

    public function changePassword(AuthSession $session, FirebaseClient $firebase): void
    {
        $this->validate(['newPassword' => ['required', 'string', 'min:6', 'max:100']], [], ['newPassword' => __('app.new_password')]);

        try {
            $res = $firebase->changePassword($session->token(), $this->newPassword);
            $session->update([
                'id_token' => $res['idToken'] ?? null,
                'refresh_token' => $res['refreshToken'] ?? $session->user()['refresh_token'],
                'expires_at' => time() + (int) ($res['expiresIn'] ?? 0),
            ]);
            $this->newPassword = '';
            $this->changingPassword = false;
            $this->js('toast('.json_encode(__('app.password_changed')).')');
        } catch (OfflineException) {
            $this->addError('newPassword', __('app.requires_online'));
        } catch (FirebaseException $e) {
            $this->addError('newPassword', __('admin.errors.'.$e->errorCode) !== 'admin.errors.'.$e->errorCode ? __('admin.errors.'.$e->errorCode) : $e->errorCode);
        }
    }

    public function checkUpdates(\App\Services\Updates\UpdateChecker $checker): void
    {
        FirebaseClient::markOnline(); // explicit request: try right away
        $result = $checker->check();

        [$message, $type] = match ($result['status']) {
            'available' => [__('app.update_found', ['version' => $result['info']['version']]), 'success'],
            'up_to_date' => [__('app.up_to_date'), 'success'],
            'not_configured' => [__('app.updates_not_configured'), 'error'],
            'offline' => [__('app.requires_online'), 'error'],
            default => [__('app.error_generic'), 'error'],
        };
        if ($result['status'] === 'available') {
            $this->dispatch('update-found');
        }
        $this->js('toast('.json_encode($message).', '.json_encode($type).')');
    }

    public function toggleReminders(ReminderService $reminders): void
    {
        $on = ! ReminderService::enabled();
        ReminderService::setEnabled($on);
        if ($on) {
            $reminders->requestPermission();
        }
        $reminders->refresh();
    }

    public function logout(AuthSession $session, ReminderService $reminders)
    {
        $reminders->clear();
        $session->logout();

        return $this->redirectRoute('login');
    }

    public function render(AuthSession $session, SyncService $sync, DeviceIdentity $device)
    {
        return view('livewire.settings', [
            'user' => $session->user(),
            'status' => $sync->status(),
            'locale' => Prefs::locale(),
            'theme' => Prefs::theme(),
            'deviceId' => $device->id(),
            'deviceName' => $device->name(),
            'interval' => (int) config('alqasr.sync_interval'),
            'remindersOn' => ReminderService::enabled(),
            'remindersCount' => count(\App\Models\AppState::get(ReminderService::STATE, [])),
            'reminderDays' => (int) config('alqasr.reminders.days_before'),
            'reminderHour' => (int) config('alqasr.reminders.hour'),
            'venue' => Venue::get(),
            'isAdmin' => $session->isAdmin(),
        ])->title(__('app.settings'));
    }
}
