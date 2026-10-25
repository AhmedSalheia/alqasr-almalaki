<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Locale aware formatting helpers (Levantine Arabic month names, currency, time).
 */
class Format
{
    public static function money(float|string|null $amount): string
    {
        $amount = (float) $amount;
        $decimals = fmod($amount, 1.0) == 0.0 ? 0 : 2;
        $number = number_format($amount, $decimals);
        $symbol = config('alqasr.currency.symbol');

        return app()->getLocale() === 'ar' ? $number.' '.$symbol : $symbol.' '.$number;
    }

    /**
     * Month name as written in lang/{locale}/calendar.php. A trailing " / 10" style month
     * number is kept only when $withNumber is true (the calendar page header).
     */
    public static function monthName(int $month, bool $withNumber = false): string
    {
        $name = trim((string) __('calendar.months.'.$month));

        return $withNumber ? $name : trim((string) preg_replace('~\s*/\s*[0-9\x{0660}-\x{0669}]+\s*$~u', '', $name));
    }

    public static function weekday(CarbonInterface $date, bool $short = false): string
    {
        return __('calendar.'.($short ? 'weekdays_short' : 'weekdays').'.'.$date->dayOfWeek);
    }

    /** e.g. "Saturday, 24 October 2026" / "السبت، 24 تشرين الأول 2026" */
    public static function longDate(CarbonInterface $date): string
    {
        $sep = app()->getLocale() === 'ar' ? '، ' : ', ';

        return static::weekday($date).$sep.$date->day.' '.static::monthName($date->month).' '.$date->year;
    }

    public static function shortDate(CarbonInterface $date): string
    {
        return $date->day.' '.static::monthName($date->month);
    }

    public static function monthYear(CarbonInterface $date): string
    {
        return static::monthName($date->month).' '.$date->year;
    }

    /** "20:30" -> "8:30 PM" / "8:30 م" */
    public static function time(?string $hhmm): string
    {
        if (! $hhmm || ! preg_match('/^(\d{1,2}):(\d{2})$/', $hhmm, $m)) {
            return '';
        }
        $h = (int) $m[1];
        $suffix = $h >= 12 ? __('calendar.pm') : __('calendar.am');
        $h12 = $h % 12 ?: 12;

        // Unicode isolates keep "7:00 م" readable inside right-to-left text.
        return "\u{2068}".$h12.':'.$m[2]."\u{2069} ".$suffix;
    }

    public static function timeRange(?string $start, ?string $end): string
    {
        return $end ? "\u{2068}".static::time($start)."\u{2069} – \u{2068}".static::time($end)."\u{2069}" : static::time($start);
    }

    public static function relative(?string $iso): string
    {
        if (! $iso) {
            return __('app.never');
        }

        return \Carbon\Carbon::parse($iso)->locale(app()->getLocale())->diffForHumans();
    }
}
