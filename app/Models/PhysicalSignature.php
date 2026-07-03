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
        'user_id',
        'subido_por',
        // Feature "Aceptaciones": path interno del storage, no URL pública.
        // El frontend descarga vía el endpoint autenticado /firmas-fisicas/{id}/descargar.
        'archivo_path',
        'archivo_hash',
        'notas',
    ];

    // ── Relaciones ───────────────────────────────────────────────────

    // La versión del manual firmada
    public function manualVersion(): BelongsTo
    {
        return $this->belongsTo(ManualVersion::class, 'manual_version_id');
    }

    // La sucursal donde firma el socio (opcional — puede ser null si el socio
    // no tiene sucursal asignada, ej: distribuidor o dropshipper).
    public function franquicia(): BelongsTo
    {
        return $this->belongsTo(Franquicia::class, 'franquicia_id');
    }

    // El socio comercial que firmó el papel. Es la fuente de verdad de "quién
    // firmó" — reemplaza el modelo anterior donde la firma era "de la sucursal".
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // El super_admin o franquiciante que subió el PDF escaneado al sistema.
    // Distinto de user_id: puede ser un admin que sube en nombre del socio.
    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }
}