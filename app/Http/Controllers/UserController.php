<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\SuperAdmin;
use App\Models\SystemAdmin;
use App\Models\FranchiseStaff;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    // GET /api/usuarios
    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = User::with(['superAdmin', 'systemAdmin', 'franchiseStaff.franquicia'])
                     ->where('id', '!=', $user->id);

        // Franquiciante solo ve usuarios de su empresa
        if ($user->esFranquiciante()) {
            $query->where('empresa_id', $user->empresa_id)
                  ->whereIn('rol', ['franquiciado', 'empleado']);
        }

        // Franquiciado solo ve empleados de su propia sucursal
        if ($user->esFranquiciado()) {
            $franquiciaId = $user->franchiseStaff?->franquicia_id;
            $query->where('empresa_id', $user->empresa_id)
                  ->where('rol', 'empleado')
                  ->whereHas('franchiseStaff', fn($q) =>
                      $q->where('franquicia_id', $franquiciaId)
                  );
        }

        // Super admin puede filtrar por empresa
        if ($user->esSuperAdmin() && $request->filled('empresa_id')) {
            $query->where('empresa_id', $request->empresa_id);
        }

        if ($request->filled('rol')) {
            $query->where('rol', $request->rol);
        }

        if ($request->filled('franquicia_id')) {
            $query->whereHas('franchiseStaff', fn($q) =>
                $q->where('franquicia_id', $request->franquicia_id)
            );
        }

        return response()->json($query->orderBy('created_at', 'desc')->get());
    }

    // POST /api/usuarios
    public function store(Request $request): JsonResponse
    {
        $actor = $request->user();

        // Roles permitidos según quien crea
        $rolesPermitidos = match(true) {
            $actor->esSuperAdmin()    => ['super_admin', 'franquiciante', 'franquiciado', 'empleado'],
            $actor->esFranquiciante() => ['franquiciado', 'empleado'],
            $actor->esFranquiciado()  => ['empleado'],
            default                   => [],
        };

        $data = $request->validate([
            'email'         => 'required|email|unique:users,email',
            'password'      => 'required|string|min:8',
            'rol'           => ['required', Rule::in($rolesPermitidos)],
            'nombre'        => 'required|string|max:100',
            'apellido'      => 'required|string|max:100',
            'dni'           => 'nullable|string|max:15',
            'celular'       => 'nullable|string|max:30',
            'cuit'          => 'nullable|string|max:15',
            'empresa_id'    => 'sometimes|integer|exists:empresas,id',
            'franquicia_id' => Rule::requiredIf(
                fn() => in_array($request->rol, ['franquiciado', 'empleado'])
                        && !$actor->esFranquiciado()
            ),
        ]);

        // Determinar empresa_id
        $empresaId = match(true) {
            $actor->esSuperAdmin() && $data['rol'] !== 'super_admin'
                => $data['empresa_id'] ?? null,
            $actor->esFranquiciante()
                => $actor->empresa_id,
            $actor->esFranquiciado()
                => $actor->empresa_id,
            default => null, // super_admin sin empresa
        };

        // El franquiciado solo crea empleados en SU propia sucursal: la franquicia
        // se fuerza desde el actor, nunca desde el request (aislamiento multi-tenant).
        $franquiciaIdNueva = $actor->esFranquiciado()
            ? $actor->franchiseStaff?->franquicia_id
            : ($data['franquicia_id'] ?? null);

        $user = User::create([
            'empresa_id'    => $empresaId,
            'email'         => $data['email'],
            'password_hash' => Hash::make($data['password']),
            'rol'           => $data['rol'],
            'celular'       => $data['celular'] ?? null,
        ]);

        // Crear perfil según rol
        match($data['rol']) {
            'super_admin' => SuperAdmin::create([
                'user_id'  => $user->id,
                'nombre'   => $data['nombre'],
                'apellido' => $data['apellido'],
                'dni'      => $data['dni'] ?? null,
            ]),
            'franquiciante' => SystemAdmin::create([
                'user_id'  => $user->id,
                'nombre'   => $data['nombre'],
                'apellido' => $data['apellido'],
                'dni'      => $data['dni'] ?? null,
            ]),
            default => FranchiseStaff::create([
                'user_id'       => $user->id,
                'franquicia_id' => $franquiciaIdNueva,
                'nombre'        => $data['nombre'],
                'apellido'      => $data['apellido'],
                'dni'           => $data['dni'] ?? null,
            ]),
        };

        ActivityLog::registrar(
            userId:      $actor->id,
            accion:      'usuario_creado',
            ip:          $request->ip(),
            empresaId:   $empresaId,
            entidadTipo: 'users',
            entidadId:   $user->id,
            detalle:     ['campo' => 'rol', 'valor_nuevo' => $data['rol']],
            userAgent:   $request->userAgent()
        );

        return response()->json(
            $user->load(['superAdmin', 'systemAdmin', 'franchiseStaff.franquicia']),
            201
        );
    }

    // PUT /api/usuarios/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $user   = User::findOrFail($id);
        $actor  = $request->user();
        $perfil = $user->superAdmin ?? $user->systemAdmin ?? $user->franchiseStaff;

        // Franquiciante solo puede editar usuarios de su empresa
        if ($actor->esFranquiciante() && $user->empresa_id !== $actor->empresa_id) {
            return response()->json(['error' => 'Sin acceso.'], 403);
        }

        // Franquiciado solo puede editar empleados de su propia sucursal
        if ($actor->esFranquiciado()) {
            $mismaSucursal = $user->rol === 'empleado'
                && $user->empresa_id === $actor->empresa_id
                && $user->franchiseStaff?->franquicia_id === $actor->franchiseStaff?->franquicia_id;
            if (!$mismaSucursal) {
                return response()->json(['error' => 'Sin acceso.'], 403);
            }
        }

        $data = $request->validate([
            'nombre'        => 'sometimes|string|max:100',
            'apellido'      => 'sometimes|string|max:100',
            'email'         => "sometimes|email|unique:users,email,{$id}",
            'password'      => 'nullable|string|min:8',
            'dni'           => 'nullable|string|max:15',
            'celular'       => 'nullable|string|max:30',
            'cuit'          => 'nullable|string|max:15',
            'franquicia_id' => 'nullable|integer|exists:franquicias,id',
        ]);

        // El franquiciado no puede mover al empleado a otra sucursal
        if ($actor->esFranquiciado()) {
            unset($data['franquicia_id']);
        }

        if (!empty($data['password'])) {
            $user->update(['password_hash' => Hash::make($data['password'])]);
        }
        if (!empty($data['email'])) {
            $user->update(['email' => $data['email']]);
        }
        if (isset($data['celular'])) {
            $user->update(['celular' => $data['celular']]);
        }

        $perfil?->update(array_filter([
            'nombre'        => $data['nombre']        ?? null,
            'apellido'      => $data['apellido']       ?? null,
            'dni'           => $data['dni']            ?? null,
            'franquicia_id' => $data['franquicia_id']  ?? null,
        ], fn($v) => $v !== null));

        return response()->json(
            $user->fresh(['superAdmin', 'systemAdmin', 'franchiseStaff.franquicia'])
        );
    }

    // POST /api/usuarios/{id}/toggle-activo
    public function toggleActivo(Request $request, int $id): JsonResponse
    {
        $user  = User::findOrFail($id);
        $actor = $request->user();

        // Franquiciante no puede tocar a franquiciantes ni super_admins
        if ($actor->esFranquiciante()) {
            if (in_array($user->rol, ['franquiciante', 'super_admin'])) {
                return response()->json(['error' => 'Sin permisos.'], 403);
            }
            if ($user->empresa_id !== $actor->empresa_id) {
                return response()->json(['error' => 'Sin acceso.'], 403);
            }
        }

        // Franquiciado solo puede activar/desactivar empleados de su propia sucursal
        if ($actor->esFranquiciado()) {
            $mismaSucursal = $user->rol === 'empleado'
                && $user->empresa_id === $actor->empresa_id
                && $user->franchiseStaff?->franquicia_id === $actor->franchiseStaff?->franquicia_id;
            if (!$mismaSucursal) {
                return response()->json(['error' => 'Sin permisos.'], 403);
            }
        }

        $nuevoEstado = !$user->activo;
        $user->update(['activo' => $nuevoEstado]);

        if (!$nuevoEstado) {
            $user->tokens()->delete();
        }

        ActivityLog::registrar(
            userId:      $actor->id,
            accion:      $nuevoEstado ? 'usuario_creado' : 'usuario_desactivado',
            ip:          $request->ip(),
            empresaId:   $user->empresa_id,
            entidadTipo: 'users',
            entidadId:   $user->id,
            userAgent:   $request->userAgent()
        );

        return response()->json([
            'message' => $nuevoEstado ? 'Usuario activado.' : 'Usuario desactivado.',
            'activo'  => $nuevoEstado,
        ]);
    }
}