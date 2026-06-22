<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    protected $table = 'documents';

    public $timestamps = false;
    protected $dates = ['created_at', 'deleted_at'];

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
        'deleted_by',
        'deleted_at',
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

    // Usuario que eliminó el documento (puede ser super_admin o franquiciante)
    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
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

    // Excluir documentos eliminados (deleted_at NOT NULL)
    public function scopeNoEliminados($query)
    {
        return $query->whereNull('deleted_at');
    }

    // Visibilidad para super_admin: ve todos los no-eliminados + los eliminados por franquiciantes
    // (porque para super_admin el borrado del franquiciante no es definitivo).
    // Los eliminados por super_admin sí quedan ocultos por defecto.
    public function scopeVisiblesParaSuperAdmin($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('deleted_at')
              ->orWhereHas('deletedBy', fn($du) => $du->where('rol', 'franquiciante'));
        });
    }
}