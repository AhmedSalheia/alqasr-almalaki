<?php

namespace App\Http\Middleware;

use App\Support\Prefs;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ApplyPreferences
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $locale = Prefs::locale();
        } catch (Throwable) {
            $locale = config('app.locale', 'ar'); // database not migrated yet
        }

        App::setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}
