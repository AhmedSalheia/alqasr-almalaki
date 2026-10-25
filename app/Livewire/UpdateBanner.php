<?php

namespace App\Livewire;

use App\Models\AppState;
use App\Services\Updates\UpdateChecker;
use App\Services\Updates\UpdateGateway;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * "New version available" banner: checks GitHub Releases now and then, downloads the APK in
 * the background (Wi-Fi only when automatic) and opens Android's installer when it is ready.
 */
class UpdateBanner extends Component
{
    /** Called by the page every minute (with navigator.onLine). Cheap unless a check is due. */
    public function tick(UpdateChecker $checker, UpdateGateway $gateway, bool $online = true): void
    {
        if ($online && config('alqasr.updates.auto_check') && $checker->due()) {
            $checker->check();
        }

        $info = $checker->cached();
        if (! $info || ! $gateway->supported() || $this->dismissed($info)) {
            return;
        }

        $status = $gateway->status();
        $sameVersion = ($status['version'] ?? '') === $info['version'];
        $busyOrDone = in_array($status['state'] ?? 'idle', ['downloading', 'done'], true) && $sameVersion;

        if ($online && ! $busyOrDone) {
            // Automatic download only on Wi-Fi; on mobile data the user taps "Download".
            $gateway->download($info['apk_url'], $info['version'], true);
        }
    }

    public function download(UpdateChecker $checker, UpdateGateway $gateway): void
    {
        if ($info = $checker->cached()) {
            $res = $gateway->download($info['apk_url'], $info['version'], false);
            if (! ($res['started'] ?? false) && ! empty($res['error'])) {
                $this->js('toast('.json_encode(__('app.update_failed', ['error' => $res['error']])).', "error")');
            }
        }
    }

    public function install(UpdateGateway $gateway): void
    {
        $res = $gateway->install();
        if ($res['needsPermission'] ?? false) {
            $this->js('toast('.json_encode(__('app.update_allow_installs')).')');
        } elseif (! ($res['success'] ?? false)) {
            $this->js('toast('.json_encode(__('app.update_failed', ['error' => $res['error'] ?? '?'])).', "error")');
        }
    }

    public function dismiss(UpdateChecker $checker): void
    {
        if ($info = $checker->cached()) {
            AppState::put('update.dismissed', $info['version']);
        }
    }

    #[On('update-found')]
    public function refreshBanner(): void
    {
        AppState::forget('update.dismissed');
    }

    protected function dismissed(array $info): bool
    {
        return AppState::get('update.dismissed') === $info['version'];
    }

    public function render(UpdateChecker $checker, UpdateGateway $gateway)
    {
        $info = $checker->cached();
        $status = $info && $gateway->supported() ? $gateway->status() : [];
        if ($info && ($status['version'] ?? '') !== $info['version']) {
            $status = ['state' => 'idle']; // status of an older download
        }

        return view('livewire.update-banner', [
            'info' => $info && ! $this->dismissed($info) ? $info : null,
            'status' => $status,
            'native' => $gateway->supported(),
            'current' => $checker->currentVersion(),
        ]);
    }
}
