<?php

namespace App\Http\Middleware;

use App\Services\AuthSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfSignedIn
{
    public function __construct(protected AuthSession $session) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->session->check()) {
            return redirect()->route('calendar');
        }

        return $next($request);
    }
}
