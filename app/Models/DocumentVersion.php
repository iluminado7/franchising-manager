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
    ];

    protected $casts = [
        'es_activa'     => 'boolean',
        'tamano_bytes'  => 'integer',
        'version_number'=> 'integer',
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

    // ── Scopes ───────────────────────────────────────────────────────

    // DocumentVersion::activas()->...  → versiones actualmente vigentes
    public function scopeActivas($query)
    {
        return $query->where('es_activa', 1);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    public function esActiva(): bool
    {
        return (bool) $this->es_activa;
    }
}