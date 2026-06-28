<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Asignación de un documento a una categoría completa.
 * Apunta a Document (cabecera), no a DocumentVersion — el usuario
 * siempre ve la versión activa del documento asignado.
 *
 * UNIQUE (empresa_id, document_id, category_id).
 */
class DocumentCategoryAssignment extends Model
{
    protected $table = 'document_category_assignments';

    public $timestamps = false;

    protected $fillable = [
        'empresa_id',
        'document_id',
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

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
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