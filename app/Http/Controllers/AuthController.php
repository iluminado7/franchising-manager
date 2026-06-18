<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)
                    ->where('activo', 1)
                    ->first();

        if (!$user || !Hash::check($request->password, $user->password_hash)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales no son correctas.'],
            ]);
        }

        // Cargar perfil según rol — ahora incluye super_admin
        $perfil = match($user->rol) {
            'super_admin'   => $user->superAdmin,
            'franquiciante' => $user->systemAdmin,
            'franquiciado'  => $user->franchiseStaff,
            'empleado'      => $user->franchiseStaff,
        };

        $token = $user->createToken(
            name:      'auth_token',
            abilities: $this->abilitiesPorRol($user->rol),
            expiresAt: now()->addHours(8),
        )->plainTextToken;

        ActivityLog::registrar(
            userId:    $user->id,
            accion:    'login',
            ip:        $request->ip(),
            empresaId: $user->empresa_id,
            userAgent: $request->userAgent()
        );

        return response()->json([
            'rol'    => $user->rol,
            'perfil' => $perfil,
        ])->cookie(
            'auth_token', $token, 60 * 8,
            '/', null, false, true, false, 'Strict'
        );
    }

    public function logout(Request $request): JsonResponse
    {
        ActivityLog::registrar(
            userId:    $request->user()->id,
            accion:    'logout',
            ip:        $request->ip(),
            empresaId: $request->user()->empresa_id,
            userAgent: $request->userAgent()
        );

        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada correctamente.'])
            ->withoutCookie('auth_token');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        $perfil = match($user->rol) {
            'super_admin'   => $user->superAdmin,
            'franquiciante' => $user->systemAdmin,
            'franquiciado'  => $user->franchiseStaff?->load('franquicia'),
            'empleado'      => $user->franchiseStaff?->load('franquicia'),
        };

        $notificacionesPendientes = $user->notifications()
                                         ->noLeidas()
                                         ->count();


        $empresa = null;
        if ($user->rol === 'franquiciante' && $user->empresa_id) {
            $empresa = \App\Models\Empresa::with('plan')->find($user->empresa_id);
        }
        return response()->json([
            'id'                        => $user->id,
            'email'                     => $user->email,
            'rol'                       => $user->rol,
            'empresa_id'                => $user->empresa_id,
            'empresa'                   => $empresa, 
            'perfil'                    => $perfil,
            'notificaciones_pendientes' => $notificacionesPendientes,
        ]);
    }
    public function updateEmail(Request $request)
    {
        $request->validate([
            'email'    => 'required|email|max:200|unique:users,email,' . $request->user()->id,
            'password' => 'required|string',
        ]);
    
        if (!Hash::check($request->password, $request->user()->password_hash)) {
            return response()->json(['message' => 'La contraseña actual es incorrecta.'], 422);
        }
    
        $request->user()->update(['email' => $request->email]);
    
        return response()->json(['message' => 'Email actualizado correctamente.']);
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password'      => 'required|string',
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string',
        ]);
    
        if (!Hash::check($request->current_password, $request->user()->password_hash)) {
            return response()->json(['message' => 'La contraseña actual es incorrecta.'], 422);
        }
    
        $request->user()->update([
            'password_hash' => Hash::make($request->password),
        ]);
    
        return response()->json(['message' => 'Contraseña actualizada correctamente.']);
    }

    private function abilitiesPorRol(string $rol): array
    {
        return match($rol) {
            'super_admin' => [
                'empresa:gestionar',
                'plan:gestionar',
                'manual:crear',
                'manual:editar',
                'manual:publicar',
                'manual:archivar',
                'manual:asignar_empresa',
                'franquicia:gestionar',
                'usuario:gestionar',
                'documento:subir',
                'log:ver',
                'invoice:ver',
            ],
            'franquiciante' => [
                'franquicia:gestionar',
                'usuario:gestionar',
                'manual:ver',
                'manual:asignar',
                'firma:subir',
                'documento:subir',
                'log:ver',
            ],
            'franquiciado' => [
                'manual:ver',
                'manual:aceptar',
                'manual:asignar',
                'documento:ver',
                'firma:subir',
            ],
            'empleado' => [
                'manual:ver',
            ],
        };
    }
}
