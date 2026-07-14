<?php
use Illuminate\Http\Request;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web:      __DIR__.'/../routes/web.php',
        api:      __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health:   '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
        ]);
        $middleware->prepend(\App\Http\Middleware\CookieToBearer::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, Request $request) {
            return response()->json(['message' => 'No autenticado.'], 401);
        });

        // V2-H-014: sin esto, $this->authorize() devolveria HTML y romperia apiFetch.
        // Se conserva la forma {'error': '...'} que ya esperaba el frontend en los
        // 403 emitidos a mano por los controllers.
        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, Request $request) {
            $msg = $e->getMessage();
            if ($msg === '' || $msg === 'This action is unauthorized.') {
                $msg = 'Sin permisos.';
            }
            return response()->json(['error' => $msg], 403);
        });
    })->create();
