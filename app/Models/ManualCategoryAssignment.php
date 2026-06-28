<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Asignación de un manual a una categoría completa.
 * Todos los usuarios que tengan esa categoría en esa empresa verán el manual.
 *
 * UNIQUE (empresa_id, manual_id, category_id).
 */
class ManualCategoryAssignment extends Model
{
    protected $table = 'manual_category_assignments';

    public $timestamps = false;

    protected $fillable = [
        'empresa_id',
        'manual_id',
        'category_id',
        'assigned_by',
        'assigned_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    // ── Relaciones ───────────────────────────────────────────────────

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function manual(): BelongsTo
    {
        return $this->belongsTo(Manual::class, 'manual_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FranchiseCategory::class, 'category_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}