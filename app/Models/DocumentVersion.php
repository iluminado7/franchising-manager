<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentVersion extends Model
{
    protected $table = 'document_versions';

    public $timestamps = false;
    protected $dates = ['subido_at'];

    protected $fillable = [
        'document_id',
        'version_number',
        'archivo_url',
        'archivo_hash',
        'mime_type',
        'tamano_bytes',
        'nota',
        'es_activa',
        'subido_por',
        'subido_at',
        'deleted_by',
        'deleted_at',
    ];

    protected $casts = [
        'es_activa'     => 'boolean',
        'tamano_bytes'  => 'integer',
        'version_number'=> 'integer',
        'subido_at'      => 'datetime',
        'deleted_at'     => 'datetime',
    ];

    // ── Relaciones ───────────────────────────────────────────────────

    // El documento padre al que pertenece esta versión
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    // El usuario (super_admin o franquiciante) que subió esta versión
    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    // ── Scopes ───────────────────────────────────────────────────────

    // DocumentVersion::activas()->...  → versiones actualmente vigentes
    public function scopeActivas($query)
    {
        return $query->where('es_activa', 1);
    }

    public function scopeNoEliminadas($query)
    {
        return $query->whereNull('deleted_at');
    }
    // ── Helpers ──────────────────────────────────────────────────────
    public function estaEliminada(): bool
    {
        return !is_null($this->deleted_at);
    }

    public function esActiva($query): bool
    {
        return $query
        ->where('es_activa', 1)
        ->whereNull('deleted_at');
    }
    
}