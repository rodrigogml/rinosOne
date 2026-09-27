<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormalizePersistentAuthenticationSession
{
    /**
     * Remove legacy non-numeric persistent-authentication identifiers from the session.
     *
     * Identifiers stored before the system-wide BIGINT migration cannot reference the
     * current persistentAuthentication table and would make the database session write fail.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $persistentAuthenticationId = $request->session()->get('persistentAuthenticationId');

        if ($persistentAuthenticationId !== null && ! ctype_digit((string) $persistentAuthenticationId)) {
            $request->session()->forget('persistentAuthenticationId');
        }

        return $next($request);
    }
}
