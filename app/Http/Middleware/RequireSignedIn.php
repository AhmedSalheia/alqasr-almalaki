<?php

namespace App\Http\Middleware;

use App\Services\AuthSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireSignedIn
{
    public function __construct(protected AuthSession $session) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->session->check()) {
            return $next($request);
        }

        return redirect()->route($this->session->pendingDevice() ? 'device.pending' : 'login');
    }
}
