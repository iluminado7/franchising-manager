<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    // ──────────────────────────────────────────────────────────────────
    // GET /api/notificaciones
    // Devuelve las notificaciones del usuario autenticado
    // Query params: ?solo_no_leidas=1
    // ──────────────────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $query = $request->user()
                         ->notifications()
                         ->with(['manual', 'manualVersion.manual', 'document'])
                         ->orderBy('created_at', 'desc');

        if ($request->boolean('solo_no_leidas')) {
            $query->noLeidas();
        }

        $notificaciones = $query->limit(50)->get();

        return response()->json([
            'notificaciones' => $notificaciones,
            'total_no_leidas' => $request->user()
                                         ->notifications()
                                         ->noLeidas()
                                         ->count(),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────
    // POST /api/notificaciones/{id}/leer
    // Marca una notificación como leída
    // ──────────────────────────────────────────────────────────────────
    public function marcarLeida(Request $request, int $id): JsonResponse
    {
        $notificacion = Notification::where('id', $id)
                                    ->where('user_id', $request->user()->id)
                                    ->firstOrFail();

        $notificacion->marcarComoLeida();

        return response()->json(['message' => 'Notificación marcada como leída.']);
    }

    // ──────────────────────────────────────────────────────────────────
    // POST /api/notificaciones/leer-todas
    // Marca todas las notificaciones del usuario como leídas
    // ──────────────────────────────────────────────────────────────────
    public function marcarTodasLeidas(Request $request): JsonResponse
    {
        $request->user()
                ->notifications()
                ->noLeidas()
                ->update([
                    'leida'    => 1,
                    'leida_at' => now(),
                ]);

        return response()->json(['message' => 'Todas las notificaciones marcadas como leídas.']);
    }
}
