<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Asignación de un documento a un usuario específico.
 * Excepción individual al modelo de categorías — útil para documentos
 * reservados o casos puntuales.
 *
 * UNIQUE (empresa_id, document_id, user_id).
 */
class DocumentUserAssignment extends Model
{
    protected $table = 'document_user_assignments';

    public $timestamps = false;

    protected $fillable = [
        'empresa_id',
        'document_id',
        'user_id',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}