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
        $query = ActivityLog::with(['user.systemAdmin', 'user.franchiseStaff', 'empresa'])
                            ->orderBy('id', 'desc')
                            ->limit(2000);

        if ($user->esFranquiciante()) {
            // Franquiciante solo ve logs de su empresa
            $query->where('empresa_id', $user->empresa_id);
        } elseif ($user->esSuperAdmin() && $request->filled('empresa_id')) {
            // Super admin puede filtrar por empresa
            $query->where('empresa_id', $request->empresa_id);
        }

        return response()->json($query->get());
    }
}
