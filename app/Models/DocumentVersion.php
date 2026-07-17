<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentVersion extends Model
{
    protected $table = 'document_versions';

    public $timestamps = false;
    protected $dates = ['subido_at'];

    // V2-H-019 (mass assignment): es_activa NO esta en $fillable.
    //
    // Marcar una version como activa decide que archivo descargan/ven todos
    // los usuarios. Si quedara en $fillable, un
    // DocumentVersion::create($request->all()) o ->update($request->all())
    // permitiria activar cualquier version salteando la logica de
    // subirVersion/destroyVersion/restoreVersion (transaccion, lock pesimista,
    // desactivacion de la anterior, UNIQUE generado uq_dv_es_activa). Se setea
    // con setter directo ($v->es_activa = 1; $v->save();) en el controlador.
    protected $fillable = [
        'document_id',
        'version_number',
        'version_minor',
        'previous_version_id',
        'archivo_url',
        'archivo_hash',
        'mime_type',
        'tamano_bytes',
        'nota',
        'subido_por',
        'subido_at',
        'deleted_by',
        'deleted_at',
    ];

    protected $casts = [
        'es_activa'      => 'boolean',
        'tamano_bytes'   => 'integer',
        'version_number' => 'integer',
        'version_minor'  => 'integer',
        'subido_at'      => 'datetime',
        'deleted_at'     => 'datetime',
    ];

    // version_label ("3.1") disponible en las respuestas JSON (historial, version activa).
    protected $appends = ['version_label'];

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

    // v2.3: Versión anterior en la cadena de versiones (FK auto-referencial).
    // Puede ser NULL si es la primera versión o si la anterior fue eliminada
    // físicamente (ON DELETE SET NULL).
    public function previousVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'previous_version_id');
    }

    // Inverso: versiones que apuntan a esta como su anterior.
    // Útil para reconstruir el árbol completo. Normalmente debería tener
    // 0 o 1 fila — más de una indicaría una bifurcación rara.
    public function nextVersions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class, 'previous_version_id');
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

    // NOTA: este método tiene un bug pre-existente — la firma mezcla
    // scope y helper. Considerar refactorizar a scopeActivaNoEliminada
    // o eliminar si no se usa.
    public function esActiva($query): bool
    {
        return $query
        ->where('es_activa', 1)
        ->whereNull('deleted_at');
    }

    // Etiqueta de version en formato mayor.menor, p. ej. "3.1".
    public function getVersionLabelAttribute(): string
    {
        return $this->version_number . '.' . ($this->version_minor ?? 0);
    }
}