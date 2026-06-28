<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivote N:M entre usuarios y categorías.
 * Un usuario puede tener cero, una o varias categorías de su empresa.
 * PK compuesta (user_id, category_id) — la unicidad la impone la DB.
 *
 * Para CRUD masivo conviene usar:
 *   - $user->categorias()->attach($categoryId, [...])
 *   - $user->categorias()->detach($categoryId)
 *   - $user->categorias()->sync([$id1 => [...], $id2 => [...]])
 *
 * Este modelo se usa principalmente para queries de lectura con relaciones
 * (assigned_by, assigned_at) cuando se necesita el detalle del pivote.
 */
class UserCategory extends Model
{
    protected $table = 'user_categories';

    // PK compuesta (user_id, category_id) — Eloquent no la maneja nativamente.
    public $incrementing = false;
    protected $primaryKey = null;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'category_id',
        'empresa_id',
        'assigned_by',
        'assigned_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    // ── Relaciones ───────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FranchiseCategory::class, 'category_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}