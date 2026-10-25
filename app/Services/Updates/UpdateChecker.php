<?php

namespace App\Services\Updates;

use App\Models\AppState;
use App\Services\Firebase\FirebaseClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Looks for a newer version in the repo's latest GitHub Release
 * (GET https://api.github.com/repos/{owner}/{repo}/releases/latest, no token needed for public repos).
 */
class UpdateChecker
{
    public const STATE = 'update.info';

    public function currentVersion(): string
    {
        return ltrim((string) config('nativephp.version', '0.0.0'), 'v');
    }

    public function repo(): ?string
    {
        $repo = trim((string) config('alqasr.updates.repo'));

        return preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repo) ? $repo : null;
    }

    public function due(): bool
    {
        $checkedAt = (int) AppState::get('update.checked_at', 0);

        return time() - $checkedAt >= max(1, (int) config('alqasr.updates.check_hours', 6)) * 3600;
    }

    /** Last known result (no network). */
    public function cached(): ?array
    {
        $info = AppState::get(self::STATE);

        // Forget it once this version is installed (or a newer one).
        if ($info && ! $this->isNewer($info['version'] ?? '0')) {
            AppState::forget(self::STATE);

            return null;
        }

        return $info;
    }

    public function isNewer(string $version): bool
    {
        $current = $this->currentVersion();
        if (! preg_match('/^\d+\.\d+\.\d+/', $current)) {
            $current = '0.0.0'; // e.g. "DEBUG"
        }

        return version_compare(ltrim($version, 'v'), $current, '>');
    }

    /**
     * @return array{status:string, info?:array}  status: available | up_to_date | offline | not_configured | error
     */
    public function check(): array
    {
        $repo = $this->repo();
        if (! $repo) {
            return ['status' => 'not_configured'];
        }
        if (FirebaseClient::recentlyOffline()) {
            return ['status' => 'offline'];
        }

        try {
            $res = Http::acceptJson()
                ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28', 'User-Agent' => 'alqasr-calendar-app'])
                ->connectTimeout(3)->timeout(8)
                ->get("https://api.github.com/repos/{$repo}/releases/latest");
        } catch (ConnectionException) {
            FirebaseClient::markOffline();

            return ['status' => 'offline'];
        } catch (Throwable $e) {
            report($e);

            return ['status' => 'error'];
        }

        AppState::put('update.checked_at', time());

        if ($res->status() === 404) {
            AppState::forget(self::STATE);

            return ['status' => 'up_to_date']; // no release published yet
        }
        if ($res->failed()) {
            return ['status' => 'error'];
        }

        $release = $res->json() ?? [];
        $version = ltrim((string) ($release['tag_name'] ?? ''), 'v');
        $apk = collect($release['assets'] ?? [])->first(function ($asset) use ($repo) {
            $url = (string) ($asset['browser_download_url'] ?? '');

            return str_ends_with(strtolower((string) ($asset['name'] ?? '')), '.apk')
                && str_starts_with($url, "https://github.com/{$repo}/releases/download/");
        });

        if (! $version || ! $this->isNewer($version) || ! $apk || ($release['draft'] ?? false) || ($release['prerelease'] ?? false)) {
            AppState::forget(self::STATE);

            return ['status' => 'up_to_date'];
        }

        $info = [
            'version' => $version,
            'apk_url' => $apk['browser_download_url'],
            'size' => (int) ($apk['size'] ?? 0),
            'notes' => mb_substr(trim((string) ($release['body'] ?? '')), 0, 2000),
            'page_url' => (string) ($release['html_url'] ?? "https://github.com/{$repo}/releases/latest"),
        ];
        AppState::put(self::STATE, $info);

        return ['status' => 'available', 'info' => $info];
    }
}
