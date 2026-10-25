<?php

namespace Alqasr\GallerySaver;

class Gallery
{
    /**
     * Copy an image file into the phone's gallery (Pictures/<album>).
     *
     * @return array{success:bool, uri?:string, error?:string}
     */
    public static function saveImage(string $path, ?string $name = null, string $album = 'Alqasr Almalaky'): array
    {
        if (! function_exists('nativephp_call')) {
            return ['success' => false, 'error' => 'not running on a device'];
        }
        // On the phone nativephp_call() is NativePHP's C extension (an internal function);
        // on a dev machine it is a PHP polyfill that only works during `native:jump`.
        $onDevice = (new \ReflectionFunction('nativephp_call'))->isInternal();
        if (! $onDevice && getenv('JUMP_BRIDGE_PORT') === false) {
            return ['success' => false, 'error' => 'not running on a device'];
        }

        $result = nativephp_call('Gallery.SaveImage', json_encode([
            'path' => $path,
            'name' => $name ?? basename($path),
            'album' => $album,
        ]));

        \Illuminate\Support\Facades\Log::info('[card] Gallery.SaveImage raw', ['raw' => $result]);
        $decoded = is_string($result) ? json_decode($result, true) : null;
        if (! is_array($decoded)) {
            return ['success' => false, 'error' => 'no response'];
        }

        // Bridge responses may be wrapped in a "data" envelope.
        $data = $decoded['data'] ?? $decoded;

        return [
            'success' => (bool) ($data['success'] ?? false),
            'uri' => $data['uri'] ?? null,
            'error' => $data['error'] ?? ($decoded['message'] ?? null),
        ];
    }
}
