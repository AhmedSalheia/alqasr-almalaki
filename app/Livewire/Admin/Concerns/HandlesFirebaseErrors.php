<?php

namespace App\Livewire\Admin\Concerns;

use App\Services\Firebase\FirebaseException;
use App\Services\Firebase\OfflineException;
use Throwable;

trait HandlesFirebaseErrors
{
    public ?string $loadError = null;

    protected function attempt(callable $fn, ?string $success = null): bool
    {
        try {
            $fn();
            if ($success) {
                $this->js('toast('.json_encode($success).')');
            }

            return true;
        } catch (OfflineException) {
            $this->js('toast('.json_encode(__('app.requires_online')).', "error")');
        } catch (FirebaseException $e) {
            $this->js('toast('.json_encode($this->firebaseMessage($e)).', "error")');
        } catch (Throwable $e) {
            report($e);
            $this->js('toast('.json_encode(__('app.error_generic')).', "error")');
        }

        return false;
    }

    protected function firebaseMessage(FirebaseException $e): string
    {
        foreach (array_keys(trans('admin.errors')) as $code) {
            if ($e->is($code)) {
                return __('admin.errors.'.$code);
            }
        }

        return __('app.error_generic').' ('.$e->errorCode.')';
    }
}
