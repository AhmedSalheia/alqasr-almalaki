<?php

namespace App\Http\Controllers;

use Alqasr\GallerySaver\Gallery;
use App\Models\Event;
use App\Support\Native;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Receives the event card rendered to PNG in the web view and hands it to the phone:
 *   action=save  -> copied into the gallery (Pictures/Alqasr Almalaky)
 *   action=share -> native share sheet (WhatsApp, Drive, Files, ...)
 *
 * The PNG arrives either as a multipart file ("card") or, as a fallback, as base64 text
 * ("card_base64") — the page retries with base64 if the upload did not come through.
 * Every step is logged with the [card] prefix (see `php artisan native:tail`).
 */
class CardController extends Controller
{
    public function store(Request $request, Event $event): JsonResponse
    {
        $action = $request->input('action') === 'share' ? 'share' : 'save';
        $native = Native::isNative();

        Log::info('[card] request', [
            'event' => $event->id,
            'action' => $action,
            'native' => $native,
            'has_file' => $request->hasFile('card'),
            'base64_length' => strlen((string) $request->input('card_base64')),
            'content_type' => $request->header('Content-Type'),
        ]);

        try {
            $dir = storage_path('app/cards');
            File::ensureDirectoryExists($dir);
            foreach (collect(File::files($dir))->sortByDesc(fn ($f) => $f->getMTime())->slice(10) as $old) {
                File::delete($old->getPathname());
            }

            $fileName = 'alqasr-'.$event->date->format('Y-m-d').'-'
                .(Str::slug($event->groom_name) ?: Str::lower(Str::random(6))).'-'.now()->format('His').'.png';
            $path = $dir.DIRECTORY_SEPARATOR.$fileName;

            if (! $this->storePng($request, $path)) {
                Log::warning('[card] no PNG received');

                return response()->json(['ok' => false, 'stage' => 'upload', 'retry_base64' => true, 'message' => 'no image received'], 422);
            }

            Log::info('[card] stored', ['path' => $path, 'bytes' => filesize($path)]);

            if (! $native) {
                return response()->json(['ok' => true, 'mode' => 'browser', 'file' => $fileName]);
            }

            if ($action === 'save') {
                $result = Gallery::saveImage($path, $fileName);
                Log::info('[card] gallery result', $result);
                if ($result['success'] ?? false) {
                    return response()->json(['ok' => true, 'mode' => 'gallery', 'file' => $fileName]);
                }
                // fall through to the share sheet so the user can still save the card
            }

            $shared = $this->share($event, $path);
            Log::info('[card] share result', ['ok' => $shared]);

            return $shared
                ? response()->json(['ok' => true, 'mode' => 'share', 'file' => $fileName])
                : response()->json(['ok' => false, 'stage' => 'share', 'message' => 'share sheet failed'], 500);
        } catch (Throwable $e) {
            Log::error('[card] failed: '.$e->getMessage(), ['exception' => $e]);

            return response()->json(['ok' => false, 'stage' => 'server', 'message' => $e->getMessage()], 500);
        }
    }

    protected function storePng(Request $request, string $path): bool
    {
        $file = $request->file('card');
        if ($file && $file->isValid()) {
            File::put($path, File::get($file->getPathname()));
        } elseif ($b64 = (string) $request->input('card_base64')) {
            $b64 = preg_replace('#^data:image/\w+;base64,#', '', $b64);
            $bytes = base64_decode($b64, true);
            if ($bytes === false) {
                return false;
            }
            File::put($path, $bytes);
        } else {
            return false;
        }

        // PNG signature check instead of fileinfo-based validation.
        $head = (string) @file_get_contents($path, false, null, 0, 8);
        if ($head !== "\x89PNG\r\n\x1a\n" || filesize($path) > 20 * 1024 * 1024) {
            @unlink($path);

            return false;
        }

        return true;
    }

    protected function share(Event $event, string $path): bool
    {
        if (! function_exists('nativephp_call')) {
            return false;
        }

        $raw = nativephp_call('Share.File', json_encode([
            'title' => $event->groom_name,
            'message' => __('event.card_share_text', ['name' => $event->groom_name]),
            'filePath' => $path,
        ]));
        Log::info('[card] Share.File raw', ['raw' => $raw]);

        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        $data = is_array($decoded) ? ($decoded['data'] ?? $decoded) : [];

        return empty($data['error']) && (($decoded['status'] ?? 'success') !== 'error');
    }
}
