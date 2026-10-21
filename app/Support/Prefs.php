<?php

namespace App\Support;

use App\Models\AppState;

/**
 * Per-device user preferences (language + theme).
 */
class Prefs
{
    public static function locale(): string
    {
        $locale = AppState::get('pref.locale', config('app.locale', 'ar'));

        return in_array($locale, config('alqasr.locales'), true) ? $locale : 'ar';
    }

    public static function setLocale(string $locale): void
    {
        if (in_array($locale, config('alqasr.locales'), true)) {
            AppState::put('pref.locale', $locale);
        }
    }

    /** 'system' | 'light' | 'dark' */
    public static function theme(): string
    {
        $theme = AppState::get('pref.theme', 'system');

        return in_array($theme, ['system', 'light', 'dark'], true) ? $theme : 'system';
    }

    public static function setTheme(string $theme): void
    {
        if (in_array($theme, ['system', 'light', 'dark'], true)) {
            AppState::put('pref.theme', $theme);
        }
    }

    public static function dir(): string
    {
        return static::locale() === 'ar' ? 'rtl' : 'ltr';
    }

    public static function calendarView(): string
    {
        return AppState::get('pref.calendar_view', 'month') === 'day' ? 'day' : 'month';
    }

    public static function setCalendarView(string $view): void
    {
        AppState::put('pref.calendar_view', $view === 'day' ? 'day' : 'month');
    }
}
