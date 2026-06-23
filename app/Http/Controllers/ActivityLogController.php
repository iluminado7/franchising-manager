<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ActivityLogController extends Controller
{
    // GET /api/activity-logs
    // Super admin: todos | Franquiciante: solo su empresa
    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = ActivityLog::with([
                                'user.systemAdmin',
                                'user.franchiseStaff',
                                'user.empresa',   // empresa actual del usuario que ejecutó la acción
                                'empresa',         // empresa registrada en el log (cuando el evento la incluye)
                            ])
                            ->orderBy('id', 'desc')
                            ->limit(2000);

        if ($user->esFranquiciante()) {
            // Franquiciante solo ve logs de su empresa (por empresa_id del log o del usuario)
            $query->where(function ($q) use ($user) {
                $q->where('empresa_id', $user->empresa_id)
                  ->orWhereHas('user', fn($u) => $u->where('empresa_id', $user->empresa_id));
            });
        } elseif ($user->esSuperAdmin() && $request->filled('empresa_id')) {
            // Super admin puede filtrar por empresa (por empresa del log o del usuario)
            $empresaId = (int) $request->empresa_id;
            $query->where(function ($q) use ($empresaId) {
                $q->where('empresa_id', $empresaId)
                  ->orWhereHas('user', fn($u) => $u->where('empresa_id', $empresaId));
            });
        }

        return response()->json($query->get());
    }
}