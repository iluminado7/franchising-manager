<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CookieToBearer
{
    public function handle(Request $request, Closure $next)
    {
        
        if ($request->is('api/login')) {
        return $next($request);
        }

        $token = $request->cookie('auth_token');

        if ($token && !$request->bearerToken()) {
            $request->headers->set('Authorization', 'Bearer ' . $token);
        }

        return $next($request);
    }
}