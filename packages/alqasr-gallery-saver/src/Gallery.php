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
        if (! function_exists('nativephp_call') || (! getenv('NATIVEPHP_RUNNING') && getenv('JUMP_BRIDGE_PORT') === false)) {
            return ['success' => false, 'error' => 'not running on a device'];
        }

        $result = nativephp_call('Gallery.SaveImage', json_encode([
            'path' => $path,
            'name' => $name ?? basename($path),
            'album' => $album,
        ]));

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
