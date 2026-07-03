<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'users';

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    // H-015 fix (mass assignment): campos privilegiados removidos del $fillable.
    // Los siguientes campos NO pueden ser seteados vía create()/update()/fill()
    // con datos del request. Los controllers deben usar setter directo
    // (->rol = ..., ->save()) para modificarlos, después de validaciones
    // explícitas por rol del actor:
    //   - rol            → auto-promoción de rol
    //   - empresa_id     → cambio de tenant
    //   - activo         → reactivar cuenta suspendida
    //   - password_hash  → cambio de contraseña sin verificación
    //   - deleted_by     → soft-delete de otros usuarios
    //   - deleted_at     → auto-eliminación o eliminación de otros
    //
    // Defensa en profundidad: aunque los controllers actuales ya validan cada
    // campo con $request->validate(), esta protección a nivel modelo evita que
    // un refactor futuro introduzca por accidente un patrón vulnerable como
    // User::create($request->all()) o ->fill($request->all()).
    protected $fillable = [
        'email',
        'nombre',
        'apellido',
        'dni',
        'celular',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected $casts = [
        'activo'     => 'boolean',
        'deleted_at' => 'datetime',
    ];

    // ── Relaciones de empresa ────────────────────────────────────────

    // NULL para super_admin, obligatorio para los demás roles
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    // Usuario que lo eliminó (super_admin o franquiciante)
    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    // ── Scopes ───────────────────────────────────────────────────────

    // Excluir usuarios eliminados (deleted_at NOT NULL)
    public function scopeNoEliminados($query)
    {
        return $query->whereNull('deleted_at');
    }

    // ── Relaciones de perfil (1 a 1) ────────────────────────────────
    // Las tablas de perfil quedan como marcadores de rol después de v2.3.
    // franchise_staff conserva además franquicia_id.

    // Solo existe si rol = 'super_admin'
    public function superAdmin(): HasOne
    {
        return $this->hasOne(SuperAdmin::class, 'user_id');
    }

    // Solo existe si rol = 'franquiciante'
    public function systemAdmin(): HasOne
    {
        return $this->hasOne(SystemAdmin::class, 'user_id');
    }

    // Solo existe si rol = 'franquiciado' o 'empleado'
    public function franchiseStaff(): HasOne
    {
        return $this->hasOne(FranchiseStaff::class, 'user_id');
    }

    // ── Categorías (v2.3) ────────────────────────────────────────────

    // Categorías a las que el usuario pertenece (vía user_categories).
    // Aplica principalmente a franquiciado y empleado.
    public function categorias(): BelongsToMany
    {
        return $this->belongsToMany(
            FranchiseCategory::class,
            'user_categories',
            'user_id',
            'category_id'
        )->withPivot('empresa_id', 'assigned_by', 'assigned_at');
    }

    // Fila pivote directa cuando se necesita assigned_by, fecha, etc.
    public function userCategories(): HasMany
    {
        return $this->hasMany(UserCategory::class, 'user_id');
    }

    // ── Acciones del usuario ─────────────────────────────────────────

    public function manualesCreados(): HasMany
    {
        return $this->hasMany(Manual::class, 'created_by');
    }

    public function versionesPublicadas(): HasMany
    {
        return $this->hasMany(ManualVersion::class, 'publicado_por');
    }

    public function acceptances(): HasMany
    {
        return $this->hasMany(Acceptance::class, 'user_id');
    }

    // ── Asignaciones de manuales (v2.3) ──────────────────────────────

    // Manuales asignados al usuario individualmente.
    // Antes apuntaba a ManualAssignment — ahora a ManualUserAssignment.
    public function asignaciones(): HasMany
    {
        return $this->hasMany(ManualUserAssignment::class, 'user_id');
    }

    // Asignaciones individuales realizadas POR este usuario (auditoría).
    public function asignacionesRealizadas(): HasMany
    {
        return $this->hasMany(ManualUserAssignment::class, 'assigned_by');
    }

    // Lista directa de manuales asignados individualmente (sin pasar por el pivote)
    public function manualesAsignados(): BelongsToMany
    {
        return $this->belongsToMany(
            Manual::class,
            'manual_user_assignments',
            'user_id',
            'manual_id'
        )->withPivot('empresa_id', 'assigned_by', 'assigned_at');
    }

    public function asignacionesEmpresaRealizadas(): HasMany
    {
        return $this->hasMany(ManualEmpresaAssignment::class, 'asignado_por');
    }

    // ── Asignaciones de documentos (v2.3) ────────────────────────────

    public function documentAssignments(): HasMany
    {
        return $this->hasMany(DocumentUserAssignment::class, 'user_id');
    }

    public function documentosAsignados(): BelongsToMany
    {
        return $this->belongsToMany(
            Document::class,
            'document_user_assignments',
            'user_id',
            'document_id'
        )->withPivot('empresa_id', 'assigned_by', 'assigned_at');
    }

    // ── Notificaciones y logs ────────────────────────────────────────

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'user_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'user_id');
    }

    public function documentosSubidos(): HasMany
    {
        return $this->hasMany(Document::class, 'subido_por');
    }

    public function firmasFisicasSubidas(): HasMany
    {
        return $this->hasMany(PhysicalSignature::class, 'subido_por');
    }

    // ── Helpers de rol ───────────────────────────────────────────────

    public function esSuperAdmin(): bool
    {
        return $this->rol === 'super_admin';
    }

    public function esFranquiciante(): bool
    {
        return $this->rol === 'franquiciante';
    }

    public function esFranquiciado(): bool
    {
        return $this->rol === 'franquiciado';
    }

    public function esEmpleado(): bool
    {
        return $this->rol === 'empleado';
    }

    // ── Helpers de nombre ────────────────────────────────────────────

    // Nombre completo del usuario. Reemplaza el método que vivía en cada
    // modelo de perfil (super_admins/system_admins/franchise_staff) tras
    // la migración v2.3 que centralizó nombre y apellido en users.
    public function nombreCompleto(): string
    {
        return trim("{$this->nombre} {$this->apellido}");
    }
}