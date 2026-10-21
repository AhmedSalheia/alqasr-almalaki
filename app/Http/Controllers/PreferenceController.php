<?php

namespace App\Http\Controllers;

use App\Support\Prefs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PreferenceController extends Controller
{
    public function locale(Request $request, string $locale): RedirectResponse
    {
        Prefs::setLocale($locale);

        return $this->back($request);
    }

    public function theme(Request $request, string $theme): RedirectResponse
    {
        Prefs::setTheme($theme);

        return $this->back($request);
    }

    protected function back(Request $request): RedirectResponse
    {
        // The native web view does not always send a Referer, so the page passes ?back=/path
        $back = (string) $request->query('back', '');
        if ($back !== '' && str_starts_with($back, '/') && ! str_starts_with($back, '//')) {
            return redirect($back);
        }

        return redirect()->back(fallback: route('calendar'));
    }
}
