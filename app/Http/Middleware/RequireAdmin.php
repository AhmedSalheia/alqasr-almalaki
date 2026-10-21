<?php

namespace App\Http\Middleware;

use App\Services\AuthSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAdmin
{
    public function __construct(protected AuthSession $session) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->session->isAdmin(), 403);

        return $next($request);
    }
}
