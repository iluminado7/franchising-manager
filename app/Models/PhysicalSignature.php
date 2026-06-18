<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhysicalSignature extends Model
{
    protected $table = 'physical_signatures';

    public $timestamps = false;
    protected $dates = ['created_at', 'updated_at'];

    protected $fillable = [
        'manual_version_id',
        'franquicia_id',
        'subido_por',
        'archivo_url',
        'archivo_hash',
        'notas',
    ];

    // ── Relaciones ───────────────────────────────────────────────────

    // La versión del manual firmada
    public function manualVersion(): BelongsTo
    {
        return $this->belongsTo(ManualVersion::class, 'manual_version_id');
    }

    // La franquicia cuyo representante firmó
    public function franquicia(): BelongsTo
    {
        return $this->belongsTo(Franquicia::class, 'franquicia_id');
    }

    // El franquiciante que subió el documento escaneado
    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }
}
