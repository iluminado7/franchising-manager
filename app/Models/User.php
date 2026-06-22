<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'users';

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    protected $fillable = [
        'empresa_id',
        'email',
        'password_hash',
        'rol',
        'celular',
        'activo',
        'deleted_by',
        'deleted_at',
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

    public function asignaciones(): HasMany
    {
        return $this->hasMany(ManualAssignment::class, 'user_id');
    }

    public function asignacionesRealizadas(): HasMany
    {
        return $this->hasMany(ManualAssignment::class, 'assigned_by');
    }

    public function asignacionesEmpresaRealizadas(): HasMany
    {
        return $this->hasMany(ManualEmpresaAssignment::class, 'asignado_por');
    }

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
}