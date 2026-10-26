<?php

namespace App\Support;

/**
 * Everything the event card needs to be drawn as a PNG, inlined as data: URLs so the
 * web view never has to fetch a file while drawing (that is what used to fail on phones
 * and fall back to system fonts with no logo).
 *
 * Files live in resources/card (bundled with the app, independent of the Vite build).
 */
class CardAssets
{
    protected static ?string $css = null;

    /** @var array<string, string> */
    protected static array $logos = [];

    public static function dir(): string
    {
        return resource_path('card');
    }

    /** @font-face rules for the card's fonts with every file embedded (~185 KB). */
    public static function fontCss(): string
    {
        if (static::$css !== null) {
            return static::$css;
        }

        $css = (string) @file_get_contents(static::dir().'/fonts.css');

        return static::$css = (string) preg_replace_callback('~url\(([a-z0-9-]+\.woff2)\)~', function (array $m) {
            $path = static::dir().'/fonts/'.$m[1];

            return is_file($path)
                ? 'url(data:font/woff2;base64,'.base64_encode((string) file_get_contents($path)).')'
                : 'url('.$m[1].')';
        }, $css);
    }

    public static function logo(bool $dark = false): string
    {
        $file = $dark ? 'logo-dark.png' : 'logo.png';

        return static::$logos[$file] ??= 'data:image/png;base64,'.base64_encode((string) @file_get_contents(static::dir().'/'.$file));
    }
}
