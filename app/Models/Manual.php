<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Manual extends Model
{
    protected $table = 'manuals';

    public $timestamps = false;
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $fillable = [
        'titulo',
        'categoria',
        'created_by',
        'estado',
        'orden',
        'deleted_by',
        'deleted_at',
    ];

    // ── Relaciones ───────────────────────────────────────────────────

    // Super Admin que creó el manual
    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Usuario que eliminó el manual (puede ser super_admin o franquiciante)
    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function versiones(): HasMany
    {
        return $this->hasMany(ManualVersion::class, 'manual_id');
    }

    public function versionActiva(): HasMany
    {
        return $this->hasMany(ManualVersion::class, 'manual_id')
                    ->where('es_activa', 1);
    }

    // Versiones con nota del publicador — usadas para armar el hilo de notas
    // (se mergean con ManualNote en ManualNoteController::porManual).
    public function versionesConNota(): HasMany
    {
        return $this->hasMany(ManualVersion::class, 'manual_id')
                    ->whereNotNull('nota_publicacion')
                    ->where('nota_publicacion', '!=', '');
    }

    // Empresas a las que fue asignado
    public function empresasAsignadas(): BelongsToMany
    {
        return $this->belongsToMany(
            Empresa::class,
            'manual_empresa_assignments',
            'manual_id',
            'empresa_id'
        )->withPivot('asignado_por', 'asignado_at');
    }

    // ── Asignaciones v2.3 ────────────────────────────────────────────

    // Usuarios con acceso individual al manual (vía manual_user_assignments).
    // Antes vivía en manual_assignments — la tabla fue migrada en v2.3.
    public function usuariosAsignados(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'manual_user_assignments',
            'manual_id',
            'user_id'
        )->withPivot('assigned_by', 'assigned_at', 'empresa_id');
    }

    // Alias para mantener compatibilidad con código viejo
    public function empleadosAsignados(): BelongsToMany
    {
        return $this->usuariosAsignados();
    }

    // Categorías a las que el manual está dirigido. Cualquier usuario que
    // tenga una de estas categorías (activa) verá el manual.
    public function categorias(): BelongsToMany
    {
        return $this->belongsToMany(
            FranchiseCategory::class,
            'manual_category_assignments',
            'manual_id',
            'category_id'
        )->withPivot('empresa_id', 'assigned_by', 'assigned_at');
    }

    // Filas pivote directas — útiles cuando se necesita assigned_by, fecha, etc.
    public function categoryAssignments(): HasMany
    {
        return $this->hasMany(ManualCategoryAssignment::class, 'manual_id');
    }

    public function userAssignments(): HasMany
    {
        return $this->hasMany(ManualUserAssignment::class, 'manual_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'manual_id');
    }

    // ── Scopes ───────────────────────────────────────────────────────

    public function scopePublicados($query)
    {
        return $query->where('estado', 'publicado');
    }

    public function scopeBorradores($query)
    {
        return $query->where('estado', 'borrador');
    }

    // Manuales asignados a una empresa específica
    public function scopeDeEmpresa($query, int $empresaId)
    {
        return $query->whereHas('empresasAsignadas', fn($q) =>
            $q->where('empresa_id', $empresaId)
        );
    }

    // Excluir manuales marcados como eliminados
    public function scopeNoEliminados($query)
    {
        return $query->where('estado', '!=', 'eliminado');
    }

    // Visibilidad para super_admin: ve todos los no-eliminados + los eliminados por franquiciantes
    // (porque para super_admin el borrado del franquiciante no es definitivo).
    // Los eliminados por super_admin sí quedan ocultos por defecto.
    public function scopeVisiblesParaSuperAdmin($query)
    {
        return $query->where(function ($q) {
            $q->where('estado', '!=', 'eliminado')
              ->orWhereHas('deletedBy', fn($du) => $du->where('rol', 'franquiciante'));
        });
    }

    // ── Helpers ──────────────────────────────────────────────────────


    public function estaEliminado(): bool
    {
        return $this->estado === 'eliminado';
    }

    public function estaPublicado(): bool
    {
        return $this->estado === 'publicado';
    }
}