<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveTenant
{
    /**
     * Bloquea en la API a los usuarios cuya empresa o franquicia este suspendida (activa = 0).
     * super_admin nunca se bloquea. Corre despues de auth:sanctum, con el usuario ya resuelto.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && !$user->esSuperAdmin()) {
            $empresaSuspendida = $user->empresa && !$user->empresa->activa;

            $franquicia           = optional($user->franchiseStaff)->franquicia;
            $franquiciaSuspendida = $franquicia && !$franquicia->activa;

            if ($empresaSuspendida || $franquiciaSuspendida) {
                abort(403, 'Tu empresa o sucursal fue suspendida.');
            }
        }

        return $next($request);
    }
}