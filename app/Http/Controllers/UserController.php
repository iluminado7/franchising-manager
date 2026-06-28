<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\SuperAdmin;
use App\Models\SystemAdmin;
use App\Models\FranchiseStaff;
use App\Models\FranchiseCategory;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    // GET /api/usuarios
    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();

        // Super admin puede ver los eliminados con ?include_deleted=1 (para poder restaurarlos)
        $includeDeleted = $user->esSuperAdmin() && (bool) $request->query('include_deleted', false);

        $query = User::with(['superAdmin', 'systemAdmin', 'franchiseStaff.franquicia', 'deletedBy:id,rol', 'categorias'])
                     ->where('id', '!=', $user->id);

        // Por defecto, nadie ve los eliminados. Solo super_admin con flag explícito.
        if (!$includeDeleted) {
            $query->noEliminados();
        }

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

        // v2.3: filtro opcional por categoría — útil para listas tipo "todos los Distribuidores"
        if ($request->filled('category_id')) {
            $query->whereHas('categorias', fn($q) =>
                $q->where('franchise_categories.id', $request->category_id)
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

        // v2.3: 'cuit' removido — la columna no existe en system_admins.
        $data = $request->validate([
            'email'         => 'required|email|unique:users,email',
            'password'      => 'required|string|min:8',
            'rol'           => ['required', Rule::in($rolesPermitidos)],
            'nombre'        => 'required|string|max:100',
            'apellido'      => 'required|string|max:100',
            'dni'           => 'nullable|string|max:15',
            'celular'       => 'nullable|string|max:30',
            'empresa_id'    => 'sometimes|integer|exists:empresas,id',
            'franquicia_id' => Rule::requiredIf(
                // v2.3 UX: el rol 'franquiciado' representa un "Socio comercial" en la UI.
                // Puede no tener sucursal asignada (distribuidor, dropshipper, proveedor
                // de servicios, etc.). Solo el empleado requiere sucursal obligatoria.
                // Excepción: si el actor es franquiciado creando empleado, la franquicia
                // se fuerza desde el actor más abajo — no la mandamos por request.
                fn() => $request->rol === 'empleado'
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

        // v2.3: nombre/apellido/dni ahora viven en users (antes en cada perfil).
        $user = User::create([
            'empresa_id'    => $empresaId,
            'email'         => $data['email'],
            'password_hash' => Hash::make($data['password']),
            'rol'           => $data['rol'],
            'nombre'        => $data['nombre'],
            'apellido'      => $data['apellido'],
            'dni'           => $data['dni'] ?? null,
            'celular'       => $data['celular'] ?? null,
        ]);

        // v2.3: las tablas de perfil quedan como marcadores de rol.
        // FranchiseStaff conserva además el vínculo con la franquicia.
        match($data['rol']) {
            'super_admin'   => SuperAdmin::create(['user_id' => $user->id]),
            'franquiciante' => SystemAdmin::create(['user_id' => $user->id]),
            default         => FranchiseStaff::create([
                'user_id'       => $user->id,
                'franquicia_id' => $franquiciaIdNueva,
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
        $user  = User::findOrFail($id);
        $actor = $request->user();

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

        // v2.3: 'cuit' removido — la columna no existe en system_admins.
        $data = $request->validate([
            'nombre'        => 'sometimes|string|max:100',
            'apellido'      => 'sometimes|string|max:100',
            'email'         => "sometimes|email|unique:users,email,{$id}",
            'password'      => 'nullable|string|min:8',
            'dni'           => 'nullable|string|max:15',
            'celular'       => 'nullable|string|max:30',
            'franquicia_id' => 'nullable|integer|exists:franquicias,id',
        ]);

        // El franquiciado no puede mover al empleado a otra sucursal
        if ($actor->esFranquiciado()) {
            unset($data['franquicia_id']);
        }

        // v2.3: nombre/apellido/dni/email/celular/password se actualizan en users
        // (antes nombre/apellido/dni iban al perfil). Consolidado en un solo UPDATE.
        $updateUser = array_filter([
            'nombre'   => $data['nombre']   ?? null,
            'apellido' => $data['apellido'] ?? null,
            'dni'      => $data['dni']      ?? null,
            'celular'  => $data['celular']  ?? null,
            'email'    => $data['email']    ?? null,
        ], fn($v) => $v !== null);

        if (!empty($data['password'])) {
            $updateUser['password_hash'] = Hash::make($data['password']);
        }

        if (!empty($updateUser)) {
            $user->update($updateUser);
        }

        // franquicia_id sigue viviendo en franchise_staff
        if (array_key_exists('franquicia_id', $data) && $user->franchiseStaff) {
            $user->franchiseStaff->update(['franquicia_id' => $data['franquicia_id']]);
        }

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

        // v2.3: bug arreglado — antes registraba 'usuario_creado' al activar.
        ActivityLog::registrar(
            userId:      $actor->id,
            accion:      $nuevoEstado ? 'usuario_activado' : 'usuario_desactivado',
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

    // DELETE /api/usuarios/{id}
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user  = User::findOrFail($id);
        $actor = $request->user();

        // Nadie puede eliminarse a sí mismo
        if ($user->id === $actor->id) {
            return response()->json(['error' => 'No podés eliminarte a vos mismo.'], 403);
        }

        // Reglas por rol
        if ($actor->esFranquiciante()) {
            // Solo franquiciados y empleados de su empresa
            if (!in_array($user->rol, ['franquiciado', 'empleado'])) {
                return response()->json(['error' => 'Sin permisos para eliminar este usuario.'], 403);
            }
            if ($user->empresa_id !== $actor->empresa_id) {
                return response()->json(['error' => 'Sin acceso.'], 403);
            }
        } elseif (!$actor->esSuperAdmin()) {
            // Franquiciado/empleado no pueden eliminar
            return response()->json(['error' => 'Sin permisos.'], 403);
        }

        // Si ya está eliminado, no hacer nada
        if ($user->deleted_at !== null) {
            return response()->json(['error' => 'El usuario ya fue eliminado.'], 409);
        }

        $user->update([
            'deleted_by' => $actor->id,
            'deleted_at' => now(),
        ]);

        // Matar la sesión activa del usuario eliminado (si la tuviera)
        $user->tokens()->delete();

        ActivityLog::registrar(
            userId:      $actor->id,
            accion:      'usuario_eliminado',
            ip:          $request->ip(),
            empresaId:   $user->empresa_id,
            entidadTipo: 'users',
            entidadId:   $user->id,
            userAgent:   $request->userAgent()
        );

        return response()->json(['message' => 'Usuario eliminado correctamente.']);
    }

    // POST /api/usuarios/{id}/restore  (solo super_admin)
    public function restore(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();

        if (!$actor->esSuperAdmin()) {
            return response()->json(['error' => 'Sin permisos.'], 403);
        }

        $user = User::findOrFail($id);

        if ($user->deleted_at === null) {
            return response()->json(['error' => 'El usuario no está eliminado.'], 409);
        }

        $user->update([
            'deleted_by' => null,
            'deleted_at' => null,
        ]);

        ActivityLog::registrar(
            userId:      $actor->id,
            accion:      'usuario_restaurado',
            ip:          $request->ip(),
            empresaId:   $user->empresa_id,
            entidadTipo: 'users',
            entidadId:   $user->id,
            userAgent:   $request->userAgent()
        );

        return response()->json(['message' => 'Usuario restaurado correctamente.']);
    }

    // ── Categorías del usuario (v2.3) ───────────────────────────────────

    /**
     * GET /api/usuarios/{id}/categorias
     * Lista las categorías asignadas al usuario.
     */
    public function listarCategorias(Request $request, int $id): JsonResponse
    {
        $usuario = User::findOrFail($id);
        $actor   = $request->user();

        // Cada uno puede ver las suyas. Los gestores ven las de los gestionables.
        if ($usuario->id !== $actor->id
            && !$this->actorPuedeGestionarCategorias($actor, $usuario)) {
            return response()->json(['error' => 'Sin acceso.'], 403);
        }

        return response()->json($usuario->categorias);
    }

    /**
     * PUT /api/usuarios/{id}/categorias
     * Sincroniza la lista completa de categorías del usuario.
     * Agrega las que falten, quita las que sobren, y deja intactas las que ya estaban
     * (preservando assigned_at original).
     */
    public function sincronizarCategorias(Request $request, int $id): JsonResponse
    {
        $usuario = User::findOrFail($id);
        $actor   = $request->user();

        if (!$this->actorPuedeGestionarCategorias($actor, $usuario)) {
            return response()->json(['error' => 'Sin acceso.'], 403);
        }

        $data = $request->validate([
            'category_ids'   => 'present|array',
            'category_ids.*' => 'integer|exists:franchise_categories,id',
        ]);

        // Validar que todas las categorías sean de la misma empresa del usuario
        // y estén activas. Si alguna no cumple, rechazar TODO el sync.
        $categorias = FranchiseCategory::whereIn('id', $data['category_ids'])->get();

        foreach ($categorias as $cat) {
            if ($cat->empresa_id !== $usuario->empresa_id) {
                return response()->json([
                    'error' => "La categoría \"{$cat->name}\" no pertenece a la empresa del usuario.",
                ], 422);
            }
            if (!$cat->is_active) {
                return response()->json([
                    'error' => "La categoría \"{$cat->name}\" está desactivada. Reactivala antes de asignar.",
                ], 422);
            }
        }

        $actuales = $usuario->categorias()->pluck('franchise_categories.id')->toArray();
        $nuevas   = collect($data['category_ids'])->unique()->values();

        $aAgregar = $nuevas->diff($actuales);
        $aQuitar  = collect($actuales)->diff($nuevas);

        DB::transaction(function () use ($usuario, $actor, $aAgregar, $aQuitar, $categorias, $request) {
            // Attach nuevas
            foreach ($aAgregar as $catId) {
                $usuario->categorias()->attach($catId, [
                    'empresa_id'  => $usuario->empresa_id,
                    'assigned_by' => $actor->id,
                    'assigned_at' => now(),
                ]);

                $cat = $categorias->firstWhere('id', $catId);
                ActivityLog::registrar(
                    userId:      $actor->id,
                    accion:      'categoria_asignada_usuario',
                    ip:          $request->ip(),
                    empresaId:   $usuario->empresa_id,
                    entidadTipo: 'user_categories',
                    entidadId:   $usuario->id,
                    detalle:     [
                        'categoria_nombre' => $cat?->name ?? '(desconocida)',
                        'user_email'       => $usuario->email,
                    ],
                    userAgent:   $request->userAgent()
                );
            }

            // Detach las que sobran
            if ($aQuitar->isNotEmpty()) {
                $catsQuitadas = FranchiseCategory::whereIn('id', $aQuitar->toArray())->get();
                $usuario->categorias()->detach($aQuitar->toArray());

                foreach ($catsQuitadas as $cat) {
                    ActivityLog::registrar(
                        userId:      $actor->id,
                        accion:      'categoria_quitada_usuario',
                        ip:          $request->ip(),
                        empresaId:   $usuario->empresa_id,
                        entidadTipo: 'user_categories',
                        entidadId:   $usuario->id,
                        detalle:     [
                            'categoria_nombre' => $cat->name,
                            'user_email'       => $usuario->email,
                        ],
                        userAgent:   $request->userAgent()
                    );
                }
            }
        });

        return response()->json([
            'message'    => 'Categorías actualizadas correctamente.',
            'categorias' => $usuario->fresh()->categorias,
        ]);
    }

    /**
     * POST /api/usuarios/{id}/categorias
     * Agrega UNA categoría al usuario sin tocar las otras.
     * Body: { "category_id": N }
     */
    public function agregarCategoria(Request $request, int $id): JsonResponse
    {
        $usuario = User::findOrFail($id);
        $actor   = $request->user();

        if (!$this->actorPuedeGestionarCategorias($actor, $usuario)) {
            return response()->json(['error' => 'Sin acceso.'], 403);
        }

        $data = $request->validate([
            'category_id' => 'required|integer|exists:franchise_categories,id',
        ]);

        $categoria = FranchiseCategory::findOrFail($data['category_id']);

        if ($categoria->empresa_id !== $usuario->empresa_id) {
            return response()->json([
                'error' => 'La categoría no pertenece a la empresa del usuario.',
            ], 422);
        }

        if (!$categoria->is_active) {
            return response()->json([
                'error' => 'La categoría está desactivada. Reactivala antes de asignar.',
            ], 422);
        }

        $yaAsignada = DB::table('user_categories')
            ->where('user_id', $usuario->id)
            ->where('category_id', $categoria->id)
            ->exists();

        if ($yaAsignada) {
            return response()->json(['message' => 'El usuario ya tiene esta categoría.'], 409);
        }

        $usuario->categorias()->attach($categoria->id, [
            'empresa_id'  => $usuario->empresa_id,
            'assigned_by' => $actor->id,
            'assigned_at' => now(),
        ]);

        ActivityLog::registrar(
            userId:      $actor->id,
            accion:      'categoria_asignada_usuario',
            ip:          $request->ip(),
            empresaId:   $usuario->empresa_id,
            entidadTipo: 'user_categories',
            entidadId:   $usuario->id,
            detalle:     [
                'categoria_nombre' => $categoria->name,
                'user_email'       => $usuario->email,
            ],
            userAgent:   $request->userAgent()
        );

        return response()->json([
            'message'   => 'Categoría asignada correctamente.',
            'categoria' => $categoria,
        ], 201);
    }

    /**
     * DELETE /api/usuarios/{id}/categorias/{categoryId}
     * Quita UNA categoría del usuario.
     */
    public function quitarCategoria(Request $request, int $id, int $categoryId): JsonResponse
    {
        $usuario = User::findOrFail($id);
        $actor   = $request->user();

        if (!$this->actorPuedeGestionarCategorias($actor, $usuario)) {
            return response()->json(['error' => 'Sin acceso.'], 403);
        }

        $asignada = DB::table('user_categories')
            ->where('user_id', $usuario->id)
            ->where('category_id', $categoryId)
            ->exists();

        if (!$asignada) {
            return response()->json([
                'error' => 'El usuario no tiene esta categoría asignada.'
            ], 404);
        }

        $categoria = FranchiseCategory::find($categoryId);
        $usuario->categorias()->detach($categoryId);

        ActivityLog::registrar(
            userId:      $actor->id,
            accion:      'categoria_quitada_usuario',
            ip:          $request->ip(),
            empresaId:   $usuario->empresa_id,
            entidadTipo: 'user_categories',
            entidadId:   $usuario->id,
            detalle:     [
                'categoria_nombre' => $categoria?->name ?? '(desconocida)',
                'user_email'       => $usuario->email,
            ],
            userAgent:   $request->userAgent()
        );

        return response()->json(['message' => 'Categoría quitada correctamente.']);
    }

    // ── PRIVADOS ─────────────────────────────────────────────────────

    /**
     * Si el actor tiene permiso para gestionar las categorías del $usuario objetivo.
     *
     * Reglas:
     *  - El usuario objetivo debe ser franquiciado o empleado.
     *  - super_admin: cualquier usuario.
     *  - franquiciante: usuarios de su empresa.
     *  - franquiciado: solo empleados de SU MISMA franquicia/sucursal.
     *  - empleado: sin permisos.
     */
    private function actorPuedeGestionarCategorias(User $actor, User $usuario): bool
    {
        // Solo categorías para franquiciados/empleados
        if (!in_array($usuario->rol, ['franquiciado', 'empleado'])) {
            return false;
        }

        if ($actor->esSuperAdmin()) {
            return true;
        }

        if ($actor->esFranquiciante()) {
            return $usuario->empresa_id === $actor->empresa_id;
        }

        if ($actor->esFranquiciado()) {
            if ($usuario->rol !== 'empleado') {
                return false;
            }
            if ($usuario->empresa_id !== $actor->empresa_id) {
                return false;
            }
            $actorFranquiciaId   = $actor->franchiseStaff?->franquicia_id;
            $usuarioFranquiciaId = $usuario->franchiseStaff?->franquicia_id;
            if (!$actorFranquiciaId || !$usuarioFranquiciaId) {
                return false;
            }
            return $actorFranquiciaId === $usuarioFranquiciaId;
        }

        return false;
    }
}