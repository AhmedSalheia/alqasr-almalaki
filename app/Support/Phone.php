<?php

namespace App\Support;

/**
 * Phone numbers with a country code (+972 / +970).
 * Stored as "+972592758021"; numbers saved before country codes existed ("0592758021") still work.
 */
class Phone
{
    public const CODES = ['+972', '+970'];

    public const DEFAULT_CODE = '+972';

    /** Arabic-Indic digits -> ASCII (everything else kept). */
    public static function normalize(?string $value): string
    {
        return trim(strtr((string) $value, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]));
    }

    /** Digits only (Arabic-Indic digits converted). */
    public static function digits(?string $value): string
    {
        return (string) preg_replace('/\D+/', '', static::normalize($value));
    }

    /** "+970" + "0599 123 456" -> "+970599123456" */
    public static function join(string $code, ?string $local): ?string
    {
        $digits = ltrim(static::digits($local), '0');
        if ($digits === '') {
            return null;
        }
        $code = in_array($code, static::CODES, true) ? $code : static::DEFAULT_CODE;

        return $code.$digits;
    }

    /**
     * "+970599123456" -> ['+970', '0599123456']; old "0599123456" -> ['+972', '0599123456'].
     *
     * @return array{0:string, 1:string}
     */
    public static function split(?string $phone): array
    {
        $raw = trim((string) $phone);
        $digits = static::digits($raw);
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
            $raw = '+'.$digits;
        }
        foreach (static::CODES as $code) {
            $c = ltrim($code, '+');
            if (str_starts_with($raw, '+') && str_starts_with($digits, $c)) {
                return [$code, '0'.substr($digits, strlen($c))];
            }
        }

        return [static::DEFAULT_CODE, $digits];
    }

    /** The national part without the leading 0 — used to compare two numbers. */
    public static function national(?string $phone): string
    {
        return ltrim(static::split($phone)[1], '0');
    }

    /** "+972592758021" -> "+972 59 275 8021"; numbers without a code are shown as typed. */
    public static function display(?string $phone): string
    {
        $phone = trim((string) $phone);
        if (! str_starts_with($phone, '+')) {
            return $phone;
        }
        [$code, $local] = static::split($phone);
        $n = ltrim($local, '0');
        if (strlen($n) === 9) {
            return $code.' '.substr($n, 0, 2).' '.substr($n, 2, 3).' '.substr($n, 5);
        }

        return $code.' '.$n;
    }
}
