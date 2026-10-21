<?php

namespace App\Http\Controllers;

use Alqasr\GallerySaver\Gallery;
use App\Models\Event;
use App\Support\Native;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;
use Native\Mobile\Facades\File as NativeFile;

/**
 * Receives the event card rendered to PNG in the web view and hands it to the phone:
 *   action=save  -> copied into the gallery (Pictures/Alqasr Almalaky)
 *   action=share -> native share sheet (WhatsApp, Drive, Files, ...)
 */
class CardController extends Controller
{
    public function store(Request $request, Event $event): JsonResponse
    {

        $request->validate([
            'card' => ['required', 'file', 'mimetypes:image/png', 'max:15360'],
            'action' => ['nullable', 'in:save,share'],
        ]);

        $dir = storage_path('app/cards');
        File::ensureDirectoryExists($dir);

        // Keep only the latest few renders.
        foreach (collect(File::files($dir))->sortByDesc(fn ($f) => $f->getMTime())->slice(10) as $old) {
            File::delete($old->getPathname());
        }

        $fileName = 'alqasr-'.$event->date->format('Y-m-d').'-'.(Str::slug($event->groom_name) ?: Str::lower(Str::random(6))).'-'.now()->format('His').'.png';
        $path = $dir.DIRECTORY_SEPARATOR.$fileName;
        $request->file('card')->move($dir, $fileName);

        if (! Native::isNative()) {
            // Browser (development): the page downloads the PNG itself.
            return response()->json(['ok' => true, 'mode' => 'browser', 'file' => $fileName]);
        }

        $action = $request->input('action', 'save');

        if ($action === 'save' && Native::can('Gallery.SaveImage')) {
            try {
                $result = Gallery::saveImage($path, $fileName);
                // saveImage() always returns an array, so check its "success" flag.
                if ($result['success'] ?? false) {
                    return response()->json(['ok' => true, 'mode' => 'gallery', 'file' => $fileName]);
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        // Share sheet (also the fallback when saving to the gallery is not available).
        try {
            \Native\Mobile\Facades\Share::file($event->groom_name, __('event.card_share_text', ['name' => $event->groom_name]), $path);

            return response()->json(['ok' => true, 'mode' => 'share', 'file' => $fileName]);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'message' => __('event.card_failed')], 500);
        }
    }
}
