<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        if (!in_array($request->user()?->rol, $roles)) {
            return response()->json(['error' => 'No tenés permiso para esta acción.'], 403);
        }

        return $next($request);
    }
}