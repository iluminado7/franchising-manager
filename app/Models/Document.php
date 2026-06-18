<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    protected $table = 'documents';

    public $timestamps = false;
    protected $dates = ['created_at'];

    protected $fillable = [
        'empresa_id',
        'titulo',
        'tipo',
        'subido_por',
        'franquicia_id',
        'archivo_url',
        'archivo_hash',
        'mime_type',
        'tamano_bytes',
        'visible_franquiciado',
    ];

    protected $casts = [
        'visible_franquiciado' => 'boolean',
        'tamano_bytes'         => 'integer',
    ];

    // ── Relaciones ───────────────────────────────────────────────────

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }

    // NULL = documento general para toda la empresa
    public function franquicia(): BelongsTo
    {
        return $this->belongsTo(Franquicia::class, 'franquicia_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'document_id');
    }

    // ── Scopes ───────────────────────────────────────────────────────

    public function scopeGlobales($query)
    {
        return $query->whereNull('franquicia_id');
    }

    public function scopeVisiblesParaFranquiciado($query)
    {
        return $query->where('visible_franquiciado', 1);
    }

    public function scopeDeEmpresa($query, int $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }
}
