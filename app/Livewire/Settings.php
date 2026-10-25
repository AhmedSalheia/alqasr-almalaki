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
use App\Support\Prefs;
use Livewire\Component;

class Settings extends Component
{
    public bool $confirmingLogout = false;

    public string $newPassword = '';

    public bool $changingPassword = false;

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
        ])->title(__('app.settings'));
    }
}
