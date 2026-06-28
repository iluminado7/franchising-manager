<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Catálogo de categorías por empresa.
 * Cada empresa tiene su propio set independiente (Distribuidor, Licenciatario,
 * Dropshipper, etc). UNIQUE (empresa_id, name).
 *
 * Las categorías NO modifican permisos del usuario — solo afectan qué
 * manuales y documentos ve según las asignaciones por categoría.
 */
class FranchiseCategory extends Model
{
    protected $table = 'franchise_categories';

    protected $fillable = [
        'empresa_id',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ── Relaciones ───────────────────────────────────────────────────

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    // Usuarios que pertenecen a esta categoría (vía user_categories)
    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'user_categories',
            'category_id',
            'user_id'
        )->withPivot(['empresa_id', 'assigned_by', 'assigned_at']);
    }

    // Manuales asignados a esta categoría
    public function manualesAsignados(): BelongsToMany
    {
        return $this->belongsToMany(
            Manual::class,
            'manual_category_assignments',
            'category_id',
            'manual_id'
        )->withPivot(['empresa_id', 'assigned_by', 'assigned_at']);
    }

    // Documentos asignados a esta categoría
    public function documentosAsignados(): BelongsToMany
    {
        return $this->belongsToMany(
            Document::class,
            'document_category_assignments',
            'category_id',
            'document_id'
        )->withPivot(['empresa_id', 'assigned_by', 'assigned_at']);
    }

    // Acceso directo a las filas pivote (útil cuando se necesita assigned_by, fecha, etc.)
    public function userCategoryAssignments(): HasMany
    {
        return $this->hasMany(UserCategory::class, 'category_id');
    }

    public function manualCategoryAssignments(): HasMany
    {
        return $this->hasMany(ManualCategoryAssignment::class, 'category_id');
    }

    public function documentCategoryAssignments(): HasMany
    {
        return $this->hasMany(DocumentCategoryAssignment::class, 'category_id');
    }

    // ── Scopes ───────────────────────────────────────────────────────

    public function scopeActivas($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDeEmpresa($query, int $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }
}