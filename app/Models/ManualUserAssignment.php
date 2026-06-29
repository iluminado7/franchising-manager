<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Asignación de un manual a un usuario específico.
 * Reemplaza ManualAssignment (que apuntaba a la tabla manual_assignments
 * eliminada en la migración v2.3). Apunta a manual_user_assignments.
 *
 * Tiene PK autoincremental (a diferencia del modelo anterior).
 * UNIQUE (empresa_id, manual_id, user_id).
 */
class ManualUserAssignment extends Model
{
    protected $table = 'manual_user_assignments';

    public $timestamps = false;

    protected $fillable = [
        'empresa_id',
        'manual_id',
        'user_id',
        'assigned_by',
        'assigned_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    // ── Relaciones ───────────────────────────────────────────────────

    public function manual(): BelongsTo
    {
        return $this->belongsTo(Manual::class, 'manual_id');
    }

    // Usuario al que se asignó (franquiciado o empleado típicamente).
    // Antes se llamaba `empleado()` — se renombra a `user()` porque ahora
    // también puede ser franquiciado.
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Alias para mantener compatibilidad con código viejo que usaba ManualAssignment::empleado()
    public function empleado(): BelongsTo
    {
        return $this->user();
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function asignadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
